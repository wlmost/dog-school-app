## Context

- `backend/resources/views/pdf/invoice.blade.php:175-182` — der
  betroffene Absatz:
  ```blade
  <p><strong>Status:</strong>
      <span class="status-badge">
          @if($invoice->status === 'paid') BEZAHLT
          @elseif($invoice->status === 'overdue') ÜBERFÄLLIG
          @else {{ strtoupper($invoice->status) }}
          @endif
      </span>
  </p>
  ```
  liegt innerhalb von `<div class="invoice-details">` (Zeilen 170-183),
  direkt nach den Zeilen "Rechnungsnummer", "Rechnungsdatum",
  "Fälligkeitsdatum". Er ist der letzte `<p>` in diesem `<div>` — nach
  Entfernung schließt das `<div class="invoice-details">` direkt mit den
  drei verbleibenden `<p>`-Zeilen.
- `backend/resources/views/pdf/invoice.blade.php:117-123` — zugehöriges
  CSS:
  ```css
  .status-badge {
      padding: 3px 10px;
      background-color: #d1fae5;
      color: #065f46;
      font-weight: bold;
      font-size: 8pt;
  }
  ```
  Grep über die gesamte Datei zeigt `.status-badge` ausschließlich an
  diesen zwei Stellen (Definition Zeile 117, Verwendung Zeile 176) —
  nach Entfernung des Absatzes ist die Klasse in dieser Datei tot.
- `backend/resources/views/pdf/anamnesis.blade.php:101` definiert eine
  **eigene, gleichnamige** `.status-badge`-Regel in einem eigenen
  `<style>`-Block einer komplett separaten Blade-View (kein gemeinsames
  Stylesheet, kein `@extends`/`@include` zwischen beiden PDF-Templates).
  Diese Datei ist von der Änderung nicht betroffen und bleibt
  unangetastet.
- `backend/resources/views/pdf/invoice.blade.php:258`
  (`@if($invoice->status !== 'paid') ... @else ... @endif`) verwendet
  `$invoice->status` ebenfalls, aber ausschließlich zur Auswahl zwischen
  zwei HTML-Blöcken (`.payment-box` mit Bankdaten vs. Bestätigungstext).
  Der Rohstatus wird dort an keiner Stelle als Text ausgegeben. Diese
  Zeile bleibt unverändert — Verwechslungsgefahr für den Entwickler
  besteht, weil beide Stellen `$invoice->status` referenzieren, aber nur
  eine davon (175-182) ist eine sichtbare Statusanzeige.
- `backend/database/migrations/2025_12_22_185107_create_invoices_table.php:18`
  — `status` ist ein `enum('status', ['draft', 'sent', 'paid', 'overdue',
  'cancelled'])->default('draft')`. Neue Rechnungen starten also mit
  `draft`, was aktuell über den `@else`-Zweig als "DRAFT" im PDF landet.
- `backend/app/Http/Controllers/Api/InvoiceController.php:228-242`
  (`downloadPdf()`) — lädt Beziehungen, prüft Autorisierung
  (`$this->authorize('view', $invoice)`), rendert `pdf.invoice` und
  liefert den Download aus. Keine Einschränkung nach Status. Diese
  Methode wird von diesem Change nicht angefasst — es geht ausschließlich
  um die Template-Anzeige, nicht darum, ob `draft`-Rechnungen überhaupt
  herunterladbar sein dürfen (das wäre eine andere Anforderung und ist
  nicht Teil der Triage).
- `backend/tests/Feature/InvoicePdfTest.php:116-144` — die Tests "PDF
  shows paid status correctly" und "PDF shows overdue status correctly"
  prüfen ausschließlich `assertOk()`, den `content-type`-Header und
  `expect($response->getContent())->not()->toBeEmpty()`. Kein Test
  prüft den Textinhalt auf "Status" oder einen konkreten Statuswert —
  die Entfernung des Absatzes bricht keinen bestehenden Test.

## Goals / Non-Goals

**Goals:**
- Der interne Rechnungsstatus (insbesondere "DRAFT") erscheint nicht
  mehr als Text im generierten Rechnungs-PDF, unabhängig vom
  `status`-Wert der Rechnung.
- Die funktionale Unterscheidung "bezahlt vs. offen" (Zeile 258 ff.,
  Zahlungsinformationen- vs. Zahlungsbestätigungs-Box) bleibt vollständig
  erhalten und unverändert.
- Kein totes CSS im Template zurücklassen (`.status-badge` wird mit
  entfernt, da nach dieser Änderung ungenutzt).
- Ein neuer, gezielter Test verhindert eine künftige Regression (z. B.
  falls jemand den Absatz versehentlich wieder einführt).

**Non-Goals:**
- Keine Einschränkung, welche Rechnungsstatus überhaupt als PDF
  herunterladbar sind (`InvoiceController::downloadPdf()` bleibt
  unverändert) — das ist eine andere, nicht triagierte Anforderung.
- Kein Ersatz-Label oder Platzhalter-Text an der Stelle des entfernten
  Absatzes (User-Entscheidung: kompletter Absatz weg, kein
  rudimentärer Rest).
- Keine Änderung an `pdf/anamnesis.blade.php` — dessen
  "ABGESCHLOSSEN"-Badge und eigene `.status-badge`-Regel sind ein
  anderer Dokumenttyp und nicht Teil dieser Anforderung.
- Kein PDF-Text-Extraktions-Tooling (z. B. `smalot/pdfparser`) als neue
  Abhängigkeit — laut `composer.json`/`composer.lock` ist aktuell keine
  PDF-Text-Extraktions-Bibliothek vorhanden (YAGNI für diesen kleinen
  Fix). Stattdessen wird der Test direkt gegen das gerenderte
  Blade-HTML geprüft (siehe Decision unten) — das PDF wird über
  `Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.invoice', ...)`
  (`InvoiceController.php:16,236`) aus exakt diesem HTML erzeugt, ein
  Wegfall des Textes im HTML garantiert den Wegfall im PDF.

## Decisions

**1. Test prüft das gerenderte Blade-HTML, nicht den PDF-Binärinhalt.**
Geprüfte Alternative: Text-Extraktion aus dem generierten PDF (z. B. via
`smalot/pdfparser`). Verworfen, weil diese Bibliothek aktuell keine
Projekt-Abhängigkeit ist (`composer.json`/`composer.lock` enthalten
weder `smalot/pdfparser` noch eine vergleichbare Bibliothek) und ihre
Einführung für einen "klein" eingestuften Fix unverhältnismäßig wäre
(YAGNI). Da `InvoiceController::downloadPdf()` das PDF direkt aus
`view('pdf.invoice', ...)` per `Pdf::loadView()` erzeugt
(`InvoiceController.php:236`), ist ein Test, der
`view('pdf.invoice', ['invoice' => $invoice])->render()` aufruft und den
resultierenden HTML-String auf Abwesenheit von "Status:" und dem
Rohstatus-Wert prüft, gleichwertig aussagekräftig und deutlich
einfacher — dieses Muster ist im Projekt bereits etabliert, siehe
`backend/tests/Feature/Pdf/InvoiceBankDetailsPdfTest.php` (rendert die
View direkt und prüft `$html` per `expect(...)->toContain(...)` /
`->not->toContain(...)`).

**2. Kompletter Absatz + CSS-Klasse entfernt, kein Ersatzinhalt.**
Bereits vom User entschieden (siehe Auftrag) — nicht erneut zur
Diskussion gestellt. Konsequenz für die Umsetzung: `<div
class="invoice-details">` behält nach der Änderung nur noch die drei
`<p>`-Zeilen "Rechnungsnummer", "Rechnungsdatum", "Fälligkeitsdatum".

**3. Zeile 258 (`@if($invoice->status !== 'paid')`) bleibt unverändert.**
Sie gibt den Status nicht als Text aus, sondern steuert eine bereits
gewünschte funktionale Verzweigung. Eine Entfernung oder Änderung wäre
ein Scope-Creep über die Anforderung hinaus und würde die
Zahlungsinformationen-/-bestätigungs-Logik brechen.

## DB-Portabilität

Keine Migration, kein raw SQL, keine neue Spalte — reine
Blade-Template-Änderung (Entfernen von Markup/CSS). Kein Datenbankzugriff
betroffen; keine Prüfung im Sinne von CLAUDE.md Abschnitt 4.2 nötig.

## Risks / Trade-offs

- **Gering:** Falls in Zukunft doch wieder ein Statushinweis im PDF
  gewünscht wird (z. B. ein "ENTWURF"-Wasserzeichen für `draft`-Rechnungen),
  müsste das als neue, eigene Anforderung/Change behandelt werden — das
  ist explizit kein Bestandteil dieses Fixes.
- **Gering:** `.status-badge` wird nur aus `invoice.blade.php` entfernt.
  Die gleichnamige Klasse in `anamnesis.blade.php` bleibt bestehen; da
  beide Dateien unabhängige `<style>`-Blöcke ohne gemeinsames Stylesheet
  haben, keine Kollisions- oder Regressionsgefahr für das Anamnese-PDF.

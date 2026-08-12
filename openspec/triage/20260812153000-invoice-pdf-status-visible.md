# Triage: Rechnungsstatus (z. B. "Draft") wird im Invoice-PDF ausgedruckt

**Pfad:** klein
**Geschätzter Umfang:** 1 Datei (Blade-Template, PHP-Stack)
**Risiko:** niedrig — reine Anzeige-/Template-Änderung, keine Migration, kein Schnittstellen-Bruch, betrifft aber ein an Kunden ausgehändigtes Dokument (Rechnung).
**Klarheit:** klar — der User hat eindeutig formuliert, dass der Status im generierten PDF nicht erscheinen darf.

## Repo-Recherche

- Der Rechnungsstatus wird in `backend/resources/views/pdf/invoice.blade.php:175-182` sichtbar ausgegeben:
  ```
  175: <p><strong>Status:</strong>
  176:     <span class="status-badge">
  177:         @if($invoice->status === 'paid') BEZAHLT
  178:         @elseif($invoice->status === 'overdue') ÜBERFÄLLIG
  179:         @else {{ strtoupper($invoice->status) }}
  180:         @endif
  181:     </span>
  182: </p>
  ```
  Bei Status `draft` (Enum-Wert laut `backend/database/migrations/2025_12_22_185107_create_invoices_table.php:18`: `enum('status', ['draft', 'sent', 'paid', 'overdue', 'cancelled'])->default('draft')`) landet über den `@else`-Zweig (`strtoupper($invoice->status)`) das Wort "DRAFT" im PDF — das ist exakt der gemeldete Fehler.
- Zugehöriges CSS: `.status-badge` in `backend/resources/views/pdf/invoice.blade.php:117-123`. Nach Entfernen des Status-Blocks wird diese Klasse in dieser Datei nicht mehr referenziert (ungeprüfte Referenz auf andere Nutzung: `backend/resources/views/pdf/anamnesis.blade.php:101` definiert eine **eigene** gleichnamige `.status-badge`-Regel in einer separaten `<style>`-Sektion — beide Dateien sind unabhängige PDF-Views ohne gemeinsames Stylesheet, daher unkritisch für Cleanup-Entscheidung in `invoice.blade.php`).
- **Wichtig — nicht zu verwechseln:** `backend/resources/views/pdf/invoice.blade.php:258` (`@if($invoice->status !== 'paid')`) verwendet den Status ebenfalls, aber **nicht als sichtbaren Text**, sondern zur bedingten Auswahl zwischen "Zahlungsinformationen"-Box und "Zahlungsbestätigung"-Box. Das ist eine funktionale Verzweigung, keine Status-Anzeige, und fällt nicht unter die Anforderung — bleibt unverändert.
- PDF wird erzeugt in `backend/app/Http/Controllers/Api/InvoiceController.php:228-242` (`downloadPdf()`), dort keine Einschränkung nach Status — auch `draft`-Rechnungen können aktuell als PDF heruntergeladen werden, was den Bug überhaupt sichtbar macht.
- Kein weiteres PDF-Template zeigt den *Invoice*-Status. `backend/resources/views/pdf/anamnesis.blade.php:137-139` hat ein eigenes, hartcodiertes "ABGESCHLOSSEN"-Badge — anderer Dokumenttyp (Anamnesebogen), nicht Teil dieser Anforderung.
- E-Mail-Template `backend/resources/views/emails/invoice-created.blade.php` enthält laut Grep keinen Status-Text — nicht betroffen.

## Bewertung: reines Anzeige-/Template-Problem

Es handelt sich um ein reines Template-Problem, kein Kontext-/Datenmodell-Thema:
- Genau eine Datei ist betroffen (`backend/resources/views/pdf/invoice.blade.php`).
- Kein anderer Dokumenttyp zeigt den Invoice-Status.
- Der Status wird an keiner anderen Stelle des PDFs für interne Zwecke benötigt — die einzige andere Verwendung (Zeile 258) ist bereits eine funktionale Verzweigung ohne Textausgabe des Rohstatus und bleibt unverändert.
- Bestehende Tests (`backend/tests/Feature/InvoicePdfTest.php:116-144`, u. a. "PDF shows paid status correctly", "PDF shows overdue status correctly") assertieren nur `assertOk()`, `content-type` und `not()->toBeEmpty()` — **kein** Test prüft den Textinhalt des PDFs auf den String "Status" oder "DRAFT" o. Ä. Die Entfernung bricht daher keine bestehenden Tests, sollte aber ggf. um einen neuen Test ergänzt werden, der sicherstellt, dass kein Status-Text mehr im generierten PDF-Text vorkommt (Text-Extraktion aus dem PDF nötig, falls möglich — sonst als manuelle/visuelle Prüfung dokumentieren).

**Offene Design-Entscheidung (kein Blocker, aber sollte im Change explizit festgehalten werden):** Soll der komplette "Status"-Absatz (Zeilen 175–182) sowie die zugehörige CSS-Klasse `.status-badge` (Zeilen 117–123) entfernt werden, oder soll nur der Status-Wert nicht mehr ausgegeben werden (z. B. Label "Status:" komplett weg, ohne Ersatz)? Empfehlung: kompletten Absatz + ungenutztes CSS entfernen, da die Anforderung sagt "der Status darf... nicht erscheinen" — ein leeres oder rudimentäres "Status:"-Label ohne Wert wäre ebenfalls verwirrend für den Kunden.

## Anforderung (Zusammenfassung)

Auf dem generierten Rechnungs-PDF (`backend/resources/views/pdf/invoice.blade.php`) wird aktuell der interne Rechnungsstatus (z. B. "DRAFT" bei Entwürfen) sichtbar ausgedruckt. Das ist ein interner/administrativer Zustand und darf einem Kunden auf der Rechnung nicht angezeigt werden. Die Status-Ausgabe (Zeilen 175–182) soll entfernt werden, ohne die bereits vorhandene funktionale Unterscheidung "bezahlt vs. offen" (Zeile 258 ff., Zahlungsinformationen vs. Zahlungsbestätigung) zu verändern.

## Rückfragen an den User

Keine — Klarheit ist gegeben. Optional zur Bestätigung (kein Blocker für den Start): Einverstanden, dass der komplette "Status:"-Absatz samt CSS-Klasse `.status-badge` entfernt wird (siehe "Offene Design-Entscheidung" oben), statt nur den Wert zu leeren?

## Empfohlene nächste Aktion

Einstufung **klein**: 1 Datei, kein Schnittstellen-Bruch, klare Anforderung, niedriges Risiko (reine Template-Anzeige, betrifft aber ein Kundendokument — deshalb nicht "trivial", sondern mit kurzem Architekt-Schritt zur Dokumentation der Design-Entscheidung oben).

Nächster Agent: `@architect` (Mode A) erstellt einen schlanken openspec-Change (z. B. `fix-invoice-pdf-status-visible`) mit einer einzigen Task für `dev-php`:
- Entfernen des Status-Absatzes `backend/resources/views/pdf/invoice.blade.php:175-182` und der zugehörigen CSS-Klasse `.status-badge:117-123`.
- Zeile 258 (`@if($invoice->status !== 'paid')`) unverändert lassen — explizit in Spec/Design vermerken, damit der Entwickler es nicht versehentlich mitentfernt.
- Optional: neuer Pest-Test, der prüft, dass der Status-String nicht mehr im PDF-Output vorkommt (falls Text-Extraktion aus dem dompdf-Output technisch machbar ist — sonst manuelle Prüfnotiz).

Danach direkt weiter mit `@dev-php` für die Umsetzung, anschließend `@reviewer` + `@tester` parallel, dann Abnahme. Ein Skeptiker-Lauf ist bei diesem Umfang optional/kann entfallen (Pfad "klein" sieht laut Workflow-Tabelle ohnehin keinen Skeptiker vor), da keine strukturellen Mehrdeutigkeiten bestehen.

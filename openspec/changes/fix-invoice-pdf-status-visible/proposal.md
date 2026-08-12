## Why

Das Rechnungs-PDF (`backend/resources/views/pdf/invoice.blade.php:175-182`)
zeigt aktuell einen "Status:"-Absatz mit einem `<span class="status-badge">`,
der je nach `$invoice->status` "BEZAHLT", "ÜBERFÄLLIG" oder — im
`@else`-Zweig — `strtoupper($invoice->status)` ausgibt. Da neue Rechnungen
laut `backend/database/migrations/2025_12_22_185107_create_invoices_table.php:18`
(`enum('status', ['draft', 'sent', 'paid', 'overdue', 'cancelled'])->default('draft')`)
mit Status `draft` starten, landet über genau diesen `@else`-Zweig das Wort
"DRAFT" im ausgedruckten PDF. `backend/app/Http/Controllers/Api/InvoiceController.php:228-242`
(`downloadPdf()`) schränkt den PDF-Download nicht nach Status ein — auch
`draft`-Rechnungen sind aktuell herunterladbar, wodurch der interne
Zustand einem Kunden auf einem ausgehändigten Dokument sichtbar wird. Das
ist für ein Kundendokument nicht angemessen.

## What Changes

- Der komplette Status-Absatz in `pdf/invoice.blade.php` (Zeilen 175-182:
  Label "Status:" + `<span class="status-badge">`-Block) wird entfernt —
  nicht nur der ausgegebene Wert. Der Absatz wird ersatzlos gestrichen,
  kein Platzhalter, kein leeres Label.
- Die CSS-Klasse `.status-badge` (definiert im `<style>`-Block derselben
  Datei, Zeilen 117-123) wird ebenfalls entfernt, da sie nach Entfernung
  des Absatzes in `invoice.blade.php` ungenutzt ist. (Die gleichnamige,
  aber unabhängige `.status-badge`-Regel in
  `backend/resources/views/pdf/anamnesis.blade.php:101` gehört zu einem
  separaten `<style>`-Block einer anderen PDF-View und ist von dieser
  Änderung nicht betroffen.)
- **Unverändert bleibt** `pdf/invoice.blade.php:258`
  (`@if($invoice->status !== 'paid')`) — diese Zeile gibt den Rohstatus
  nicht als Text aus, sondern steuert nur die funktionale Auswahl
  zwischen der "Zahlungsinformationen"-Box (Bankdaten) und der
  "Zahlungsbestätigung"-Box. Diese Unterscheidung ist explizit
  gewünscht und bleibt bestehen.
- Neuer Pest-Test, der sicherstellt, dass ein per `view('pdf.invoice',
  ['invoice' => $invoice])->render()` gerendertes Rechnungs-PDF-HTML den
  String "Status:" bzw. den Rohstatus-Wert (z. B. "DRAFT") nicht mehr
  enthält — unabhängig vom `status`-Wert der Rechnung.

## Capabilities

### New Capabilities
- `invoice-pdf-status-display`: Regelt, dass das Rechnungs-PDF keinen
  internen Dokumentstatus als Text anzeigt, während die funktionale
  Unterscheidung zwischen offener und bezahlter Rechnung (Zahlungsbox)
  erhalten bleibt.

### Modified Capabilities
_Keine — es existiert noch keine Spec zur Statusanzeige im Rechnungs-PDF;
dieser Change führt eine neue, schlanke Capability ein. Die vorhandene
Capability `invoice-bank-details` (Zahlungsinformationen-Box) ist
inhaltlich benachbart, aber fachlich getrennt und bleibt unverändert._

## Impact

**Backend:**
- `backend/resources/views/pdf/invoice.blade.php` — Status-Absatz
  (Zeilen 175-182) und CSS-Klasse `.status-badge` (Zeilen 117-123)
  entfernt; Zeile 258 (`@if($invoice->status !== 'paid')`) unverändert.
- `backend/tests/Feature/InvoicePdfTest.php` — kein bestehender Test
  bricht (die vorhandenen Tests prüfen nur `assertOk()`/Content-Type/
  Nicht-Leerheit, keinen Textinhalt); ein neuer Test wird ergänzt.

**Nicht betroffen (geprüft, kein Änderungsbedarf):**
- `backend/app/Http/Controllers/Api/InvoiceController.php` — keine
  Änderung an `downloadPdf()`, insbesondere keine neue
  Status-Einschränkung für den PDF-Download (nicht Teil der Anforderung).
- `backend/resources/views/pdf/anamnesis.blade.php` — eigenes,
  hartkodiertes "ABGESCHLOSSEN"-Badge für einen anderen Dokumenttyp
  (Anamnesebogen), nicht Teil dieser Anforderung; eigene, unabhängige
  `.status-badge`-CSS-Regel bleibt unangetastet.
- `backend/resources/views/emails/invoice-created.blade.php` — enthält
  laut Recherche keinen Status-Text.
- `frontend/**` — keine Frontend-Änderung, die Status-Anzeige in der
  Rechnungsverwaltung im Browser ist nicht Teil der Anforderung (nur das
  PDF, das an Kunden ausgehändigt wird).

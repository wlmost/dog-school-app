## Why

Rechnungs-PDF und Anamnese-PDF zeigen im Kopf- und Fußbereich eine fest
einprogrammierte Muster-Firmenadresse statt der echten, vom Admin in den
Systemeinstellungen gepflegten Adressdaten:

- `backend/resources/views/pdf/invoice.blade.php:149-151` (Kopf):
  `<h1>Hundeschule Max Mustermann</h1>`, `<p>Musterstraße 123 • 12345
  Musterstadt</p>`, `<p>Tel: +49 123 456789 • E-Mail:
  info@hundeschule-mustermann.de</p>`
- `backend/resources/views/pdf/invoice.blade.php:289-291` (Fuß):
  `<p>Hundeschule Max Mustermann • Musterstraße 123 • 12345
  Musterstadt</p>`, `<p>USt-IdNr: DE123456789</p>`
- `backend/resources/views/pdf/anamnesis.blade.php:126-128` (Kopf) und
  `:272-273` (Fuß): identische hartkodierte Werte

Die Einstellungen existieren bereits vollständig und funktionieren: das
`Setting`-Model (`backend/app/Models/Setting.php:62-73`,
`Setting::get(string $key, $default = null)`, gecacht) liest aus der
`settings`-Tabelle, befüllt über `backend/database/seeders/SettingsSeeder.php:19-28`
mit den Keys `company_name`, `company_street`, `company_zip`,
`company_city`, `company_country`, `company_phone`, `company_email`,
`company_website`, `company_tax_id`, `company_registration_number` (Gruppe
`company`). Admins pflegen diese Werte über das Settings-Formular; nur die
beiden PDF-Templates lesen sie nicht.

Im selben Rechnungs-PDF existiert bereits eine korrekte
Referenzimplementierung für exakt dieses Muster: der Bankdaten-Block
(`backend/resources/views/pdf/invoice.blade.php:128-133`, aus dem
archivierten Change `add-invoice-bank-details`) lädt Settings direkt per
`\App\Models\Setting::get('company_bank_account_holder', ...)` etc. in
einem `@php`-Block. Dieser Change überträgt dasselbe Muster auf
Firmenname/-adresse/-kontakt/USt-IdNr.

Es gibt im Repo nur zwei `Pdf::loadView(...)`-Aufrufe
(`backend/app/Http/Controllers/Api/InvoiceController.php:236`,
`backend/app/Http/Controllers/AnamnesisResponseController.php:211`), also
genau die zwei betroffenen Templates — kein unbekannter Rest weiterer
Dokumenttypen.

**Bekannter Nebeneffekt (muss mit behoben werden, sonst bricht die
Test-Suite):** Der bestehende Test
`backend/tests/Feature/Pdf/InvoiceBankDetailsPdfTest.php:65-72`
("lässt company_name company_street company_city und company_tax_id
unverändert hartkodiert") behauptet explizit, dass die hartkodierten
Platzhalterwerte **bestehen bleiben** — das war zum Zeitpunkt von
`add-invoice-bank-details` korrekt (der Firmenname war dort bewusst als
Non-Goal ausgeklammert, siehe dessen `proposal.md`), widerspricht aber
genau dem, was dieser Change beheben soll. Ohne Anpassung dieses Tests
schlägt `composer qa` nach der Template-Änderung fehl.

## What Changes

- Firmenkopf (Name, Straße, PLZ/Ort, Telefon, E-Mail) und Firmenfuß (Name,
  Straße, PLZ/Ort, USt-IdNr) in `pdf/invoice.blade.php` und
  `pdf/anamnesis.blade.php` lesen die Werte per `\App\Models\Setting::get()`
  aus den bestehenden Settings-Keys `company_name`, `company_street`,
  `company_zip`, `company_city`, `company_phone`, `company_email`,
  `company_tax_id` statt sie hartzukodieren.
- Zwei neue, gemeinsam genutzte Blade-Partials
  (`pdf/partials/company-info.blade.php` für den Kopf-Textblock,
  `pdf/partials/company-footer-lines.blade.php` für die Fuß-Textzeilen)
  fassen die zwischen beiden PDF-Templates identischen Textzeilen an
  einer Stelle zusammen (DRY) — die umgebende Struktur (Logo-Tabelle im
  Rechnungs-PDF vs. einfaches `div` im Anamnese-PDF) bleibt in den
  jeweiligen Templates, nur der identische Textinhalt wird geteilt.
- Fehlende Settings-Werte führen zu leeren Feldern, nicht zu einer neuen
  Fantasieadresse und nicht zu einem PHP-Fehler (Default `''` bzw. der
  neutrale Fallback `'Hundeschule'` für den Firmennamen, konsistent zum
  bereits etablierten Fallback in `layouts/email.blade.php:143-159`).
- Anpassung des bestehenden Tests
  `backend/tests/Feature/Pdf/InvoiceBankDetailsPdfTest.php` (Test
  "lässt company_name company_street company_city und company_tax_id
  unverändert hartkodiert", Zeile 65-72), damit er die neue,
  settings-basierte Anzeige statt der alten hartkodierten Werte prüft.
- Keine Migration, keine Änderung an `InvoiceController::downloadPdf()`
  oder `AnamnesisResponseController::downloadPdf()` (beide übergeben
  weiterhin nur das jeweilige Model an die View; Settings werden wie
  beim bestehenden Bankdaten-Block direkt im Template geladen).

## Capabilities

### New Capabilities
- `pdf-company-branding`: Firmenname, -adresse, -kontaktdaten und
  USt-IdNr der Hundeschule werden im Rechnungs- und Anamnese-PDF aus den
  konfigurierten Systemeinstellungen dargestellt statt hartkodierter
  Platzhalterdaten.

### Modified Capabilities
_Keine — es existiert noch keine Spec für die PDF-Firmenkopf-/-fußdarstellung;
dieser Change führt eine neue Capability ein. Die bestehende Capability
`invoice-bank-details` ist inhaltlich benachbart (gleiche Templates,
gleiches `Setting::get()`-Muster), aber fachlich getrennt (Bankdaten vs.
Firmenstammdaten) und wird nicht verändert._

## Impact

**Backend:**
- `backend/resources/views/pdf/invoice.blade.php` — Kopf- (149-151) und
  Fußzeilen (289-291) durch `@include`s der neuen Partials mit
  Settings-Werten ersetzt
- `backend/resources/views/pdf/anamnesis.blade.php` — Kopf- (126-128) und
  Fußzeilen (272-273) durch `@include`s derselben Partials ersetzt
  (Fuß behält zusätzlich die bestehende "Erstellt am"-Zeile)
- `backend/resources/views/pdf/partials/company-info.blade.php` (neu)
- `backend/resources/views/pdf/partials/company-footer-lines.blade.php`
  (neu)
- `backend/tests/Feature/Pdf/InvoiceBankDetailsPdfTest.php` — ein Test
  (Zeile 65-72) wird von "hartkodiert bleibt erhalten" auf
  "settings-basiert" umgestellt

**Nicht betroffen (geprüft, kein Änderungsbedarf):**
- `backend/app/Models/Setting.php`, `backend/database/seeders/SettingsSeeder.php`,
  `backend/database/migrations/2026_01_05_144724_create_settings_table.php`
  — Settings-Keys existieren bereits vollständig, keine neuen Keys nötig
- `backend/app/Http/Controllers/Api/InvoiceController.php`,
  `backend/app/Http/Controllers/AnamnesisResponseController.php` — beide
  übergeben weiterhin nur das Model, kein neues `$settings`-Array nötig
  (gleiche Begründung wie beim bestehenden Bankdaten-Muster)
- `backend/tests/Unit/InvoiceBankDetailsBladeSourceTest.php` — prüft nur
  IBAN/BIC-Strings, keine Berührung mit Firmenname/-adresse
- `frontend/**` — keine Frontend-Änderung, das Settings-Formular zeigt die
  betroffenen Felder bereits korrekt an

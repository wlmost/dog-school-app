# Triage: PDF-Dokumente zeigen hardcodierte Muster-Adresse statt Settings

**Pfad:** klein
**Geschätzter Umfang:** 2 Dateien, PHP/Blade (`backend/resources/views/pdf/*.blade.php`)
**Risiko:** niedrig — nur Blade-Templates betroffen, keine Migration, keine Schnittstellenänderung, `Setting`-Model und Speicherung existieren bereits und werden bereits an anderer Stelle korrekt genutzt (Referenzimplementierung vorhanden).
**Klarheit:** klar — Ursache eindeutig identifiziert und im Code belegt, kein Rückfragebedarf.

## Anforderung (Zusammenfassung)

Auf Rechnungs-PDFs (und mindestens einem weiteren Dokumenttyp) erscheinen statt
der in den Einstellungen gepflegten Hundeschul-Adressdaten feste
Platzhalterwerte ("Hundeschule Max Mustermann", "Musterstraße 123", USt-IdNr
"DE123456789" etc.). Die Einstellungen selbst (`company_name`,
`company_street`, `company_zip`, `company_city`, `company_phone`,
`company_email`, `company_tax_id` usw.) existieren im `settings`-Table und
werden gepflegt, aber die betroffenen Blade-Templates lesen sie nicht aus,
sondern haben die Muster-Werte fest im HTML stehen.

## Befunde (mit Datei:Zeile-Belegen)

**Settings-Mechanismus (funktioniert, keine ungeprüfte Referenz):**
- `backend/app/Models/Setting.php:62-73` — `Setting::get(string $key, $default = null)`, gecacht.
- `backend/database/migrations/2026_01_05_144724_create_settings_table.php:14-25` — Tabelle `settings` (key/value/type/group).
- `backend/database/seeders/SettingsSeeder.php:14-28` — Gruppe `company` enthält u. a. `company_name`, `company_street`, `company_zip`, `company_city`, `company_country`, `company_phone`, `company_email`, `company_website`, `company_tax_id`, `company_registration_number` sowie die Bank-Settings.

**Betroffen — Rechnungs-PDF:**
- `backend/app/Http/Controllers/Api/InvoiceController.php:236` — `Pdf::loadView('pdf.invoice', ['invoice' => $invoice])`, keine Settings werden an die View übergeben (nur die Bank-Settings werden `pdf/invoice.blade.php` selbst via `Setting::get(...)` gezogen, s. u.).
- `backend/resources/views/pdf/invoice.blade.php:128-133` — Bank-/Zahlungsziel-Settings werden korrekt per `Setting::get('company_bank_account_holder', ...)` etc. geladen (offenbar aus dem kürzlich archivierten Change `add-invoice-bank-details`, s. `git log` Commit `9c04b69`/`2b83246`).
- `backend/resources/views/pdf/invoice.blade.php:149-151` — Firmenkopf ist hardcodiert: `<h1>Hundeschule Max Mustermann</h1>`, `<p>Musterstraße 123 • 12345 Musterstadt</p>`, `<p>Tel: +49 123 456789 • E-Mail: info@hundeschule-mustermann.de</p>` — nutzt weder `company_name` noch `company_street`/`company_zip`/`company_city`/`company_phone`/`company_email`.
- `backend/resources/views/pdf/invoice.blade.php:289-291` (Footer) — ebenfalls hardcodiert: `<p>Hundeschule Max Mustermann • Musterstraße 123 • 12345 Musterstadt</p>` und `<p>USt-IdNr: DE123456789</p>` — nutzt nicht `company_tax_id`.

**Betroffen — Anamnese-PDF (zweiter Dokumenttyp, bestätigt dieselbe Ursache):**
- `backend/app/Http/Controllers/AnamnesisResponseController.php:211` — `Pdf::loadView('pdf.anamnesis', ['response' => $anamnesisResponse])`, ebenfalls ohne Settings-Übergabe.
- `backend/resources/views/pdf/anamnesis.blade.php:126-128` (Kopf) und `:272-273` (Footer) — identische hardcodierte Werte ("Hundeschule Max Mustermann", "Musterstraße 123 • 12345 Musterstadt", Tel./E-Mail-Muster, "USt-IdNr: DE123456789").

**Nicht betroffen — Referenzimplementierung mit Fallback vorhanden:**
- `backend/resources/views/layouts/email.blade.php:142-167` — nutzt bereits korrekt `$settings['company_name'] ?? 'Hundeschule'` usw. (Null-Coalescing auf Platzhalter nur als Fallback, nicht als Primärquelle). Dieses Muster ist die naheliegende Vorlage für den Fix in den PDF-Templates.

**Weitere Dokumenttypen:** Es wurden im Repo nur zwei PDF-Blade-Views gefunden
(`backend/resources/views/pdf/invoice.blade.php`,
`backend/resources/views/pdf/anamnesis.blade.php`) sowie zwei
`Pdf::loadView(...)`-Aufrufe (`InvoiceController.php:236`,
`AnamnesisResponseController.php:211`). Keine weiteren PDF-Generatoren
(Mahnungen, Verträge) im Repo gefunden — die Vermutung des Users ("wahrscheinlich
auch auf anderen Dokumenten") ist damit bestätigt, aber der Umfang ist mit
genau diesen zwei Templates abschließend bekannt, kein "unbekannter Rest".

## Rückfragen an den User

Keine — Ursache und Umfang sind eindeutig aus dem Code belegt.

## Empfohlene nächste Aktion

`@architect` (Modus A) erstellt einen openspec-Change (Vorschlag: `fix-pdf-hardcoded-company-address`)
mit Tasks für `dev-php`:
- T01: In `pdf/invoice.blade.php` Kopf- und Fußzeile auf `Setting::get('company_name', ...)`,
  `company_street`, `company_zip`, `company_city`, `company_phone`, `company_email`,
  `company_tax_id` umstellen (analog zum bereits vorhandenen Bank-Block in derselben Datei).
- T02: Dieselbe Umstellung in `pdf/anamnesis.blade.php` (Kopf- und Fußzeile).
- Optional: Prüfen, ob ein gemeinsamer Blade-Partial/Include (`pdf/partials/company-header.blade.php`,
  `pdf/partials/company-footer.blade.php`) sinnvoll ist, um Duplikation zwischen den
  beiden PDF-Templates zu vermeiden (Architekt entscheidet im Design).

Kein Skeptiker-Overkill nötig, aber da der volle Workflow für "klein" ohnehin
Architekt → Entwickler → Reviewer+Tester → Abnahme vorsieht, reicht das aus;
kein User-Spec-Gate mit Skeptiker-Runde erforderlich (Pfad "klein", nicht "standard").

## Why

Rechnungs-PDF (`backend/resources/views/pdf/invoice.blade.php:259-260`),
Rechnungs-E-Mail (`backend/resources/views/emails/invoice-created.blade.php:98-104`)
und Zahlungserinnerung-E-Mail
(`backend/resources/views/emails/payment-reminder.blade.php:75,80`)
zeigen aktuell eine hartkodierte Platzhalter-IBAN/BIC
(`DE89 3704 0044 0532 0130 00` / `COBADEFFXXX`, klassische Musterbank-Testdaten)
statt der echten Kontodaten der Hundeschule. Es gibt derzeit **keinen**
Settings-Key für Bankverbindung — weder in `UpdateSettingsRequest.php:34-46`
noch in `SettingsSeeder.php:19-28` noch in `SettingsView.vue`. Kunden
erhalten dadurch auf jeder Rechnung **und jeder Zahlungserinnerung** falsche
Überweisungsdaten.

Die Zahlungserinnerung wird über `App\Mail\PaymentReminder::content()`
(`backend/app/Mail/PaymentReminder.php:54-64`) versendet und nutzt exakt
dasselbe `$settings`-Array-Pattern wie `InvoiceCreated::content()`
(`backend/app/Mail/InvoiceCreated.php:54-64`) — beide laden über
`Cache::remember('all_settings', ...)` `Setting::pluck('value', 'key')`
und übergeben das Ergebnis unverändert als `with: ['settings' => $settings]`
ans Template. Ursprünglich war dieses dritte Template nicht im Scope dieses
Change; nach Rückmeldung des Skeptikers (`verification.md`, Abschnitt
"Zusätzliche Befunde") hat der User entschieden, es mit aufzunehmen, da es
dieselbe hartkodierte Platzhalter-IBAN/BIC enthält wie die beiden bereits
erfassten Templates.

Zusätzlich fehlt ein fester, admin-konfigurierbarer Überweisungstext
("Bitte überweisen Sie den Betrag innerhalb der X Wochen …"), der
unabhängig vom individuellen `due_date` der jeweiligen Rechnung
(`StoreInvoiceRequest.php:91`) angezeigt werden soll.

Nicht Teil dieses Change (bewusst abgegrenzt, siehe Triage-Rückfrage 1):
Firmenname/Adresse/Steuernummer bleiben hartkodiert in
`pdf/invoice.blade.php:144-146,280-281` — separates Problem.

## What Changes

- Fünf neue Settings-Keys für Bankdaten und Zahlungsziel der Hundeschule
  (Gruppe `company`, analog zur bestehenden Gruppierungslogik in
  `SettingsController::determineTypeAndGroup()`):
  - `company_bank_account_holder` (Kontoinhaber)
  - `company_bank_name` (Bankname)
  - `company_bank_iban` (IBAN)
  - `company_bank_bic` (BIC)
  - `company_payment_term_weeks` (Standard-Zahlungsziel in Wochen, integer,
    unabhängig vom individuellen `due_date` einer Rechnung)
- Validierungsregeln für diese fünf Keys in `UpdateSettingsRequest`.
- Seeder-Defaults in `SettingsSeeder`.
- Neue Formularfelder im Settings-Formular (`SettingsView.vue`).
- Rechnungs-PDF (`pdf/invoice.blade.php`) und Rechnungs-E-Mail
  (`emails/invoice-created.blade.php`) ersetzen die hartkodierte
  Platzhalter-IBAN/BIC durch die echten Werte aus den Settings und zeigen
  zusätzlich zur bestehenden "Zahlungsziel: {Datum}"-Zeile den neuen
  Überweisungstext mit Kontoinhaber, Bankname, IBAN, BIC und der
  konfigurierten Wochenanzahl.
- Zahlungserinnerung-E-Mail (`emails/payment-reminder.blade.php`) ersetzt
  die hartkodierte Platzhalter-IBAN/BIC durch die echten Werte aus den
  Settings und ergänzt zusätzlich Kontoinhaber und Bankname für vollständige
  Kontodaten. **Ohne** die "innerhalb von X Wochen"-Formulierung aus
  Decision 7, da die Rechnung bei einer Zahlungserinnerung bereits fällig
  bzw. überfällig ist — der bestehende überfälligkeitsneutrale Einleitungssatz
  ("Bitte überweisen Sie den offenen Betrag unter Angabe der Rechnungsnummer
  auf folgendes Konto:", `payment-reminder.blade.php:71`) bleibt unverändert
  bestehen. Details siehe `design.md` Decision 8.
- **Explizit nicht geändert:** `InvoiceController::downloadPdf()` (PDF
  liest Settings bereits heute direkt per `\App\Models\Setting::get()` im
  Blade-Template, siehe `invoice.blade.php:128` — dasselbe Muster wird für
  die neuen Felder fortgesetzt), `App\Mail\InvoiceCreated` und
  `App\Mail\PaymentReminder` (übergeben bereits alle Settings als
  `$settings`-Array ans Template, siehe `InvoiceCreated.php:56-63` und
  `PaymentReminder.php:56-63` — die neuen Keys erscheinen dort automatisch
  ohne Codeänderung). Details siehe `design.md` Decision 4/5.
- **Kein Migrations-Task:** `settings.value` ist bereits eine generische
  `text`-Spalte (`2026_01_05_144724_create_settings_table.php:17`), neue
  Keys benötigen kein Schema-Update.

## Capabilities

### New Capabilities
- `invoice-bank-details`: Konfigurierbare Bankverbindung und
  Standard-Zahlungsziel der Hundeschule, dargestellt in Rechnungs-PDF,
  Rechnungs-E-Mail und Zahlungserinnerung-E-Mail anstelle hartkodierter
  Platzhalterdaten.

### Modified Capabilities
_Keine — es existiert noch keine Spec für Settings-Firmenfelder oder
Invoice-Dokumente; dieser Change führt eine neue Capability ein._

## Impact

**Backend:**
- `backend/app/Http/Requests/UpdateSettingsRequest.php` — 5 neue
  Validierungsregeln + Attribute-Labels
- `backend/database/seeders/SettingsSeeder.php` — 5 neue Seed-Einträge
- `backend/app/Http/Controllers/SettingsController.php` — explizite
  Typzuweisung `integer` für `company_payment_term_weeks` in
  `determineTypeAndGroup()` (analog zum bestehenden
  `company_small_business`-Sonderfall, Zeile 91)
- `backend/resources/views/pdf/invoice.blade.php` — Ersatz der
  hartkodierten IBAN/BIC-Zeilen (259-260) durch dynamische Werte +
  Überweisungstext
- `backend/resources/views/emails/invoice-created.blade.php` — Ersatz der
  hartkodierten IBAN/BIC-Zeilen (98-104) durch dynamische Werte +
  Überweisungstext
- `backend/resources/views/emails/payment-reminder.blade.php` — Ersatz der
  hartkodierten IBAN/BIC-Zeilen (75, 80) durch dynamische Werte aus
  `$settings`, ergänzt um Kontoinhaber/Bankname, ohne Wochen-Frist-Text
  (siehe `design.md` Decision 8)

**Frontend:**
- `frontend/src/views/SettingsView.vue` — 5 neue Formularfelder im
  Stammdaten-Abschnitt

**Nicht betroffen (geprüft, kein Änderungsbedarf):**
- `backend/app/Http/Resources/SettingsResource.php` (generisches
  Key/Value-Mapping, deckt neue Keys bereits ab)
- `frontend/src/api/settings.ts` (generischer `Record<string, any>`-Transport)
- `backend/app/Mail/InvoiceCreated.php` (siehe oben)
- `backend/app/Mail/PaymentReminder.php` (siehe oben — übergibt bereits
  `$settings` ans Template, nur das Blade-Template wird geändert)
- `backend/app/Http/Controllers/Api/InvoiceController.php` (siehe oben)
- `backend/app/Http/Controllers/Api/SettingsController.php` — **totes
  Duplikat**, nicht geroutet (`routes/api.php:27,206-207` verwendet
  `App\Http\Controllers\SettingsController` ohne `Api`-Namespace). Wird
  in diesem Change nicht angefasst, um keine Verwirrung über zwei aktive
  Controller zu erzeugen (bestehendes Duplikat ist ein separates
  Aufräum-Thema).

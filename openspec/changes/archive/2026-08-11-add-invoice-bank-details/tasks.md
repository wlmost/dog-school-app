# Tasks für add-invoice-bank-details

## T01: Settings-Backend — neue Bankdaten- und Zahlungsziel-Felder

- **Agent:** dev-php
- **Dateien:**
  - `backend/app/Http/Requests/UpdateSettingsRequest.php`
  - `backend/database/seeders/SettingsSeeder.php`
  - `backend/app/Http/Controllers/SettingsController.php` (der geroutete
    Controller ohne `Api`-Namespace, siehe `routes/api.php:27,206-207` —
    **nicht** `backend/app/Http/Controllers/Api/SettingsController.php`,
    das ist ein totes Duplikat und bleibt unangetastet)
- **Abhängigkeiten:** keine
- **Beschreibung:**
  In `UpdateSettingsRequest::rules()` (nach der bestehenden
  `company_registration_number`-Regel, vor `company_small_business`)
  fünf neue Regeln ergänzen:
  ```php
  'company_bank_account_holder' => ['sometimes', 'nullable', 'string', 'max:255'],
  'company_bank_name' => ['sometimes', 'nullable', 'string', 'max:255'],
  'company_bank_iban' => ['sometimes', 'nullable', 'string', 'max:34', 'regex:/^[A-Z]{2}[0-9]{2}[A-Z0-9]{1,30}$/'],
  'company_bank_bic' => ['sometimes', 'nullable', 'string', 'max:11', 'regex:/^[A-Z]{6}[A-Z0-9]{2}([A-Z0-9]{3})?$/'],
  'company_payment_term_weeks' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:52'],
  ```
  IBAN-/BIC-Regex 1:1 aus `UpdateCustomerRequest.php:61-62` übernommen
  (siehe `design.md` Decision 2). Passende Einträge in `attributes()`
  ergänzen (`'company_bank_account_holder' => 'Kontoinhaber'`,
  `'company_bank_name' => 'Bankname'`, `'company_bank_iban' => 'IBAN'`,
  `'company_bank_bic' => 'BIC'`, `'company_payment_term_weeks' =>
  'Zahlungsziel (Wochen)'`).

  In `SettingsSeeder::$companySettings` fünf neue Zeilen ergänzen (Gruppe
  `company`, Muster wie bestehende Einträge Zeile 19-28):
  - `company_bank_account_holder` (type `string`, Default z. B.
    `'Hundeschule Beispiel'`)
  - `company_bank_name` (type `string`, Default z. B. `'Musterbank'`)
  - `company_bank_iban` (type `string`, Default z. B.
    `'DE89370400440532013000'` — **ohne** Leerzeichen, damit der Seed-Wert
    die eigene Regex besteht)
  - `company_bank_bic` (type `string`, Default z. B. `'COBADEFFXXX'`)
  - `company_payment_term_weeks` (type `integer`, Default `'2'`)

  In `SettingsController::determineTypeAndGroup()` (Zeile 87-108) den
  `match (true)`-Block für `$type` um einen expliziten Fall vor dem
  generischen `is_int($value)`-Check ergänzen:
  ```php
  $key === 'company_payment_term_weeks' => 'integer',
  ```
  Begründung siehe `design.md` Decision 3 (FormData liefert immer
  Strings, `is_int()` greift sonst nie). Die Gruppierung
  (`str_starts_with($key, 'company_') => 'company'`) erfordert **keine**
  Änderung, da alle neuen Keys mit `company_` beginnen.
- **Akzeptanzkriterien:**
  - [x] `PUT /api/v1/settings` akzeptiert alle 5 neuen Keys einzeln und
    zusammen mit HTTP 200/keine Validierungsfehler, wenn gültige Werte
    gesendet werden.
  - [x] Ungültige IBAN (z. B. Kleinbuchstaben, Leerzeichen, falsches
    Präfix) liefert HTTP 422 mit Validierungsfehler für
    `company_bank_iban`.
  - [x] Ungültige BIC liefert HTTP 422 mit Validierungsfehler für
    `company_bank_bic`.
  - [x] `company_payment_term_weeks` mit Wert `0` oder `53` liefert HTTP
    422; Werte `1`–`52` werden akzeptiert.
  - [x] Alle 5 Felder sind optional — ein `PUT`-Request ohne diese Felder
    liefert weiterhin HTTP 200 (keine Pflichtfelder).
  - [x] Nach dem Speichern von `company_payment_term_weeks` liefert
    `Setting::get('company_payment_term_weeks')` einen PHP-`int`, kein
    `string` (Type wird korrekt als `integer` persistiert).
  - [x] Ein frischer `php artisan db:seed --class=SettingsSeeder`-Lauf
    legt alle 5 neuen Keys mit den definierten Defaults an.
  - [x] `composer qa` (lint, stan, compat-check, test) läuft grün.

---

## T02: Settings-Frontend — neue Formularfelder

- **Agent:** dev-typescript
- **Dateien:**
  - `frontend/src/views/SettingsView.vue`
- **Abhängigkeiten:** T01 (Soft-Dependency — Datenvertrag, also die 5
  Settings-Keys und ihre Bedeutung, ist bereits in `design.md` Decision 1
  fixiert, daher kann die Implementierung parallel zu T01 starten; vor
  Review/Test wird aber ein laufender Backend-Stand aus T01 zur
  Integrationsprüfung benötigt)
- **Beschreibung:**
  In `formData` (Zeile 526-561) nach `company_registration_number` und
  vor `company_small_business` fünf neue Properties ergänzen:
  ```ts
  company_bank_account_holder: '',
  company_bank_name: '',
  company_bank_iban: '',
  company_bank_bic: '',
  company_payment_term_weeks: 2 as number | string,
  ```
  Im Template, im "Stammdaten"-Card (Zeile 23-218), nach dem bestehenden
  `company_registration_number`-Feld (Zeile 146-157) einen neuen
  Unterabschnitt "Bankverbindung" mit fünf Eingabefeldern ergänzen,
  analog zum bestehenden Markup-Muster (Label + `input`, Tailwind-Klassen
  wie bei den übrigen Feldern):
  - Kontoinhaber (`type="text"`, `v-model="formData.company_bank_account_holder"`)
  - Bankname (`type="text"`, `v-model="formData.company_bank_name"`)
  - IBAN (`type="text"`, `v-model="formData.company_bank_iban"`)
  - BIC (`type="text"`, `v-model="formData.company_bank_bic"`)
  - Zahlungsziel in Wochen (`type="number"`, `min="1"`, `max="52"`,
    `v-model.number="formData.company_payment_term_weeks"`, analog zum
    bestehenden `smtp_port`-Feld Zeile 290-303)
  `loadSettings()`/`saveSettings()` müssen **nicht** angepasst werden —
  beide iterieren bereits generisch über alle Keys in `formData`
  (Zeilen 589-606, 627-639), neue Felder werden automatisch mit
  geladen/gespeichert, solange sie in `formData` existieren.
- **Akzeptanzkriterien:**
  - [x] Alle 5 neuen Felder sind im Stammdaten-Formular sichtbar,
    beschriftet und mit `formData` verbunden.
  - [x] Nach `loadSettings()` werden vom Backend gelieferte Werte für die
    5 neuen Keys korrekt im Formular angezeigt (inkl. Fall: Key noch
    nicht in der DB vorhanden → Feld bleibt beim Default aus `formData`).
  - [x] `saveSettings()` sendet alle 5 neuen Felder im `FormData`-Payload.
  - [x] `npm run lint`, `npm run test`, `npm run build` laufen ohne
    Fehler/Warnings durch.

---

## T03: Rechnungs-PDF und Rechnungs-E-Mail — Kontodaten + Überweisungstext aus Settings

- **Agent:** dev-php
- **Dateien:**
  - `backend/resources/views/pdf/invoice.blade.php`
  - `backend/resources/views/emails/invoice-created.blade.php`
- **Abhängigkeiten:** T01 (Settings-Keys müssen existieren, damit die
  Werte im Dokument erscheinen; ohne T01 liefert `Setting::get()` nur die
  im Template gesetzten Fallback-Defaults — kein Fehler, aber fachlich
  unvollständig zum Testen)
- **Beschreibung:**
  **PDF (`pdf/invoice.blade.php`):** Im bestehenden `@php`-Block (Zeile
  127-133, neben `$isSmallBusiness`) zusätzliche Variablen für die neuen
  Settings laden, z. B.:
  ```php
  $bankAccountHolder = \App\Models\Setting::get('company_bank_account_holder', '');
  $bankName = \App\Models\Setting::get('company_bank_name', '');
  $bankIban = \App\Models\Setting::get('company_bank_iban', '');
  $bankBic = \App\Models\Setting::get('company_bank_bic', '');
  $paymentTermWeeks = \App\Models\Setting::get('company_payment_term_weeks', 2);
  ```
  (direkter `Setting::get()`-Aufruf, kein neues `$settings`-Array — siehe
  `design.md` Decision 4; `InvoiceController::downloadPdf()` bleibt
  unverändert). Im `.payment-box`-Block (Zeile 255-262) die bestehenden
  Zeilen
  ```
  <p><strong>IBAN:</strong> DE89 3704 0044 0532 0130 00</p>
  <p><strong>BIC:</strong> COBADEFFXXX</p>
  ```
  (Zeile 259-260) entfernen und durch den neuen Überweisungstext
  ersetzen, **nach** der bestehenden `Zahlungsziel:`-Zeile (258) und
  **vor** der bestehenden `Verwendungszweck:`-Zeile (261):
  ```
  <p>Bitte überweisen Sie den Betrag innerhalb von {{ $paymentTermWeeks }} Wochen auf folgendes Konto:</p>
  <p>
      <strong>Kontoinhaber:</strong> {{ $bankAccountHolder }}<br>
      <strong>Bank:</strong> {{ $bankName }}<br>
      <strong>IBAN:</strong> {{ $bankIban }}<br>
      <strong>BIC:</strong> {{ $bankBic }}
  </p>
  ```
  (exakter Wortlaut/Struktur siehe `design.md` Decision 7).

  **E-Mail (`emails/invoice-created.blade.php`):** Das Template erhält
  `$settings` bereits vollständig über `App\Mail\InvoiceCreated::content()`
  (keine Mailable-Änderung nötig, siehe `design.md` Decision 5). Im
  `.payment-info`-Block (Zeile 89-111) die bestehenden `info-row`-Paare
  für IBAN (Zeile 97-100) und BIC (Zeile 102-105) entfernen und durch den
  neuen Überweisungstext ersetzen, **nach** der bestehenden
  `Zahlungsziel:`-Zeile (92-95) und **vor** der bestehenden
  `Verwendungszweck:`-Zeile (107-110):
  ```blade
  <p>Bitte überweisen Sie den Betrag innerhalb von {{ $settings['company_payment_term_weeks'] ?? 2 }} Wochen auf folgendes Konto:</p>
  <div class="info-row">
      <span class="info-label">Kontoinhaber:</span>
      {{ $settings['company_bank_account_holder'] ?? '' }}
  </div>
  <div class="info-row">
      <span class="info-label">Bank:</span>
      {{ $settings['company_bank_name'] ?? '' }}
  </div>
  <div class="info-row">
      <span class="info-label">IBAN:</span>
      {{ $settings['company_bank_iban'] ?? '' }}
  </div>
  <div class="info-row">
      <span class="info-label">BIC:</span>
      {{ $settings['company_bank_bic'] ?? '' }}
  </div>
  ```
  Genaue HTML-Struktur (z. B. ob als eigene `info-row`s oder als
  zusammengefasster Absatz) darf sich am bestehenden Markup-Stil der
  Datei orientieren, solange der Wortlaut aus `design.md` Decision 7 und
  alle vier Werte (Kontoinhaber, Bankname, IBAN, BIC) sowie die
  Wochenanzahl enthalten sind.
- **Akzeptanzkriterien:**
  - [x] `GET /api/v1/invoices/{id}/pdf` (bzw. der entsprechende
    `downloadPdf`-Aufruf) erzeugt ein PDF ohne die alte Platzhalter-IBAN
    `DE89 3704 0044 0532 0130 00`/BIC `COBADEFFXXX` als hartkodierten
    String im Blade-Quelltext.
  - [x] Werden in den Settings reale Bankdaten gepflegt (z. B. via T01),
    erscheinen genau diese Werte im PDF.
  - [x] Das PDF enthält weiterhin die unveränderte
    `Zahlungsziel: {due_date}`-Zeile **und zusätzlich** den neuen
    Überweisungstext mit der konfigurierten Wochenanzahl.
  - [x] Fehlen die Settings-Werte (nicht gesetzt), rendert das PDF ohne
    PHP-Fehler/-Warnung (leere Werte statt Exception).
  - [x] Die Rechnungs-E-Mail (`InvoiceCreated`) enthält dieselben vier
    Kontodaten-Werte und denselben Überweisungstext wie das PDF, nicht
    mehr die hartkodierte Platzhalter-IBAN/BIC.
  - [x] `company_name`, `company_street`, `company_city`,
    `company_tax_id` etc. bleiben in beiden Dateien unverändert
    hartkodiert (Non-Goal, nicht Teil dieses Change — keine Regression
    prüfen, aber auch keine versehentliche Änderung vornehmen).
  - [x] `composer qa` läuft grün.

---

## T04: Zahlungserinnerung-E-Mail — Kontodaten aus Settings statt hartkodierter Platzhalter

- **Agent:** dev-php
- **Dateien:**
  - `backend/resources/views/emails/payment-reminder.blade.php`
- **Abhängigkeiten:** T01 (Settings-Keys müssen existieren, damit die
  Werte im Dokument erscheinen; ohne T01 liefert `$settings[...]` nur
  `null`/leeren String über den `??`-Fallback — kein Fehler, aber fachlich
  unvollständig zum Testen)
- **Beschreibung:**
  Scope-Erweiterung nach Skeptiker-Befund, siehe `verification.md`
  ("Zusätzliche Befunde") und `design.md` Decision 8. Das Template erhält
  `$settings` bereits vollständig über `App\Mail\PaymentReminder::content()`
  (`backend/app/Mail/PaymentReminder.php:54-64`, identisches Muster wie
  `InvoiceCreated::content()`) — **keine** Mailable-Änderung nötig.

  Im `.payment-info`-Block (Zeile 68-87) den bestehenden Einleitungssatz
  (Zeile 71) **unverändert** lassen (kein Wochen-Frist-Text — siehe
  Begründung in `design.md` Decision 8: die Rechnung ist bei einer
  Zahlungserinnerung bereits fällig/überfällig). Vor der bestehenden
  IBAN-`info-row` (Zeile 73-76) zwei neue `info-row`-Blöcke für
  Kontoinhaber und Bankname einfügen, danach die hartkodierten Werte in
  den bestehenden IBAN-/BIC-`info-row`-Blöcken (Zeile 75 bzw. 80) durch
  die Settings-Werte ersetzen:
  ```blade
  <div class="info-row">
      <span class="info-label">Kontoinhaber:</span>
      {{ $settings['company_bank_account_holder'] ?? '' }}
  </div>

  <div class="info-row">
      <span class="info-label">Bank:</span>
      {{ $settings['company_bank_name'] ?? '' }}
  </div>

  <div class="info-row">
      <span class="info-label">IBAN:</span>
      {{ $settings['company_bank_iban'] ?? '' }}
  </div>

  <div class="info-row">
      <span class="info-label">BIC:</span>
      {{ $settings['company_bank_bic'] ?? '' }}
  </div>
  ```
  Die bestehende `Verwendungszweck`-`info-row` (Zeile 83-86,
  `{{ $invoice->invoice_number }}`) bleibt unverändert — sie ist bereits
  dynamisch und nicht Teil der hartkodierten Platzhalterdaten. Der
  restliche Aufbau des Templates (Überfälligkeits-Hinweis Zeile 28-37,
  Betrag-Box Zeile 63-66, Signatur Zeile 89-94) bleibt unangetastet.
- **Akzeptanzkriterien:**
  - [x] Die Zahlungserinnerung-E-Mail (`PaymentReminder`) enthält nach dem
    Versand nicht mehr die hartkodierte Platzhalter-IBAN
    `DE89 3704 0044 0532 0130 00` oder BIC `COBADEFFXXX` als hartkodierten
    String im Blade-Quelltext.
  - [x] Werden in den Settings reale Bankdaten gepflegt (z. B. via T01),
    erscheinen Kontoinhaber, Bankname, IBAN und BIC korrekt in der
    Zahlungserinnerung-E-Mail.
  - [x] Der bestehende Einleitungssatz ("Bitte überweisen Sie den offenen
    Betrag unter Angabe der Rechnungsnummer auf folgendes Konto:") bleibt
    wortwörtlich unverändert — **kein** neuer Wochen-Frist-Text wird
    eingefügt.
  - [x] Die bestehende `Verwendungszweck`-Zeile mit der Rechnungsnummer
    bleibt unverändert funktional.
  - [x] Fehlen die Settings-Werte (nicht gesetzt), rendert die E-Mail ohne
    PHP-Fehler/-Warnung (leere Werte statt Exception).
  - [x] Die bestehende Überfälligkeits-/Fälligkeitslogik
    (`$invoice->isOverdue()`, Zeile 31-36) bleibt unverändert.
  - [x] `composer qa` läuft grün.

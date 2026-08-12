# Tasks für fix-pdf-hardcoded-company-address

## T01: Rechnungs-PDF — Firmenkopf/-fuß aus Settings, gemeinsame Partials anlegen

- **Agent:** dev-php
- **Dateien:**
  - `backend/resources/views/pdf/invoice.blade.php`
  - `backend/resources/views/pdf/partials/company-info.blade.php` (neu)
  - `backend/resources/views/pdf/partials/company-footer-lines.blade.php`
    (neu)
- **Abhängigkeiten:** keine
- **Beschreibung:**
  Zwei neue Partials anlegen, die ihre Werte selbst per
  `\App\Models\Setting::get(...)` laden (siehe `design.md` Decision 1–3
  für Muster und Fallback-Werte):

  `pdf/partials/company-info.blade.php` (ersetzt den Inhalt von
  `invoice.blade.php:149-151`):
  ```blade
  @php
      $companyName = \App\Models\Setting::get('company_name', 'Hundeschule');
      $companyStreet = \App\Models\Setting::get('company_street', '');
      $companyZip = \App\Models\Setting::get('company_zip', '');
      $companyCity = \App\Models\Setting::get('company_city', '');
      $companyPhone = \App\Models\Setting::get('company_phone', '');
      $companyEmail = \App\Models\Setting::get('company_email', '');
  @endphp
  <h1>{{ $companyName }}</h1>
  <p>{{ $companyStreet }} • {{ $companyZip }} {{ $companyCity }}</p>
  <p>Tel: {{ $companyPhone }} • E-Mail: {{ $companyEmail }}</p>
  ```

  `pdf/partials/company-footer-lines.blade.php` (ersetzt den Inhalt von
  `invoice.blade.php:289-291`):
  ```blade
  @php
      $companyName = \App\Models\Setting::get('company_name', 'Hundeschule');
      $companyStreet = \App\Models\Setting::get('company_street', '');
      $companyZip = \App\Models\Setting::get('company_zip', '');
      $companyCity = \App\Models\Setting::get('company_city', '');
      $companyTaxId = \App\Models\Setting::get('company_tax_id', '');
  @endphp
  <p>{{ $companyName }} • {{ $companyStreet }} • {{ $companyZip }} {{ $companyCity }}</p>
  <p>USt-IdNr: {{ $companyTaxId }}</p>
  ```

  In `invoice.blade.php` den Kopf-Textblock (Zeile 148-152, `<td
  class="company-info" ...>`) auf `@include('pdf.partials.company-info')`
  umstellen (die umgebende `<td>` mit ihren Style-Attributen bleibt
  bestehen, nur der `<h1>`/`<p>`/`<p>`-Inhalt wird ersetzt). Die
  Fußzeile (Zeile 288-292, `<div class="footer">`) auf
  `@include('pdf.partials.company-footer-lines')` umstellen (die
  umgebende `<div class="footer">` bleibt bestehen).

  Der bestehende Bankdaten-`@php`-Block (Zeile 127-138) bleibt
  unverändert; die neuen Partials sind bewusst getrennte Dateien mit
  eigenen `@php`-Blöcken, kein Vermischen mit dem Bankdaten-Block (Single
  Responsibility, siehe `design.md` Decision 2).
- **Akzeptanzkriterien:**
  - [x] `view('pdf.invoice', ['invoice' => $invoice])->render()` enthält
    nach dem Setzen von `company_name`, `company_street`, `company_zip`,
    `company_city`, `company_phone`, `company_email`, `company_tax_id`
    über `Setting::set(...)` genau diese Werte im Kopf **und** im Fuß des
    gerenderten HTML.
  - [x] Der gerenderte HTML-Quelltext enthält nicht mehr die Strings
    `Hundeschule Max Mustermann`, `Musterstraße 123`, `12345 Musterstadt`,
    `hundeschule-mustermann.de`, `DE123456789` als hartkodierten Text im
    Blade-Quellcode.
  - [x] Ohne gesetzte Settings (leere DB) rendert das PDF ohne
    PHP-Fehler/-Warnung; `company_name` zeigt `Hundeschule`, alle anderen
    Felder erscheinen leer statt eines Fake-Werts.
  - [x] Der bestehende Bankdaten-Block (`.payment-box`,
    `company_bank_*`-Werte) ist unverändert funktionsfähig (keine
    Regression).
  - [x] `composer qa` läuft grün (mit Ausnahme des in T03 zu
    korrigierenden, bereits vor dieser Task bekannten Tests — siehe
    Abhängigkeitshinweis dort).

---

## T02: Anamnese-PDF — Firmenkopf/-fuß aus Settings (Partials wiederverwenden)

- **Agent:** dev-php
- **Dateien:**
  - `backend/resources/views/pdf/anamnesis.blade.php`
- **Abhängigkeiten:** T01 (die beiden Partials müssen existieren, bevor
  sie hier eingebunden werden können)
- **Beschreibung:**
  In `anamnesis.blade.php` den Kopf-Textblock (Zeile 124-129, `<div
  class="company-info">...</div>`) so umstellen, dass der Inhalt aus
  `@include('pdf.partials.company-info')` kommt (die umgebende `<div
  class="company-info">` bleibt bestehen, da das Anamnese-PDF anders als
  das Rechnungs-PDF kein Logo/keine Tabelle hat):
  ```blade
  <!-- Company Header -->
  <div class="company-info">
      @include('pdf.partials.company-info')
  </div>
  ```

  Die Fußzeile (Zeile 270-275, `<div class="footer">`) so umstellen, dass
  die beiden gemeinsamen Zeilen aus
  `@include('pdf.partials.company-footer-lines')` kommen, die
  anamnese-spezifische dritte Zeile ("Erstellt am: ...") bleibt
  unverändert **nach** dem Include bestehen:
  ```blade
  <!-- Footer -->
  <div class="footer">
      @include('pdf.partials.company-footer-lines')
      <p style="margin-top: 10px;">Erstellt am: {{ now()->format('d.m.Y H:i') }} Uhr</p>
  </div>
  ```
- **Akzeptanzkriterien:**
  - [x] `view('pdf.anamnesis', ['response' => $response])->render()`
    enthält nach dem Setzen der Company-Settings genau diese Werte im
    Kopf **und** im Fuß des gerenderten HTML.
  - [x] Der gerenderte HTML-Quelltext enthält nicht mehr die Strings
    `Hundeschule Max Mustermann`, `Musterstraße 123`, `12345 Musterstadt`,
    `hundeschule-mustermann.de`, `DE123456789` als hartkodierten Text im
    Blade-Quellcode.
  - [x] Die bestehende "Erstellt am: {{ now()->format('d.m.Y H:i') }}
    Uhr"-Zeile erscheint weiterhin unverändert im Fuß, nach den beiden
    Firmenzeilen.
  - [x] Ohne gesetzte Settings (leere DB) rendert das PDF ohne
    PHP-Fehler/-Warnung; `company_name` zeigt `Hundeschule`, alle anderen
    Felder erscheinen leer statt eines Fake-Werts.
  - [x] `composer qa` läuft grün.

---

## T03: Veralteten Test korrigieren, der die alte Hartkodierung als Soll-Zustand voraussetzt

- **Agent:** dev-php
- **Dateien:**
  - `backend/tests/Feature/Pdf/InvoiceBankDetailsPdfTest.php`
- **Abhängigkeiten:** T01 (Testinhalt bezieht sich auf das durch T01
  geänderte Kopf-/Fuß-Rendering von `pdf/invoice.blade.php`)
- **Beschreibung:**
  Der Test `it('lässt company_name company_street company_city und
  company_tax_id unverändert hartkodiert', ...)` (Zeile 65-72) behauptet
  aktuell explizit, dass die alten Platzhalterwerte im PDF stehen
  bleiben — das war zum Zeitpunkt von `add-invoice-bank-details` korrekt
  (dort bewusst als Non-Goal ausgeklammert), widerspricht nach T01/T02
  aber der neuen, gewünschten Anzeige. Test umbenennen und auf
  settings-basiertes Verhalten umstellen, im selben Stil wie der
  bestehende Bankdaten-Test in derselben Datei (Zeile 35-49, `Setting::set`
  + `expect($html)->toContain(...)`):
  ```php
  it('zeigt firmenname firmenadresse und ust-idnr aus den einstellungen statt hartkodierter platzhalterwerte', function () {
      Setting::set('company_name', 'Hundeschule Testfall', 'string', group: 'company');
      Setting::set('company_street', 'Teststraße 42', 'string', group: 'company');
      Setting::set('company_zip', '99999', 'string', group: 'company');
      Setting::set('company_city', 'Teststadt', 'string', group: 'company');
      Setting::set('company_tax_id', 'DE999999999', 'string', group: 'company');

      $html = view('pdf.invoice', ['invoice' => $this->invoice])->render();

      expect($html)->toContain('Hundeschule Testfall');
      expect($html)->toContain('Teststraße 42');
      expect($html)->toContain('99999 Teststadt');
      expect($html)->toContain('USt-IdNr: DE999999999');
      expect($html)->not->toContain('Hundeschule Max Mustermann');
      expect($html)->not->toContain('Musterstraße 123');
      expect($html)->not->toContain('DE123456789');
  });
  ```
  Der `use App\Models\Setting;`-Import (Zeile 8) ist bereits vorhanden,
  keine weitere Anpassung der `beforeEach`/Imports nötig.
- **Akzeptanzkriterien:**
  - [x] Der umbenannte Test prüft, dass gesetzte
    `company_name`/`company_street`/`company_zip`/`company_city`/`company_tax_id`-Werte
    im gerenderten Rechnungs-PDF erscheinen.
  - [x] Der Test prüft zusätzlich, dass die alten Platzhalterwerte
    (`Hundeschule Max Mustermann`, `Musterstraße 123`, `DE123456789`)
    **nicht** mehr im gerenderten HTML enthalten sind.
  - [x] Alle übrigen Tests derselben Datei (Bankdaten-Fokus, Zeile 1-63)
    bleiben unverändert und weiterhin grün.
  - [x] `backend/tests/Unit/InvoiceBankDetailsBladeSourceTest.php` bleibt
    unverändert und weiterhin grün (reine IBAN/BIC-Prüfung, nicht
    betroffen).
  - [x] `composer qa` läuft vollständig grün (lint, stan, compat-check,
    pest — keine bekannten roten Tests mehr aus diesem Change).

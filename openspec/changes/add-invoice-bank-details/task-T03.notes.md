# Task T03 — Rechnungs-PDF und Rechnungs-E-Mail: Kontodaten + Überweisungstext aus Settings

## Status
Abgeschlossen.

## Geänderte/neue Dateien

- `backend/resources/views/pdf/invoice.blade.php`
  - Im bestehenden `@php`-Block (neben `$isSmallBusiness`) fünf neue
    Variablen ergänzt: `$bankAccountHolder`, `$bankName`, `$bankIban`,
    `$bankBic`, `$paymentTermWeeks` — jeweils via direktem
    `\App\Models\Setting::get($key, $default)`-Aufruf, konsistent zum
    bestehenden Muster für `$isSmallBusiness` (siehe `design.md`
    Decision 4). `InvoiceController::downloadPdf()` wurde **nicht**
    angefasst.
  - Im `.payment-box`-Block die beiden hartkodierten Zeilen
    `<p><strong>IBAN:</strong> DE89 3704 0044 0532 0130 00</p>` /
    `<p><strong>BIC:</strong> COBADEFFXXX</p>` entfernt und durch den in
    `design.md` Decision 7 / `tasks.md` T03 vorgegebenen Überweisungstext
    ersetzt (Einleitungssatz mit `{{ $paymentTermWeeks }}`, gefolgt von
    Kontoinhaber/Bank/IBAN/BIC als `<strong>Label:</strong> Wert`-Zeilen).
    Position: nach der bestehenden `Zahlungsziel:`-Zeile, vor der
    bestehenden `Verwendungszweck:`-Zeile — beide unverändert.
- `backend/resources/views/emails/invoice-created.blade.php`
  - Im `.payment-info`-Block die beiden `info-row`-Blöcke für IBAN und
    BIC entfernt und durch den Überweisungstext ersetzt (Einleitungssatz
    mit `{{ $settings['company_payment_term_weeks'] ?? 2 }}`, gefolgt von
    vier neuen `info-row`-Blöcken für Kontoinhaber/Bank/IBAN/BIC, jeweils
    mit `?? ''`-Fallback). Position: nach der bestehenden
    `Zahlungsziel:`-Zeile, vor der bestehenden `Verwendungszweck:`-Zeile
    — beide unverändert. `App\Mail\InvoiceCreated::content()` wurde
    **nicht** angefasst — `$settings` enthält bereits alle Setting-Keys
    (siehe `design.md` Decision 5, verifiziert in
    `backend/app/Mail/InvoiceCreated.php:56-64`).
- `backend/tests/Feature/Pdf/InvoiceBankDetailsPdfTest.php` (neu)
  - Group `pdf`, `invoice`. Rendert `pdf.invoice` direkt via
    `view(...)->render()` (statt über den HTTP-Download-Roundtrip, da
    DomPDF-Binärausgabe komprimierte Textstreams enthalten kann und daher
    für String-Assertions ungeeignet ist). Deckt ab: Rendering ohne
    PHP-Fehler bei fehlenden Settings (leere Werte, Default-Zahlungsziel
    2 Wochen), korrekte Anzeige konfigurierter Bankdaten + Wochenanzahl,
    Koexistenz von `Zahlungsziel:`-Zeile und neuem Überweisungstext,
    Abwesenheit der alten hartkodierten Platzhalter-IBAN/BIC,
    Unverändertheit von `company_name`/`company_street`/`company_city`/
    `company_tax_id` (Non-Goal-Regression-Check).
- `backend/tests/Feature/InvoiceCreatedMailBankDetailsTest.php` (neu)
  - Group `feature`, `invoice`. Nutzt Laravels
    `Mailable::assertSeeInHtml()`/`assertDontSeeInHtml()` direkt auf einer
    neu instanziierten `InvoiceCreated`-Mailable (kein `Mail::fake()`
    nötig, da nicht der Versand, sondern der gerenderte Inhalt geprüft
    wird). Deckt dieselben Fälle wie der PDF-Test ab, für die E-Mail.
- `backend/tests/Unit/InvoiceBankDetailsBladeSourceTest.php` (neu)
  - Group `unit`, `invoice`. Reine Dateisystem-Prüfung der beiden
    `.blade.php`-Quelltexte (ohne Laravel-Bootstrap, analog zum Muster in
    `tests/Unit/Deployment/HtaccessTemplatesTest.php`), um das
    Akzeptanzkriterium "kein hartkodierter Platzhalter-String im
    Blade-Quelltext" wörtlich (nicht nur über den gerenderten Output) zu
    prüfen, plus einen Beleg, dass beide Templates die neuen
    Settings-Keys tatsächlich referenzieren.

**Nicht angefasst:** `company_name`, `company_street`, `company_city`,
`company_tax_id` in beiden Dateien (Non-Goal), `InvoiceController.php`,
`InvoiceCreated.php` (Mailable), `backend/resources/views/emails/payment-reminder.blade.php`
(gehört zu T04, parallel von einem anderen Agenten bearbeitet).

## QA-Ergebnisse (Docker-Umgebung, wie in CLAUDE.md Abschnitt 7.1 gefordert)

```bash
docker compose exec php composer lint            # PASS (297 files)
docker compose exec php composer stan            # [OK] No errors (202/202)
docker compose exec php composer compat-check    # keine Ausgabe = keine Verstöße
docker compose exec php composer test            # 757 passed, 1 failed (siehe unten)
```

Gezielte Nachläufe der neuen/betroffenen Dateien, alle grün:

```bash
docker compose exec php vendor/bin/pest --no-coverage \
  tests/Feature/Pdf/InvoiceBankDetailsPdfTest.php \
  tests/Feature/InvoiceCreatedMailBankDetailsTest.php \
  tests/Unit/InvoiceBankDetailsBladeSourceTest.php
# 13 passed (40 assertions)

docker compose exec php vendor/bin/pest --no-coverage \
  tests/Feature/InvoicePdfTest.php tests/Feature/EmailNotificationTest.php
# 34 passed (98 assertions) — keine Regression an bestehenden Invoice-PDF-/Mail-Tests
```

### Unabhängiger, vorbestehender Test-Fehler (nicht T03)

`composer test` (voller Lauf) meldet einen Fehlschlag in
`tests/Feature/Api/CustomerApiTest.php:82`
(`can filter customers with search term` erwartet 1 Treffer für
`search=John`, bekommt 2). Isoliert ausgeführt
(`vendor/bin/pest tests/Feature/Api/CustomerApiTest.php`) läuft dieselbe
Datei vollständig grün (27 passed). Ursache ist damit eine
Test-Reihenfolge-/Faker-Kollision (vermutlich ein zufällig generierter
Vorname "John" aus einer Factory in einer anderen, vorher laufenden
Test-Datei), unabhängig von den hier geänderten Dateien (`git status`
zeigt keine Änderung an `CustomerApiTest.php`, `Customer`-Factory oder
`CustomerController`). Nicht behoben, da außerhalb des Scopes von T03.

## Akzeptanzkriterien — Abgleich

- [x] PDF ohne alte Platzhalter-IBAN/BIC als hartkodierten String im
  Blade-Quelltext (Unit-Test + Content-Test).
- [x] Reale Bankdaten aus den Settings erscheinen im PDF.
- [x] `Zahlungsziel:`-Zeile bleibt unverändert bestehen, zusätzlich der
  neue Überweisungstext mit konfigurierter Wochenanzahl.
- [x] Fehlende Settings-Werte → Rendering ohne PHP-Fehler/-Warnung (leere
  Werte statt Exception), verifiziert per Test ohne vorab gesetzte
  Settings.
- [x] Rechnungs-E-Mail enthält dieselben vier Kontodaten-Werte und
  denselben Überweisungstext wie das PDF, nicht mehr die alte
  Platzhalter-IBAN/BIC.
- [x] `company_name`/`company_street`/`company_city`/`company_tax_id`
  bleiben in beiden Dateien unverändert hartkodiert.
- [x] `composer qa` läuft grün (bis auf den o. g., nachweislich
  unabhängigen `CustomerApiTest`-Befund).

## Annahmen

- Für die PDF-Content-Prüfung wurde bewusst `view('pdf.invoice', [...])
  ->render()` statt eines echten `Pdf::loadView(...)->download(...)`-
  Roundtrips verwendet, da DomPDF die resultierenden PDF-Textstreams
  standardmäßig komprimiert (FlateDecode) und String-Assertions auf dem
  rohen PDF-Binärinhalt daher unzuverlässig wären. Die bestehenden
  HTTP-Tests in `InvoicePdfTest.php` (Statuscode, Content-Type,
  Content-Disposition, "nicht leer") bleiben unverändert als
  Ende-zu-Ende-Absicherung des tatsächlichen PDF-Downloads bestehen.
- `Setting::set()` wurde in den neuen Tests mit benanntem Parameter
  `group: 'company'` aufgerufen, um den `$description`-Parameter (Default
  `null`) nicht explizit angeben zu müssen — inhaltlich identisch zum
  vom Seeder verwendeten Muster.

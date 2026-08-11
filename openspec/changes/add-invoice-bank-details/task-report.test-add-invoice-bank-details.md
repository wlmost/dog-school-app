# Test-Report: add-invoice-bank-details (T01–T04)

**Status:** alle-gruen

## Hinzugefügte / geänderte Tests

- `backend/tests/Feature/Api/SettingsBankDetailsApiTest.php` (bereits vorhanden, 17 Tests) —
  keine Änderung nötig, deckt alle T01-Akzeptanzkriterien lückenlos ab.
- `backend/tests/Feature/Pdf/InvoiceBankDetailsPdfTest.php` (bereits vorhanden, 5 Tests) —
  keine Änderung nötig.
- `backend/tests/Feature/InvoiceCreatedMailBankDetailsTest.php` (bereits vorhanden, 4 Tests) —
  keine Änderung nötig.
- `backend/tests/Unit/InvoiceBankDetailsBladeSourceTest.php` (bereits vorhanden, 4 Tests) —
  keine Änderung nötig.
- `backend/tests/Feature/PaymentReminderEmailTest.php`: **2 neue Cases ergänzt**
  (`zeigt den überfälligkeits-hinweis weiterhin korrekt neben den neuen bankdaten an`,
  `zeigt den fälligkeits-hinweis ohne überfällig-formulierung für eine noch nicht fällige rechnung`) —
  schließt die bisher nur implizit abgedeckte Regression der
  `isOverdue()`-Fälligkeitslogik (`payment-reminder.blade.php:31-36`) explizit mit
  Werte-Assertions ab, für beide Zweige (überfällig / noch nicht fällig).
- `frontend/src/views/SettingsView.test.ts`: **5 neue Cases ergänzt** in neuem
  `describe('Bankverbindung-Formularfelder')`-Block — schließt eine vollständige
  Lücke: die Datei enthielt vorher **keinen einzigen** Test für die fünf neuen
  T02-Formularfelder, obwohl alle vier T02-Akzeptanzkriterien in `tasks.md` bereits
  als `[x]` erledigt markiert waren.

## Akzeptanzkriterien-Abdeckung

### T01 — Settings-Backend

- [x] `PUT /api/v1/settings` akzeptiert alle 5 neuen Keys einzeln und zusammen mit
  HTTP 200 — `SettingsBankDetailsApiTest.php::akzeptiert einen gültigen kontoinhaber`
  (+ 4 analoge Einzel-Tests) und `::akzeptiert alle fünf bankdaten-felder zusammen`
- [x] Ungültige IBAN → 422 mit Validierungsfehler für `company_bank_iban` —
  `::weist eine iban mit kleinbuchstaben zurück`, `::weist eine iban mit leerzeichen zurück`,
  `::weist eine iban mit falschem präfix zurück`
- [x] Ungültige BIC → 422 mit Validierungsfehler für `company_bank_bic` —
  `::weist eine ungültige bic zurück`
- [x] `company_payment_term_weeks` mit `0`/`53` → 422; `1`–`52` akzeptiert —
  `::weist ein zahlungsziel von null wochen zurück`, `::weist ein zahlungsziel von 53 wochen zurück`,
  `::akzeptiert ein zahlungsziel von 52 wochen als obergrenze`,
  `::akzeptiert ein zahlungsziel von einer woche als untergrenze`
- [x] Alle 5 Felder optional, `PUT` ohne diese Felder liefert 200 —
  `::verursacht keinen validierungsfehler wenn keines der bankdaten-felder gesendet wird`
- [x] `Setting::get('company_payment_term_weeks')` liefert PHP-`int`, kein String —
  `::persistiert das zahlungsziel als integer auch wenn es als string gesendet wird`
  (sendet den Wert bewusst als String, analog zum echten `FormData`-Verhalten des
  Frontends, und prüft anschließend `expect(...)->toBeInt()` + `->toBe(5)`)
- [x] Frischer Seeder-Lauf legt alle 5 Keys mit Defaults an —
  `::legt beim seeden alle fünf neuen bankdaten-keys mit defaults an`
  (instanziiert `SettingsSeeder` direkt, `assertDatabaseHas` pro Key)
- [x] `composer qa` läuft grün — siehe Ausführungs-Ergebnis unten

### T02 — Settings-Frontend

- [x] Alle 5 neuen Felder sichtbar, beschriftet, mit `formData` verbunden — **Lücke
  geschlossen**: `SettingsView.test.ts::zeigt alle fünf bankdaten-felder beschriftet
  im stammdaten-formular an` (prüft Label-Text + Feld-Existenz je Feld) und
  `::verbindet die eingabefelder über v-model mit formData` (setzt Werte über
  `setValue()` und liest sie über `.element.value` zurück)
- [x] Nach `loadSettings()` werden Backend-Werte für die 5 Keys korrekt angezeigt,
  inkl. Fall "Key noch nicht in der DB" → Default aus `formData` — **Lücke
  geschlossen**: `::zeigt vom backend gelieferte bankdaten-werte nach dem laden im
  formular an` (mockt `getSettings` mit allen 5 Keys inkl. `company_payment_term_weeks`
  als `integer`-Wert) und `::behält den formData-default wenn ein bankdaten-key noch
  nicht in der datenbank existiert` (mockt eine leere `company`-Gruppe, prüft leere
  Strings + Default `'2'` für das Wochenfeld)
- [x] `saveSettings()` sendet alle 5 neuen Felder im Payload — **Lücke geschlossen**:
  `::sendet alle fünf bankdaten-felder beim speichern an die settings-api` (füllt
  alle 5 Felder aus, triggert `submit`, prüft den an `settingsApi.updateSettings`
  übergebenen Objekt-Payload per `expect.objectContaining(...)`)
- [x] `npm run lint`, `npm run test`, `npm run build` laufen ohne Fehler/Warnings
  durch — siehe Ausführungs-Ergebnis unten (0 ESLint-Errors, nur bereits im gesamten
  Projekt vorhandene Warnings; `vue-tsc -b && vite build` erfolgreich)

### T03 — Rechnungs-PDF und Rechnungs-E-Mail

- [x] PDF ohne alte Platzhalter-IBAN/BIC im Blade-Quelltext —
  `InvoiceBankDetailsBladeSourceTest.php::enthält im pdf-template nicht mehr die
  hartkodierte platzhalter-iban oder bic` (reiner Quelltext-Grep, keine Cache-/
  Rendering-Abhängigkeit) + `InvoiceBankDetailsPdfTest.php::enthält nicht mehr die
  alte hartkodierte platzhalter-iban und bic` (gerenderter Content)
- [x] Reale Bankdaten aus Settings erscheinen im PDF —
  `InvoiceBankDetailsPdfTest.php::zeigt die konfigurierten bankdaten und das
  zahlungsziel im überweisungstext`
- [x] `Zahlungsziel:`-Zeile bleibt bestehen, zusätzlich neuer Überweisungstext mit
  Wochenanzahl — `::enthält weiterhin die zahlungsziel-zeile mit dem
  fälligkeitsdatum zusätzlich zum überweisungstext`
- [x] Fehlende Settings-Werte → kein PHP-Fehler, leere Werte —
  `::rendert das rechnungs-pdf ohne php-fehler wenn keine bankdaten-settings existieren`
  (Default-Wochenzahl 2 aus dem Fallback im `@php`-Block wird mitgeprüft)
- [x] Rechnungs-E-Mail enthält dieselben vier Kontodaten-Werte + Überweisungstext
  wie das PDF — `InvoiceCreatedMailBankDetailsTest.php::zeigt die konfigurierten
  bankdaten und das zahlungsziel im überweisungstext der rechnungs-mail`,
  `::enthält nicht mehr die alte hartkodierte platzhalter-iban und bic in der
  rechnungs-mail`, `::rendert die rechnungs-mail ohne php-fehler wenn keine
  bankdaten-settings existieren`
- [x] `company_name`/`company_street`/`company_city`/`company_tax_id` bleiben
  unverändert hartkodiert (Non-Goal-Regressionscheck) —
  `InvoiceBankDetailsPdfTest.php::lässt company_name company_street company_city
  und company_tax_id unverändert hartkodiert`
- [x] `composer qa` läuft grün — siehe Ausführungs-Ergebnis unten

### T04 — Zahlungserinnerung-E-Mail

- [x] Keine hartkodierte Platzhalter-IBAN/BIC mehr —
  `PaymentReminderEmailTest.php::rendert die zahlungserinnerung ohne die
  hartkodierte platzhalter-iban`, `::...ohne die hartkodierte platzhalter-bic`
- [x] Reale Bankdaten aus Settings erscheinen (Kontoinhaber, Bankname, IBAN, BIC) —
  `::zeigt die gepflegten bankdaten aus den settings in der zahlungserinnerung`
- [x] Bestehender Einleitungssatz bleibt wortwörtlich unverändert, **kein**
  Wochen-Frist-Text — `::behält den bestehenden einleitungssatz der
  zahlungserinnerung wortwörtlich unverändert bei` (prüft sowohl den exakten Satz
  als auch die Abwesenheit von `'innerhalb von'`)
- [x] `Verwendungszweck`-Zeile mit Rechnungsnummer bleibt funktional —
  `::zeigt weiterhin die rechnungsnummer als verwendungszweck in der
  zahlungserinnerung`
- [x] Fehlende Settings-Werte → kein PHP-Fehler —
  `::rendert die zahlungserinnerung ohne fehler wenn keine bankdaten-settings
  gepflegt sind`
- [x] Überfälligkeits-/Fälligkeitslogik (`isOverdue()`) bleibt unverändert —
  **Lücke geschlossen**: zwei neue Tests `::zeigt den überfälligkeits-hinweis
  weiterhin korrekt neben den neuen bankdaten an` (überfällige Rechnung, prüft
  `isOverdue()` explizit + den "ist seit dem ... überfällig"-Text + "Tage
  überfällig") und `::zeigt den fälligkeits-hinweis ohne überfällig-formulierung
  für eine noch nicht fällige rechnung` (Rechnung mit zukünftigem `due_date`,
  prüft den "ist am ... fällig"-Text und die Abwesenheit von "überfällig"). Vorher
  war diese Logik nur implizit über das erfolgreiche Rendern in den bestehenden
  Tests abgedeckt, ohne explizite Werte-Assertion auf den Fälligkeitstext.
- [x] `composer qa` läuft grün — siehe Ausführungs-Ergebnis unten

## Ausführungs-Ergebnis

### Backend — gezielter Lauf der neuen/geänderten Test-Dateien

```
docker compose exec php vendor/bin/pest --no-coverage \
  tests/Feature/Api/SettingsBankDetailsApiTest.php \
  tests/Feature/Pdf/InvoiceBankDetailsPdfTest.php \
  tests/Feature/InvoiceCreatedMailBankDetailsTest.php \
  tests/Unit/InvoiceBankDetailsBladeSourceTest.php \
  tests/Feature/PaymentReminderEmailTest.php

  PASS  Tests\Feature\Api\SettingsBankDetailsApiTest      (17 tests)
  PASS  Tests\Feature\Pdf\InvoiceBankDetailsPdfTest        (5 tests)
  PASS  Tests\Feature\InvoiceCreatedMailBankDetailsTest    (4 tests)
  PASS  Tests\Unit\InvoiceBankDetailsBladeSourceTest       (4 tests)
  PASS  Tests\Feature\PaymentReminderEmailTest             (8 tests, davon 2 neu)

  Tests:    38 passed (96 assertions)
```

### Backend — volle Suite (`composer qa`: lint, stan, compat-check, test)

```
docker compose exec php composer qa

lint (Pint):          PASS, 297 files
stan (Larastan):       [OK] No errors, 202/202
compat-check (PHPCS):  keine Ausgabe = keine Verstöße gegen PHP-8.2-Kompatibilität
test (Pest):            Tests: 760 passed (2405 assertions)
```

Hinweis: Der in `task-T03.notes.md` dokumentierte, als "unabhängig" eingestufte
Flake in `CustomerApiTest.php` (Testreihenfolge-/Faker-Kollision) trat in diesem
Lauf **nicht** auf — volle Suite ist ohne Einschränkung grün (760/760).

### Frontend — Vitest

```
docker compose exec node npm run test -- --run

Test Files:  20 passed (20)
     Tests:  214 passed (214)
```

`SettingsView.test.ts` einzeln: 10 Tests (5 bestehend + 5 neu), alle grün.

### Frontend — Lint und Build (Akzeptanzkriterium laut CLAUDE.md Abschnitt "Frontend-Tasks")

```
docker compose exec node npm run lint
✖ 3045 problems (0 errors, 3045 warnings)
```
0 Errors projektweit; die beiden Warnings in `SettingsView.test.ts` (`Unexpected any`,
Zeile 96 bestehend + Zeile 251 neu) folgen demselben bereits etablierten Muster
(`{ data: {}, message: '...' } as any` als Mock-Rückgabewert), keine neue
Warnkategorie.

```
docker compose exec node npm run build
vue-tsc -b && vite build
✓ 643 modules transformed.
✓ built in 2.33s
```
Kein TypeScript-Fehler, kein Build-Fehler.

## Fehler

Keine. Alle Tests (bestehende + neu ergänzte) sind grün, `composer qa`,
`npm run lint`, `npm run test` und `npm run build` laufen fehlerfrei durch.

## Nicht durchgeführt (außerhalb des Scopes)

- **MySQL-Portabilitäts-Lauf** (`docker-compose.mysql.yml`, `migrate:fresh`) wurde
  nicht durchgeführt. `design.md` (Non-Goals) stellt explizit klar, dass dieser
  Change **keine Migration** enthält — die `settings`-Tabelle ist bereits ein
  generisches Key/Value-Schema, `git status` zeigt keine geänderte/neue Datei unter
  `backend/database/migrations/`.

## Anmerkung zu bestehenden Test-Dateien (keine Produktivcode-Änderung)

Die vier bereits vom Entwickler angelegten Test-Dateien für T01/T03
(`SettingsBankDetailsApiTest.php`, `InvoiceBankDetailsPdfTest.php`,
`InvoiceCreatedMailBankDetailsTest.php`, `InvoiceBankDetailsBladeSourceTest.php`)
waren bereits vollständig, TESTING.md-konform (Groups, Factory-States,
Laravel-/Pest-Assertion-Trennung, `it()`-Stil) und deckten alle zugehörigen
Akzeptanzkriterien ab — hier war keine Ergänzung nötig. Die zwei tatsächlichen
Lücken (T02-Frontend komplett ungetestet, T04-Fälligkeitslogik nur implizit
getestet) wurden wie oben beschrieben geschlossen.

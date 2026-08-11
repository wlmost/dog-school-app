# Task T01 — Settings-Backend: neue Bankdaten- und Zahlungsziel-Felder

## Status
Abgeschlossen.

## Geänderte/neue Dateien

- `backend/app/Http/Requests/UpdateSettingsRequest.php`
  - `rules()`: fünf neue Regeln für `company_bank_account_holder`,
    `company_bank_name`, `company_bank_iban`, `company_bank_bic`,
    `company_payment_term_weeks` ergänzt (nach
    `company_registration_number`, vor `company_small_business`).
    IBAN-/BIC-Regex 1:1 aus `UpdateCustomerRequest.php:61-62` übernommen.
  - `attributes()`: passende deutsche Labels für die fünf neuen Keys
    ergänzt.
- `backend/database/seeders/SettingsSeeder.php`
  - `$companySettings`: fünf neue Zeilen (Gruppe `company`) mit den in
    `tasks.md` vorgegebenen Defaults, IBAN ohne Leerzeichen
    (`DE89370400440532013000`), damit der Seed-Wert die eigene Regex
    besteht.
- `backend/app/Http/Controllers/SettingsController.php`
  - `determineTypeAndGroup()`: expliziter Fall
    `$key === 'company_payment_term_weeks' => 'integer'` vor dem
    generischen `is_int($value)`-Check ergänzt (Begründung: FormData
    liefert immer Strings, siehe `design.md` Decision 3). Gruppierung
    unverändert, da alle neuen Keys mit `company_` beginnen.
- `backend/tests/Feature/Api/SettingsBankDetailsApiTest.php` (neu)
  - Group `api`, `setting` (passend zu bestehendem
    `SettingsValidationTest.php` im selben Verzeichnis).
  - Deckt alle Akzeptanzkriterien ab: einzelne gültige Werte pro Feld,
    alle 5 Felder zusammen, ungültige IBAN (Kleinbuchstaben, Leerzeichen,
    falsches Präfix) → 422, ungültige BIC → 422, Zahlungsziel-Grenzwerte
    (0/53 abgelehnt, 1/52 akzeptiert), Optionalität (kein Feld gesendet →
    200), Typumwandlung (`company_payment_term_weeks` wird als String
    gesendet — analog zum echten `FormData`-Verhalten des Frontends — und
    landet trotzdem als PHP-`int` in `Setting::get()`), sowie ein
    Seeder-Lauf, der alle 5 neuen Keys mit den definierten Defaults
    anlegt.

**Nicht angefasst:** `backend/app/Http/Controllers/Api/SettingsController.php`
(totes Duplikat, laut Task-Beschreibung explizit ausgenommen).

## Anmerkung zu parallelen Änderungen

`frontend/src/views/SettingsView.vue` war beim Start dieser Task bereits
im Working Tree verändert (`git status` zeigte `modified`, vermutlich
durch die parallel laufende Task T02). Diese Datei gehört nicht zu T01
und wurde von mir nicht angefasst.

## QA-Ergebnisse

Docker war zu Beginn der Session nicht verfügbar (`Cannot connect to the
Docker daemon`). Da `phpunit.xml` für Tests durchgehend SQLite
(`:memory:`) verwendet, wurden die Checks stattdessen direkt gegen die
lokal in `backend/vendor/bin/` vorhandenen Tools ausgeführt (kein
`composer`-Binary lokal vorhanden, daher die einzelnen Scripts aus
`composer.json` manuell aufgerufen):

```bash
php vendor/bin/pint --test                                          # PASS
php vendor/bin/phpstan analyse --memory-limit=1G                    # [OK] No errors
php vendor/bin/phpcs --standard=.phpcs-baseline.xml app/ database/ config/ routes/   # keine Ausgabe = keine Verstöße
php -d memory_limit=512M vendor/bin/pest --no-coverage              # 739 passed (2346 assertions)
php -d memory_limit=512M vendor/bin/pest --no-coverage --group=setting  # 24 passed (48 assertions)
```

Hinweis: Der volle `pest`-Lauf ohne erhöhtes `memory_limit` bricht lokal
an einem PDF-Test (`dompdf`) mit `Allowed memory size of 134217728 bytes
exhausted` ab — das ist eine lokale PHP-CLI-`memory_limit`-Beschränkung
(Standard 128M), keine Regression durch T01. Mit `-d memory_limit=512M`
läuft die volle Suite grün durch. In der Docker-Umgebung (CLAUDE.md
Abschnitt 7.1) ist das vermutlich bereits passend konfiguriert; sollte
das dort ebenfalls auftreten, ist das ein eigenständiger, von T01
unabhängiger Befund.

Ein MySQL-Portabilitäts-Lauf (`docker-compose.mysql.yml`,
`migrate:fresh`) wurde **nicht** durchgeführt, da diese Task keine
Migration enthält (reine Eloquent-/FormRequest-/Seeder-Änderungen,
`settings`-Tabelle bereits generisch, siehe `design.md` "Non-Goals").

## Akzeptanzkriterien — Abgleich

- [x] `PUT /api/v1/settings` akzeptiert alle 5 neuen Keys einzeln und
  zusammen mit HTTP 200 bei gültigen Werten.
- [x] Ungültige IBAN → 422 mit Validierungsfehler für `company_bank_iban`.
- [x] Ungültige BIC → 422 mit Validierungsfehler für `company_bank_bic`.
- [x] `company_payment_term_weeks` mit `0`/`53` → 422; `1`–`52` akzeptiert.
- [x] Alle 5 Felder optional — `PUT` ohne diese Felder liefert weiterhin 200.
- [x] Nach dem Speichern liefert `Setting::get('company_payment_term_weeks')`
  einen PHP-`int`.
- [x] `php artisan db:seed --class=SettingsSeeder` (im Test via
  `(new SettingsSeeder)->run()` simuliert) legt alle 5 neuen Keys mit
  Defaults an.
- [x] `composer qa`-Äquivalent (lint, stan, compat-check, test) läuft grün.

## Annahmen

- Da kein `composer`-Binary lokal verfügbar war, wurden die in
  `composer.json` unter `scripts.qa` definierten Einzelschritte manuell
  über `vendor/bin/*` ausgeführt (inhaltlich identisch zu `composer qa`).
- IBAN-/BIC-Testwerte in `SettingsBankDetailsApiTest.php` sind fiktive,
  öffentlich bekannte Beispielwerte (u. a. aus der bestehenden
  Platzhalter-IBAN im Projekt), keine echten Kontodaten.

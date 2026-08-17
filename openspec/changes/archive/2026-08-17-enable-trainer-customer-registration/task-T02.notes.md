# T02: AuthenticationTest — bestehenden Test präzisieren, neue Rollen-Fälle abdecken

## Zusammenfassung

Geänderte Datei: `backend/tests/Feature/AuthenticationTest.php`

- `'non-admin cannot register new user'` (bisher `AuthenticationTest.php:126-138`,
  erstellte einen Trainer und erwartete 403) umbenannt zu
  `'customer cannot register new user'`. Deckt jetzt ausschließlich den
  Customer-Fall ab (`User::factory()->customer()->create()` statt
  Magic-String `->create(['role' => 'trainer'])`), erwartet weiterhin 403,
  zusätzlich ergänzt um `assertDatabaseMissing` (kein User wird angelegt).
- Neuer Test `'trainer can register a new customer'`: Trainer
  (`User::factory()->trainer()->create()`) registriert `role: 'customer'`
  → 201, inkl. `assertDatabaseHas` mit `role: 'customer'` sowie den
  übergebenen `first_name`/`last_name`.
- Neuer Test `'trainer cannot register a new admin'`: Trainer sendet
  `role: 'admin'` → 422 mit `assertJsonValidationErrors(['role'])`, plus
  `assertDatabaseMissing` (kein User angelegt).
- Neuer Test `'trainer cannot register a new trainer'`: analog für
  `role: 'trainer'` → 422 mit `assertJsonValidationErrors(['role'])`, plus
  `assertDatabaseMissing`.
- Neuer Test `'unauthenticated request cannot register new user'`: Aufruf
  ohne `actingAs()`/Bearer-Token → 401, plus `assertDatabaseMissing` (kein
  User angelegt). Deckt das von `verification.md` (Skeptiker-Befund,
  "Nicht auffindbar") und `specs/user-registration/spec.md`
  ("Unauthenticated request is unauthorized") geforderte, bislang nicht
  automatisiert getestete Szenario ab.
- `'admin can register new user'` (`AuthenticationTest.php:89-124`)
  unverändert gelassen, wie in den Akzeptanzkriterien gefordert.

Alle neuen/geänderten Tests verwenden Factory-States (`->admin()`,
`->trainer()`, `->customer()`) statt Magic Strings gemäß `TESTING.md`
Abschnitt 3.1, HTTP-Assertions im Laravel-Style
(`assertStatus`, `assertJsonValidationErrors`), DB-Assertions im
Laravel-Style (`assertDatabaseHas`/`assertDatabaseMissing`) gemäß
`TESTING.md` Abschnitt 5.

## Abweichungen vom Plan

- Der bestehende Datei-Stil (`test('...', function () {...})` statt
  `it('...', function () {...})`, keine `uses()->group(...)`-Zeile) wurde
  **nicht** auf den `it()`/Groups-Standard aus `TESTING.md` Abschnitt 2/7
  umgestellt. Begründung: `TESTING.md` (Präambel, Abschnitt 1) hält fest,
  dass diese Schablone für **neue** Test-Dateien verbindlich ist und
  Bestand nicht rückwirkend angepasst wird ("Boy-Scout-Regel" ist optional,
  "bei Gelegenheit"). `AuthenticationTest.php` ist eine Bestandsdatei mit
  15 Tests; ein vollständiges Umstellen aller Tests auf `it()` + Groups war
  nicht Teil des T02-Auftrags (der Auftrag listet explizit nur die
  Umbenennung eines Tests sowie 5 neue Testfälle) und hätte den Diff über
  den beauftragten Scope hinaus mechanisch aufgebläht (siehe
  Projekt-Memory zu "Large mechanical reformats" — vorab beim User
  nachfragen, wenn kein Baseline-Konsens besteht). Die explizit im
  Reviewer-Checklisten-Punkt "Factory-States verwendet" geforderte Regel
  (`TESTING.md` Abschnitt 10, ohne "in neuen Dateien"-Einschränkung) wurde
  dagegen für alle neuen/geänderten Tests umgesetzt.
- Kein weiterer Scope-Zuwachs: `RegisterRequest.php` (T01) nicht
  angefasst, keine anderen Test-Dateien geändert außer der explizit in den
  Task-Akzeptanzkriterien geforderten Regressionsprüfung von
  `EmailNotificationTest.php` (nur gelesen und ausgeführt, nicht
  verändert).

## Verifikation

### Statische Checks (in Docker, `docker compose exec php ...`)

- `docker compose exec php composer qa` (lint + stan + compat-check +
  pest) → **vollständig grün**:
  - `composer lint` → `[OK] No errors` (PHP-CS-Fixer/Pint, `--dry-run`).
  - `composer stan` → `[OK] No errors` (PHPStan/Larastan).
  - `composer compat-check` → keine Ausgabe = keine PHP-8.2-Verstöße
    (PHPCompatibility).
  - `composer test` (Pest) → **897 passed, 3 skipped** (die 3 Skips sind
    die bekannten, unveränderten Concurrency-Tests, die laut vorhandener
    Kommentierung eine echte MVCC-DB benötigen — unabhängig von T02).

### Gezielte Testläufe

```
docker compose exec php vendor/bin/pest --filter=AuthenticationTest
```
→ 15 passed (59 assertions), inkl. aller 6 T02-relevanten Tests:
`customer cannot register new user`, `trainer can register a new customer`,
`trainer cannot register a new admin`, `trainer cannot register a new
trainer`, `unauthenticated request cannot register new user`, `admin can
register new user`.

```
docker compose exec php vendor/bin/pest tests/Feature/EmailNotificationTest.php
```
→ 11 passed (19 assertions), inkl. der beiden admin-initiierten
`/auth/register`-Tests im `describe('User Registration Emails', ...)`-Block
(`EmailNotificationTest.php:124-150`) — **keine Regression**, wie in
`design.md` ("Risks / Trade-offs") erwartet, da Admin-Verhalten von diesem
Change unverändert bleibt.

## Kompatibilität

- Reine Testdatei-Änderung, kein Anwendungscode betroffen. `composer
  compat-check` scannt laut `backend/composer.json:69` nur `app/`,
  `database/`, `config/`, `routes/` — nicht `tests/` — ist für diese Task
  also nicht aussagekräftig für die geänderte Datei selbst. Manuelle
  Prüfung: Die verwendeten Konstrukte (Pest `test()`, Factory-States,
  Laravel-`assert*()`-Methoden) sind reine PHP-8.2/Laravel-11-Standard-API,
  keine der in `CLAUDE.md` Abschnitt 4.1 gelisteten 8.3/8.4-Features
  (kein Property-Hook, keine Typed Class Constants, kein `#[\Override]`,
  kein `json_validate()`, keine Dynamic Class Constant Fetch, kein
  klammernloses `new`).
- Keine Migration, kein SQL, keine DB-Portabilitätsfragen betroffen.

## Status

Task-Checkboxen in `tasks.md` (T02) auf `[x]` gesetzt. Nicht committet —
Commit erfolgt separat durch den Koordinator nach Review.

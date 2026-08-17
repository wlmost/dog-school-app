# Test-Report: T01 + T02 (enable-trainer-customer-registration)

**Status:** alle-gruen

Deckt beide Tasks ab, da sie eng zusammenhängen: T01
(`RegisterRequest.php`, sicherheitskritische Autorisierungs-/
Validierungslogik) und T02 (`AuthenticationTest.php`, zugehörige
Testfälle). Ausgeführt auf Branch `feature/enable-trainer-customer-registration`
in der Docker-Umgebung (`docker compose exec php ...`).

## Hinzugefügte / geänderte Tests

Vom Entwickler (T02) bereits vorhanden in `backend/tests/Feature/AuthenticationTest.php`:
- `'customer cannot register new user'` (umbenannt von `'non-admin cannot register new user'`)
- `'trainer can register a new customer'`
- `'trainer cannot register a new admin'`
- `'trainer cannot register a new trainer'`
- `'unauthenticated request cannot register new user'`
- `'admin can register new user'` (unverändert)

Vom Tester zusätzlich ergänzt (Lücken gegen `specs/user-registration/spec.md`
und `design.md`-Abschnitt "Sicherheitskritische Änderung" geschlossen), in
`backend/tests/Feature/AuthenticationTest.php`:
- `'admin can register a new admin'` — Spec-Szenario "Admin retains
  unrestricted role assignment" war für `role: 'admin'` bislang nicht
  direkt mit 201-Erfolgsfall getestet (nur der Trainer-Reject-Fall sendet
  `role: 'admin'`).
- `'admin can register a new customer'` — dasselbe Spec-Szenario für
  `role: 'customer'` durch einen Admin war bislang nur indirekt über
  Validierungsfehler-Tests (`registration validates email uniqueness` /
  `password strength`) berührt, nie als eigenständiger Erfolgsfall.
- `'trainer cannot escalate privileges via additional forged fields'` —
  direkter Test des in `design.md` (Abschnitt "Sicherheitskritische
  Änderung") explizit geforderten Exploit-Szenarios: Trainer sendet
  `role: 'admin'` zusammen mit erfundenen Feldern `is_admin: true` und
  `force_role: 'admin'` → weiterhin 422, kein User wird angelegt.
- `'forged fields on an allowed trainer registration have no effect'` —
  Ergänzung dazu: Trainer sendet einen ansonsten gültigen
  `role: 'customer'`-Request zusätzlich mit `is_admin: true` /
  `force_role: 'admin'` → 201, aber der angelegte User hat weiterhin
  `role: 'customer'`, keine Privilegien-Eskalation über zusätzliche Felder.

4 neue Testfälle, keine bestehenden Tests entfernt oder verändert (außer
den bereits vom Entwickler in T02 vorgenommenen, dokumentierten Änderungen).
Stil: `test()` statt `it()`, keine `uses()->group(...)`-Zeile — konsistent
mit dem bestehenden Bestands-Stil der Datei (siehe `task-T02.notes.md`,
Abschnitt "Abweichungen vom Plan": `TESTING.md` verlangt die neue Schablone
nur für neue Dateien, nicht rückwirkend für Bestandsdateien; da diese Datei
bereits vor T02 im `test()`-Stil ohne Groups bestand, wurde konsistent
weitergemacht statt den Diff mechanisch aufzublähen).

Factory-States (`->admin()`, `->trainer()`) statt Magic Strings verwendet
(`TESTING.md` Abschnitt 3.1). HTTP-Assertions Laravel-Style
(`assertStatus`, `assertJsonValidationErrors`), DB-Assertions Laravel-Style
(`assertDatabaseHas`/`assertDatabaseMissing`) gemäß `TESTING.md` Abschnitt 5.

## Akzeptanzkriterien-Abdeckung

### T01 (`RegisterRequest.php`)

- [x] Trainer + `role: 'customer'` → 201, User mit `role: 'customer'`
      angelegt — `AuthenticationTest.php::'trainer can register a new customer'`
- [x] Trainer + `role: 'admin'`/`role: 'trainer'` → 422 mit
      Validierungsfehler auf `role`, kein User angelegt —
      `'trainer cannot register a new admin'`,
      `'trainer cannot register a new trainer'`
- [x] Admin registriert weiterhin alle drei Rollen → 201 (Regressionsschutz)
      — `'admin can register new user'` (role: trainer),
      `'admin can register a new admin'` (neu, role: admin),
      `'admin can register a new customer'` (neu, role: customer)
- [x] Customer (nicht Admin/Trainer) erhält weiterhin 403 —
      `'customer cannot register new user'`
- [x] Unauthentifizierter Aufruf erhält weiterhin 401 —
      `'unauthenticated request cannot register new user'`
- [x] `composer compat-check` bleibt grün — bestätigt, keine Ausgabe (siehe
      Ausführungs-Ergebnis unten)
- [x] `composer stan` bleibt grün — bestätigt, `[OK] No errors` (215/215)

### T02 (`AuthenticationTest.php`)

- [x] Test für Customer → 403 vorhanden und grün —
      `'customer cannot register new user'`
- [x] Trainer registriert `role: 'customer'` → 201 inkl.
      `assertDatabaseHas` — `'trainer can register a new customer'`
- [x] Trainer versucht `role: 'admin'` → 422 mit
      `assertJsonValidationErrors(['role'])` —
      `'trainer cannot register a new admin'`
- [x] Trainer versucht `role: 'trainer'` → 422 mit
      `assertJsonValidationErrors(['role'])` —
      `'trainer cannot register a new trainer'`
- [x] `'admin can register new user'` bleibt unverändert grün — bestätigt
- [x] Unauthentifizierter Aufruf → 401, kein User angelegt —
      `'unauthenticated request cannot register new user'`
- [x] `EmailNotificationTest.php` bleibt vollständig grün (kein
      Regressionsverhalten) — bestätigt, 11 passed (19 assertions), siehe
      Ausführungs-Ergebnis
- [x] `composer qa` läuft vollständig grün durch — bestätigt

### Zusätzlich geprüft (über die formalen Akzeptanzkriterien hinaus,
aus `design.md`-Abschnitt "Sicherheitskritische Änderung")

- [x] Server-seitige, nicht client-beeinflussbare Rollen-Einschränkung:
      zusätzliche erfundene Felder (`is_admin`, `force_role`) haben weder
      im Reject-Fall noch im Erfolgsfall Wirkung —
      `'trainer cannot escalate privileges via additional forged fields'`,
      `'forged fields on an allowed trainer registration have no effect'`

Alle formalen Akzeptanzkriterien aus `tasks.md` (T01 und T02) sowie alle
Szenarien aus `specs/user-registration/spec.md` sind durch konkrete,
grüne Tests abgedeckt. Keine offenen Lücken.

## Ausführungs-Ergebnis

```
$ docker compose exec php vendor/bin/pest --filter=AuthenticationTest --no-coverage
   PASS  Tests\Feature\AuthenticationTest
  ✓ user can login with valid credentials                                0.23s
  ✓ user cannot login with invalid credentials                           0.23s
  ✓ user cannot login with soft deleted account                          0.25s
  ✓ authenticated user can logout                                        0.03s
  ✓ admin can register new user                                          0.04s
  ✓ customer cannot register new user                                    0.02s
  ✓ trainer can register a new customer                                  0.02s
  ✓ trainer cannot register a new admin                                  0.02s
  ✓ trainer cannot register a new trainer                                0.02s
  ✓ unauthenticated request cannot register new user                     0.02s
  ✓ admin can register a new admin                                       0.02s
  ✓ admin can register a new customer                                    0.02s
  ✓ trainer cannot escalate privileges via additional forged fields      0.02s
  ✓ forged fields on an allowed trainer registration have no effect      0.02s
  ✓ registration validates email uniqueness                              0.02s
  ✓ registration validates password strength                             0.02s
  ✓ registration validates role                                          0.02s
  ✓ authenticated user can get their profile                             0.02s
  ✓ unauthenticated user cannot get profile                              0.02s

  Tests:    19 passed (70 assertions)
  Duration: 1.34s
```

```
$ docker compose exec php vendor/bin/pest tests/Feature/EmailNotificationTest.php --no-coverage
   PASS  Tests\Feature\EmailNotificationTest
  ✓ Booking Confirmation Emails → it sends confirmation email when crea…  0.25s
  ✓ Booking Confirmation Emails → it sendet beim erstellen einer buchun…  0.03s
  ✓ Booking Confirmation Emails → it sends confirmation email when conf…  0.04s
  ✓ Booking Confirmation Emails → it does not send email when booking c…  0.03s
  ✓ Booking Confirmation Emails → it includes correct booking details i…  0.03s
  ✓ User Registration Emails → it sendet bei der registrierung eines ne…  0.03s
  ✓ User Registration Emails → it sendet die willkommens-mail an den ne…  0.03s
  ✓ Invoice Creation Emails → it does not send email when creating an i…  0.04s
  ✓ Invoice Creation Emails → it does not send email when invoice creat…  0.03s
  ✓ Email Queue Configuration → it queues booking confirmation email in…  0.04s
  ✓ Email Queue Configuration → it does not queue an invoice email on c…  0.04s

  Tests:    11 passed (19 assertions)
  Duration: 0.64s
```

```
$ docker compose exec php composer qa
   PASS   ......................................................... 334 files       (Pint --test, lint)
Note: Using configuration file /var/www/html/phpstan.neon.
[OK] No errors                                                                       (PHPStan/Larastan, 215/215)
(compat-check: keine Ausgabe = keine PHPCompatibility-Verstöße gegen .phpcs-baseline.xml)
...
  WARN  Tests\Concurrency\Domain\Invoice\InvoiceDunningRecorderConcurrencyTest
  - benötigt echte MVCC-DB, unabhängig von diesem Change
  WARN  Tests\Concurrency\Domain\Payment\InvoicePaymentRecorderConcurrencyTest
  - 2 Tests, benötigen echte MVCC-DB, unabhängig von diesem Change

  Tests:    3 skipped, 901 passed (2855 assertions)
  Duration: ~33s
```

`composer qa` = `lint` + `stan` + `compat-check` + `test` (siehe
`backend/composer.json`). Alle vier Schritte vollständig grün. Die 3
`WARN`/skipped Tests sind bekannte, vom Change unabhängige
Concurrency-Tests, die eine echte MVCC-Datenbank (Postgres/MySQL statt der
lokalen Test-DB) benötigen — bereits vor diesem Change so dokumentiert
(siehe `task-T02.notes.md`).

## MySQL-Portabilitäts-Check

`docker-compose.mysql.yml` existiert **nicht** im Repo (`find` am
Repo-Root liefert keinen Treffer). Ein MySQL-Testlauf gemäß `CLAUDE.md`
Abschnitt 7.1 ("Vor `git push`/PR: ... `docker-compose.mysql.yml`") war
technisch nicht durchführbar.

**Einschätzung, ob nötig:** Nicht nötig für diesen Change. Er ist laut
`proposal.md` ("Impact", Zeile 67: "Backend only (PHP), keine Migration,
keine Frontend-Änderung") und `design.md` ("Kompatibilität", Zeile
150-155) reine Eloquent-/Validierungslogik in `RegisterRequest::rules()`
und `authorize()` — kein raw SQL, kein `DB::raw()`/`whereRaw()`, keine
Migration, keine DB-spezifischen Konstrukte (`Rule::in()`,
Nullsafe-Operator `?->`, `User::create()` über Eloquent). Damit greift die
"Eloquent-only-Änderungen sind unkritisch"-Ausnahme aus `CLAUDE.md`
Abschnitt 7.2 ("Projektspezifische Workflow-Regeln"). Kein Blocker.

## Fehler

Keine. Alle Tests grün, `composer qa` vollständig grün.

## Hinweis zur Sicherheitskritikalität (informativ, kein Befund)

Zusätzlich zur automatisierten Testabdeckung wurde in `task-T01.notes.md`
eine manuelle Empirie-Prüfung (curl gegen laufenden Docker-Nginx) für alle
Rollenkombinationen dokumentiert, die mit den hier automatisierten
Ergebnissen übereinstimmt. Die zentrale Sicherheitseigenschaft — die
Rollen-Einschränkung basiert ausschließlich auf `$this->user()` (Server-
Auth-Zustand) und ist durch keinen Client-Input (weder `role` selbst noch
erfundene Zusatzfelder) umgehbar — ist jetzt sowohl durch
`verification.md` (Framework-Code-Analyse: `passesAuthorization()` vor
`getValidatorInstance()`) als auch durch die neuen Exploit-Tests in
diesem Report doppelt abgesichert.

## Fazit

**Alle Akzeptanzkriterien aus T01 und T02 sind erfüllt** und durch
konkrete, grüne Tests belegt. Alle Szenarien aus
`specs/user-registration/spec.md` sind abgedeckt. `composer qa` läuft
vollständig grün (lint, stan, compat-check, 901 passed/3 skipped Tests).
Zwei zuvor nicht direkt getestete Erfolgsfälle (Admin registriert `role:
'admin'` bzw. `role: 'customer'`) sowie das in `design.md` explizit
geforderte Exploit-Szenario (erfundene Felder wie `is_admin`/`force_role`)
wurden vom Tester ergänzt, um die Testabdeckung lückenlos an die
Sicherheitskritikalität dieses Changes anzupassen. Kein
Produktivcode wurde verändert. MySQL-Portabilitätslauf war mangels
`docker-compose.mysql.yml` nicht durchführbar, ist aber für diesen
Eloquent-only-Change ohne Migration nicht sicherheits- oder
korrektheitsrelevant.

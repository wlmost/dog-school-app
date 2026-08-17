# Abnahme: enable-trainer-customer-registration

**Status:** bereit-für-user-review

## Geprüfte Artefakte

- `proposal.md`, `design.md`, `tasks.md`, `specs/user-registration/spec.md`, `verification.md`
- `task-T01.notes.md`, `task-T02.notes.md`, `task-T01.review.md`, `task-T01.test-report.md`
  (Review und Test-Report decken beide Tasks gemeinsam ab, siehe deren Überschriften)
- `git diff main...feature/enable-trainer-customer-registration` (vollständig gelesen)
- `openspec validate enable-trainer-customer-registration --strict` → `Change 'enable-trainer-customer-registration' is valid`
- `composer qa` final im Docker-Container (`docker compose exec php composer qa`) → Exit-Code 0

## Erfüllt

- **Strukturelle Validität:** `openspec validate --strict` läuft ohne Befund durch.
- **Vollständigkeit der Tasks:** T01 und T02 sind in `tasks.md` vollständig
  mit `[x]` abgehakt; jedes einzelne Akzeptanzkriterium ist im Code-Diff
  nachvollziehbar umgesetzt (siehe unten, Spec-Konformität).
- **Spec-Konformität — Kernänderung bestätigt im Diff** (`git diff main...feature/enable-trainer-customer-registration -- backend/app/Http/Requests/RegisterRequest.php`):
  - `authorize()`: `return $user && ($user->isAdmin() || $user->isTrainer());`
    — Admin und Trainer dürfen aufrufen, Customer/unauthentifiziert weiterhin
    nicht. Deckt Spec-Requirement "Endpoint access restricted to Admin and
    Trainer roles" (`specs/user-registration/spec.md` Z.3-23) ab.
  - `rules()`: `$allowedRoles = $this->user()?->isAdmin() ? ['admin', 'trainer', 'customer'] : ['customer'];`
    an `Rule::in($allowedRoles)` übergeben — Rollen-Einschränkung basiert
    ausschließlich auf serverseitigem Auth-Zustand, nicht auf
    Client-Input. Deckt Spec-Requirement "Role assignment for
    Trainer-initiated registrations is restricted to customer"
    (`specs/user-registration/spec.md` Z.25-53) ab.
  - Kein Controller-Override in `AuthController::register()` — konsistent mit
    `design.md` Entscheidung 2.
  - Statuscode-Verhalten (403→201 für Trainer+customer, 403→422 für
    Trainer+admin/trainer, Admin/Customer/unauthentifiziert unverändert)
    entspricht exakt `proposal.md` Abschnitt "Impact" und `design.md`
    Entscheidung 3.
- **Alle 5 Spec-Szenarien** aus `specs/user-registration/spec.md` sind durch
  konkrete, im Diff sichtbare Tests in `AuthenticationTest.php` abgedeckt
  (Admin/Trainer-Zugriff, Customer-403, unauthenticated-401,
  Trainer-registriert-Customer-201, Trainer-Admin/Trainer-422,
  Admin-unrestricted).
- **Reviewer-Befund "Sollte" #1 (veralteter Docblock) — verifiziert behoben:**
  `AuthController.php:90` lautet jetzt `"Register a new user (Admins and
  Trainers only; Trainers may only assign the customer role)."` (vorher:
  `"Register a new user (Admin only)."`) — bestätigt im tatsächlichen Diff,
  nicht nur laut Notes.
- **Reviewer-Befund "Sollte" #2 (fehlende Admin-Erfolgstests je Rolle) —
  verifiziert behoben:** Im Diff von `AuthenticationTest.php` sind die
  Tests `'admin can register a new admin'` und `'admin can register a new
  customer'` tatsächlich vorhanden (zusätzlich zum bereits bestehenden
  `'admin can register new user'` mit `role: 'trainer'`) — damit ist jede
  der drei Admin-Zielrollen durch einen eigenständigen 201-Erfolgstest mit
  `assertDatabaseHas` abgedeckt. Zusätzlich (über die Reviewer-Forderung
  hinaus, vom Tester ergänzt) zwei Exploit-Tests für erfundene Felder
  (`is_admin`, `force_role`) — im Diff verifiziert.
- **Reviewer "Muss"-Befunde:** keine vorhanden (Review-Gesamtempfehlung: ok).
- **Tests:** `composer qa` (Docker, final ausgeführt) — Pint/Lint grün
  (334 files), PHPStan/Larastan grün (`[OK] No errors`), compat-check grün
  (keine Ausgabe), Pest: 901 passed, 3 skipped (Exit-Code 0). Die 3 Skips
  sind bekannte, vom Change unabhängige Concurrency-Tests, die eine echte
  MVCC-Datenbank benötigen (dokumentiert seit vor diesem Change) — kein
  Regressionsrisiko für diesen Change. `AuthenticationTest.php` gezielt:
  19 passed. `EmailNotificationTest.php` (Regressionsschutz für
  admin-initiierte Registrierung): 11 passed.
- **PHP-Kompatibilität (CLAUDE.md 4.1):** `Rule::in()` und `?->` sind keine
  8.3/8.4-Features; `composer compat-check` bestätigt dies zusätzlich
  automatisiert (grün).
- **DB-Portabilität:** Change ist Eloquent-only ohne Migration und ohne raw
  SQL (`RegisterRequest::rules()`/`authorize()`, `User::create()` über
  Eloquent) — laut CLAUDE.md 7.2 unkritisch. `docker-compose.mysql.yml`
  existiert im Repo nicht; ein MySQL-Lauf war daher nicht durchführbar,
  ist für diesen Change aber nicht sicherheits- oder korrektheitsrelevant.

## Offen / Nacharbeit

Keine blockierenden Punkte.

Nicht-blockierende Hinweise für spätere Changes (kein Nacharbeitsbedarf für
diesen Change):

- `verification.md` (Skeptiker) weist darauf hin, dass
  `frontend/src/stores/auth.ts:108-129` ebenfalls `/auth/register` aufruft,
  aktuell aber ungenutzt ist (kein `.vue`-Consumer gefunden) — reine
  Vollständigkeits-Anmerkung zur Impact-Analyse, kein Sicherheitsrisiko, da
  die Autorisierung serverseitig erzwungen wird. Kein Task nötig.
- Das "Könnte"-Review-Item (expliziter Exploit-Test für erfundene Felder)
  wurde vom Tester bereits proaktiv umgesetzt, obwohl nur "könnte"/optional.

## Empfehlung an den User

Der Change ist vollständig, spec-konform umgesetzt, beide Reviewer-Befunde
sind nachweislich im Diff behoben, und `composer qa` läuft final grün.
Freigabe für User-Gate 2 (PR) empfohlen.

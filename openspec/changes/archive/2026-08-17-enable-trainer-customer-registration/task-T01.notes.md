# T01: RegisterRequest — Trainer-Zugriff mit Rollen-Einschränkung

## Zusammenfassung

Geänderte Datei: `backend/app/Http/Requests/RegisterRequest.php`

- `authorize()`: erweitert von `$user && $user->isAdmin()` auf
  `$user && ($user->isAdmin() || $user->isTrainer())`. Admins und Trainer
  dürfen `POST /api/v1/auth/register` jetzt aufrufen; Customer und
  unauthentifizierte Aufrufer weiterhin nicht.
- `rules()`: die erlaubte Wertemenge für das Feld `role` wird jetzt
  dynamisch aus `$this->user()` abgeleitet (nicht aus dem Request-Body):
  `$this->user()?->isAdmin() ? ['admin', 'trainer', 'customer'] : ['customer']`,
  übergeben an `Rule::in($allowedRoles)`. Da `authorize()` bereits
  sicherstellt, dass nur Admin oder Trainer `rules()` überhaupt erreichen
  (Laravel ruft `passesAuthorization()` vor `getValidatorInstance()` auf —
  verifiziert in `verification.md`), deckt der `else`-Zweig ausschließlich
  den Trainer-Fall ab.
- Kein Controller-Override in `AuthController::register()` (design.md,
  Entscheidung 2) — Enforcement bleibt einzig in `RegisterRequest`.
- Klassen-Docblock (Zeile 12-17) unverändert gelassen, da er den jetzt
  erreichten Ziel-Zustand bereits korrekt beschrieb.

Exakt wie in `design.md` Entscheidung 1 vorgeschlagen umgesetzt, keine
Abweichung vom Plan.

## Abweichungen vom Plan

Keine.

## Verifikation

### Statische Checks (in Docker, `docker compose exec php ...`)

- `composer lint` → grün (334 files, PASS).
- `composer stan` → grün (`[OK] No errors`, 215/215).
- `composer compat-check` → grün (keine Ausgabe = keine Verstöße gegen
  PHP-8.2-Kompatibilität, PHPCompatibility-Sniffs gegen
  `.phpcs-baseline.xml`).
- `composer test` (Teil von `composer qa`) → **1 vorbestehender Testfehler**:
  `AuthenticationTest > non-admin cannot register new user`
  (`backend/tests/Feature/AuthenticationTest.php:126-138`) erwartet 403,
  erhält jetzt 201. Das ist der **erwartete, dokumentierte Soll-Zustand**
  dieses Changes (proposal.md: "Trainer + `role: 'customer'`: 403 → 201")
  und wird in **T02** durch Anpassung dieses Tests behoben. Kein
  Regressions-Bug in T01. Alle übrigen 892 Tests liefen grün (inkl. 3
  Concurrency-Tests, die laut vorhandener Kommentierung eine echte
  MVCC-DB benötigen und in der lokalen Suite als WARN übersprungen
  werden — unabhängig von dieser Änderung).

### Manuelle Empirie (ergänzend, da T02 die dedizierten Testfälle erst
noch schreibt)

Über `php artisan tinker` wurden ein Admin-, ein Trainer- und ein
Customer-User samt Sanctum-Tokens angelegt und via `curl` gegen den
laufenden Docker-Nginx (`http://localhost:8081`) alle Akzeptanzkriterien
aus `tasks.md` T01 durchgespielt:

| Aufrufer | `role` im Body | Erwartet | Tatsächlich |
|---|---|---|---|
| Trainer | `admin` | 422 | 422 |
| Trainer | `trainer` | 422 | 422 |
| Trainer | `customer` | 201 | 201 |
| Customer | `customer` | 403 | 403 |
| unauthentifiziert | `customer` | 401 | 401 |
| Admin | (Regressionstest, bereits per `composer test` grün: `'admin can register new user'`) | 201 | 201 |

Alle sechs Akzeptanzkriterien aus `tasks.md` T01 sind damit erfüllt und
sowohl automatisiert (compat-check/stan/bestehende Admin-Tests) als auch
manuell-empirisch (Trainer/Customer/unauthenticated) bestätigt. Alle
Smoke-Test-User wurden anschließend wieder aus der DB entfernt
(`User::where('email', 'like', '%t01smoke%')->forceDelete()`, 4
gelöscht) — keine Testdaten verbleiben in der Datenbank.

## Kompatibilität

- `Rule::in()` und der Nullsafe-Operator `?->` sind PHP-8.0-Standard bzw.
  Laravel-API, keine PHP-8.3/8.4-Features (siehe `CLAUDE.md` Abschnitt
  4.1) — bestätigt durch grünen `composer compat-check`.
- Keine Migration, kein SQL, keine DB-Portabilitätsfragen betroffen
  (reine Autorisierungs-/Validierungslogik in einem `FormRequest`).

## Status

Task-Checkboxen in `tasks.md` (T01) auf `[x]` gesetzt. Nicht committet —
Commit erfolgt separat durch den Koordinator nach Review.

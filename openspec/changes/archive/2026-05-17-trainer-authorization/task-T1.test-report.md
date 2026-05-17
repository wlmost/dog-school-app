# Test-Report: trainer-authorization

**Tester:** Testing Agent
**Datum:** 2026-05-17

## Test-Ausführung

- **Befehl:** `php -d memory_limit=512M vendor/bin/pest tests/Feature/TrainerApiTest.php --colors=never`
- **Ergebnis:** 18 Tests, 18 bestanden, 0 fehlgeschlagen
- **Status:** ✅ GRÜN

```
   PASS  Tests\Feature\TrainerApiTest
  ✓ Admin → it listet alle trainer auf                                   0.19s
  ✓ Admin → it erstellt einen neuen trainer                              0.03s
  ✓ Admin → it zeigt einen einzelnen trainer an                          0.01s
  ✓ Admin → it aktualisiert einen trainer                                0.01s
  ✓ Admin → it löscht einen trainer                                      0.01s
  ✓ Trainer-Rolle → it erhält 403 beim auflisten von trainern            0.01s
  ✓ Trainer-Rolle → it erhält 403 beim erstellen eines trainers          0.01s
  ✓ Trainer-Rolle → it erhält 403 beim anzeigen eines trainers           0.01s
  ✓ Trainer-Rolle → it erhält 403 beim aktualisieren eines trainers
  ✓ Trainer-Rolle → it erhält 403 beim löschen eines trainers
  ✓ Customer-Rolle → it erhält 403 beim auflisten von trainern           0.01s
  ✓ Customer-Rolle → it erhält 403 beim erstellen eines trainers
  ✓ Customer-Rolle → it erhält 403 beim anzeigen eines trainers          0.01s
  ✓ Customer-Rolle → it erhält 403 beim aktualisieren eines trainers
  ✓ Customer-Rolle → it erhält 403 beim löschen eines trainers
  ✓ Unauthenticated → it erhält 401 beim auflisten von trainern
  ✓ Unauthenticated → it erhält 401 beim erstellen eines trainers        0.01s
  ✓ Unauthenticated → it erhält 401 beim löschen eines trainers          0.01s

  Tests:    18 passed (18 assertions)
  Duration: 0.37s
```

## Spec-Coverage

| Requirement | Szenarien (Spec) | Tests vorhanden | Status |
|---|---|---|---|
| Admin-only access (index, store, show, update, destroy → 200/201) | 5 | 5 | ✅ |
| Non-admin 403 (trainer+customer × list, create, update, delete) | 8 | 10* | ✅ |
| Unauthenticated 401 (index, store, delete) | 3 | 3 | ✅ |

\* Der Test deckt zusätzlich `show` für beide Non-admin-Rollen ab (je 1 extra für `Trainer-Rolle` und `Customer-Rolle`). Die allgemeine Requirement-Formulierung *„any trainer CRUD endpoint"* schließt `show` ein — Extra-Coverage, kein Befund.

### Akzeptanzkriterien-Abdeckung

- [x] Admin → index liefert 200 — `Admin → it listet alle trainer auf`
- [x] Admin → store liefert 201 — `Admin → it erstellt einen neuen trainer`
- [x] Admin → show liefert 200 — `Admin → it zeigt einen einzelnen trainer an`
- [x] Admin → update liefert 200 — `Admin → it aktualisiert einen trainer`
- [x] Admin → destroy liefert 200 — `Admin → it löscht einen trainer` *(Anmerkung unten)*
- [x] Trainer-Rolle → index liefert 403 — `Trainer-Rolle → it erhält 403 beim auflisten von trainern`
- [x] Trainer-Rolle → store liefert 403 — `Trainer-Rolle → it erhält 403 beim erstellen eines trainers`
- [x] Trainer-Rolle → update liefert 403 — `Trainer-Rolle → it erhält 403 beim aktualisieren eines trainers`
- [x] Trainer-Rolle → destroy liefert 403 — `Trainer-Rolle → it erhält 403 beim löschen eines trainers`
- [x] Customer-Rolle → index liefert 403 — `Customer-Rolle → it erhält 403 beim auflisten von trainern`
- [x] Customer-Rolle → store liefert 403 — `Customer-Rolle → it erhält 403 beim erstellen eines trainers`
- [x] Customer-Rolle → update liefert 403 — `Customer-Rolle → it erhält 403 beim aktualisieren eines trainers`
- [x] Customer-Rolle → destroy liefert 403 — `Customer-Rolle → it erhält 403 beim löschen eines trainers`
- [x] Unauthenticated → index liefert 401 — `Unauthenticated → it erhält 401 beim auflisten von trainern`
- [x] Unauthenticated → store liefert 401 — `Unauthenticated → it erhält 401 beim erstellen eines trainers`
- [x] Unauthenticated → destroy liefert 401 — `Unauthenticated → it erhält 401 beim löschen eines trainers`

**Anmerkung Admin-destroy:** Die Spec schreibt HTTP 204 vor, der Controller gibt HTTP 200 zurück. Der Test assertiert `assertOk()` (HTTP 200) und passt damit zum tatsächlichen Controller-Verhalten. Der Reviewer hat dieses Delta als Pre-existing Issue eingestuft (nicht Teil dieses Changes, kein Merge-Blocker). Der Test kann nicht gegen 204 assertieren, ohne Produktivcode anzufassen — bleibt als bekanntes Delta dokumentiert.

## TESTING.md-Konformität

| Regel | Status | Befund |
|---|---|---|
| Factory States statt Magic Strings (§3.1) | ✅ | Behoben: alle drei `beforeEach`-Aufrufe auf `->admin()`, `->trainer()`, `->customer()` umgestellt |
| Pest-Syntax (`describe`/`it`, keine PHPUnit-Klassen) | ✅ | Korrekt |
| `declare(strict_types=1);` | ✅ | Zeile 3 |
| `uses(RefreshDatabase::class)` | ✅ | Zeile 8 |
| `uses()->group(...)` | ✅ | `group('feature', 'trainers')` — Zeile 9 |
| `actingAs()` für Auth-Tests | ✅ | Durchgängig verwendet |
| HTTP-Assertions Laravel-Style | ✅ | `assertOk()`, `assertCreated()`, `assertForbidden()`, `assertUnauthorized()` |
| Test-Benennung: Deutsch, konjugiertes Verb, Kleinschreibung | ✅ | Alle `it()`-Texte korrekt |

## Durchgeführte Korrekturen

**`backend/tests/Feature/TrainerApiTest.php` — Magic Strings → Factory States (Zeilen 12–14)**

Reviewer-Befund [TESTING.md §3.1 — Muss] behoben:

```php
// Vorher (verboten):
$this->admin    = User::factory()->create(['role' => 'admin']);
$this->trainer  = User::factory()->create(['role' => 'trainer']);
$this->customer = User::factory()->create(['role' => 'customer']);

// Nachher (TESTING.md §3.1-konform):
$this->admin    = User::factory()->admin()->create();
$this->trainer  = User::factory()->trainer()->create();
$this->customer = User::factory()->customer()->create();
```

Die Factory-States `admin()`, `trainer()`, `customer()` sind bereits in `database/factories/UserFactory.php` implementiert — kein weiterer Handlungsbedarf.

## Fazit

✅ Tests vollständig und grün. Der kritische Reviewer-Befund (Magic Strings) ist behoben. Alle 16 Spec-Szenarien sind abgedeckt (+ 2 Extra-Szenarien für `show` bei Non-admin-Rollen). Die bekannte 200/204-Diskrepanz bei Admin-Delete ist dokumentiert und liegt nicht im Scope dieses Changes.

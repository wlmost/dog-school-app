# Code Review: trainer-authorization

**Reviewer:** Code Review Agent
**Datum:** 2026-05-17
**Status:** ❌ CHANGES REQUIRED

## Geprüfte Dateien

- `backend/routes/api.php` (Trainer-Route-Verschiebung in `can:admin`-Block)
- `backend/tests/Feature/TrainerApiTest.php` (neu)

---

## Befunde

### Kritische Befunde (müssen vor Merge behoben werden)

**[TESTING.md §3.1 — Magic String Anti-Pattern, Häufung → Muss]**
`backend/tests/Feature/TrainerApiTest.php:12–14`: Alle drei User-Factory-Aufrufe im `beforeEach` verwenden die in TESTING.md explizit **verbotene** Magic-String-Schreibweise statt Factory States:

```php
// Aktuell (verboten gemäß TESTING.md §3.1):
$this->admin    = User::factory()->create(['role' => 'admin']);
$this->trainer  = User::factory()->create(['role' => 'trainer']);
$this->customer = User::factory()->create(['role' => 'customer']);

// Korrekt (verbindlich):
$this->admin    = User::factory()->admin()->create();
$this->trainer  = User::factory()->trainer()->create();
$this->customer = User::factory()->customer()->create();
```

TESTING.md §3.1: *„Tester-Agent darf NICHT auf Magic Strings ausweichen."*
Da alle drei Aufrufe betroffen sind (Häufung), gilt der Befund als **Muss** gemäß Reviewer-Anweisungen.

---

### Verbesserungsvorschläge (optional)

**[REST-Konvention — Könnte]**
`backend/tests/Feature/TrainerApiTest.php:51`: Der Admin-Delete-Test assertiert `->assertOk()` (HTTP 200). Der `TrainerController::destroy()` gibt tatsächlich 200 mit einem JSON-Body zurück, weshalb die Assertion technisch korrekt ist. REST-Konvention (und `php-api-standards.instructions.md`: *„204 No Content: Successful DELETE operations"*) würde jedoch HTTP 204 + leeren Body bevorzugen. Dies ist ein Pre-existing Issue im Controller und nicht Teil dieses Changes — kein Blocker.

---

### Positive Aspekte

- **Route-Positionierung korrekt:** `Route::apiResource('trainers', ...)` liegt jetzt sauber in einem separaten `Route::middleware('can:admin')->group(...)` innerhalb des `auth:sanctum`-Blocks — ohne `Route::prefix('admin')`. URL bleibt `/api/v1/trainers`. Der im Skeptiker-Bericht identifizierte Fallstrick (falscher `admin`-prefix-Block) wurde vermieden.
- **`declare(strict_types=1);`** vorhanden (Zeile 3). `uses(RefreshDatabase::class)` und `uses()->group(...)` korrekt gesetzt (Zeilen 7–8).
- **Pest-Syntax vollständig korrekt:** `describe()`, `beforeEach()`, `it()` — keine PHPUnit-Klassen-Vererbung.
- **Alle 20 Test-Szenarien abgedeckt:** 5 Actions × 4 Rollen (Admin ✅, Trainer 403 ✅, Customer 403 ✅, Unauthenticated 401 für index/store/destroy ✅) gemäß Spec.
- **Fixture-Strategie durchdacht:** `$this->trainer` (role=trainer) wird korrekt als Route-Binding-Ziel für show/update/destroy verwendet, weil `TrainerController` `abort_if($trainer->role \!== 'trainer', 404)` prüft — die Tests würden ohne `role=trainer`-Fixture auf 404 laufen statt auf 200/403/401.
- **PHP 8.2-kompatibel:** Keine 8.3- oder 8.4-Features im Diff.
- **Keine hardcoded Credentials:** Passwörter im Test (`Password123\!`) sind Test-Fixtures, keine echten Secrets.

---

## Sicherheits-Checkliste

| Punkt | Status |
|-------|--------|
| Alle 5 Actions durch `can:admin` geschützt | ✅ |
| Kein falscher `Route::prefix('admin')` | ✅ |
| `auth:sanctum` greift vor `can:admin` | ✅ |
| PHP 8.2-kompatibel | ✅ |
| Keine hardcoded Credentials | ✅ |
| URL-Manipulation durch Route-Positionierung ausgeschlossen | ✅ |

---

## Fazit

Die sicherheitsrelevante Kern-Änderung (`routes/api.php`) ist korrekt implementiert. Der Test-Code enthält jedoch einen klaren Verstoß gegen TESTING.md §3.1 (Magic Strings statt Factory States, dreifach), der vor dem Merge behoben werden muss.

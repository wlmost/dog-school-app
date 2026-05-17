# Skeptic Verification: trainer-authorization

**Datum:** 2026-05-17
**Gesamtstatus:** ✅ ok — Change kann implementiert werden

---

## Geprüfte Annahmen

| # | Annahme | Status | Fundstelle / Befund |
|---|---------|--------|---------------------|
| 1 | `Route::apiResource('trainers', TrainerController::class)` liegt im `auth:sanctum`-Block, aber **nicht** im `can:admin`-Block | ✅ bestätigt | `backend/routes/api.php` ca. Zeile 136: direkt im `Route::prefix('v1')->middleware('auth:sanctum')->group(...)`, keine `can:admin`-Verschachtelung |
| 2 | `can:admin`-Middleware-Pattern existiert bereits (Settings + PricingItem) | ✅ bestätigt | `backend/routes/api.php`: **zwei** separate `can:admin`-Blöcke — PricingItem ca. Zeile 79–89 (mit zusätzlichem `Route::prefix('admin')`), Settings ca. Zeile 183–186 (ohne Prefix) |
| 3 | `Gate::define('admin', ...)` in `AppServiceProvider` | ✅ bestätigt (mit Anmerkung) | `backend/app/Providers/AppServiceProvider.php` ca. Zeile 61 — Gate existiert, **aber**: Design beschreibt `fn($user) => $user->role === 'admin'`, tatsächlicher Code lautet `function ($user) { return $user->isAdmin(); }` — funktional identisch, da `isAdmin()` exakt `$this->role === 'admin'` zurückgibt |
| 4 | `TrainerController` hat kein `$this->authorize()` und kein `authorizeResource()` | ✅ bestätigt | Grep auf `backend/app/Http/Controllers/Api/TrainerController.php` — null Treffer |
| 5 | `UserPolicy.php` existiert; `viewAny` erlaubt admin **und** trainer | ✅ bestätigt | `backend/app/Policies/UserPolicy.php` Zeile 20: `return $user->isAdmin() \|\| $user->isTrainer();` |
| 6 | Kein `TrainerApiTest.php` in `tests/Feature/` | ✅ bestätigt | File-Search über `backend/tests/Feature/**` — kein `TrainerApiTest.php` gefunden (32 Dateien gelistet) |
| 7 | `TrainerController` hat Methoden `index`, `store`, `show`, `update`, `destroy` | ✅ bestätigt | `backend/app/Http/Controllers/Api/TrainerController.php`: alle 5 Methoden vorhanden — `index` Z.22, `store` Z.44, `show` Z.97, `update` Z.102, `destroy` Z.166 |
| 8 | `can:admin`-Middleware-Syntax ist `Route::middleware('can:admin')->group(function () {...})` | ✅ bestätigt | Beide vorhandenen Blöcke in `backend/routes/api.php` nutzen exakt diese Syntax |
| 9 | User-Modell hat `role`-Property und Rollen-Logik | ✅ bestätigt | `backend/app/Models/User.php`: `$role` in `$fillable`, Methoden `isAdmin()`, `isTrainer()`, `isCustomer()` vorhanden |
| 10 | Bestehende Tests nutzen Pest-Syntax (`factory()->create(['role' => ...])`, `actingAs`, `assertForbidden`, `assertUnauthorized`) | ✅ bestätigt | `backend/tests/Feature/CourseApiTest.php` und `CreditPackageApiTest.php` zeigen das vollständige Muster |

---

## Kritische Befunde

### Befund: Zwei `can:admin`-Blöcke in api.php — falscher Block würde Routen brechen

Das Design sagt: „Trainer-Route in den bestehenden `can:admin`-Block verschieben." In `api.php` gibt es aber **zwei** `can:admin`-Blöcke:

1. **PricingItem-Block** (ca. Zeile 79–89): Enthält ein zusätzliches `Route::prefix('admin')` — würde die Trainer-Route auf `/api/v1/admin/trainers` legen statt `/api/v1/trainers`. **Dieser Block ist falsch.**

2. **Settings-Block** (ca. Zeile 183–186): Kein Prefix — URL bleibt `/api/v1/trainers`. **Dieser Block ist das korrekte Vorbild.**

Die Implementierung kann entweder:
- `Route::apiResource('trainers', ...)` in den Settings-`can:admin`-Block einfügen, oder
- einen neuen, separaten `can:admin`-Block nur für Trainer anlegen (ebenfalls korrekt)

In jedem Fall: **kein** `Route::prefix('admin')` hinzufügen.

---

## Empfehlungen für dev-php

1. **Referenz-Block für die Migration:** Der Settings-Block am Ende der Datei ist das richtige Muster:
   ```php
   // Settings Management (Admin only)
   Route::middleware('can:admin')->group(function () {
       Route::get('/settings', [SettingsController::class, 'index']);
       Route::put('/settings', [SettingsController::class, 'update']);
   });
   ```
   Analog dazu einen neuen `can:admin`-Block für Trainer anlegen — **ohne** `Route::prefix('admin')`.

2. **Test-Boilerplate (aus `CourseApiTest.php` / `CreditPackageApiTest.php`):**
   - Factory-Syntax: `User::factory()->create(['role' => 'admin'])` — kein Factory-State, direkte Array-Übergabe
   - Auth: `$this->actingAs($this->admin)->getJson('/api/v1/trainers')`
   - Assertions: `->assertOk()`, `->assertCreated()`, `->assertNoContent()`, `->assertForbidden()`, `->assertUnauthorized()`
   - Unauthenticated: `$this->getJson('/api/v1/trainers')->assertUnauthorized()` (kein `actingAs`)

3. **Gate-Implementation:** Das `can:admin`-Gate ruft `$user->isAdmin()` auf, nicht direkt `$user->role`. Kein Änderungsbedarf, aber bei Tests ist `User::factory()->create(['role' => 'admin'])` der korrekte Weg, einen Admin-User zu erzeugen.

4. **`TrainerApiTest.php`-Dateiheader:** Analog zu anderen Tests:
   ```php
   <?php
   declare(strict_types=1);
   use App\Models\User;
   use Illuminate\Foundation\Testing\RefreshDatabase;
   uses(RefreshDatabase::class);
   ```

5. **Test für `store`/`update`/`destroy`:** Der `TrainerController::show()` und `::update()` und `::destroy()` führen `abort_if($trainer->role \!== 'trainer', 404)` aus. Tests für Admin-Zugriff auf diese Methoden brauchen daher einen Fixture-Trainer mit `role = 'trainer'`.

---

## Fazit

✅ Change kann implementiert werden.

Alle Annahmen in Proposal und Design sind korrekt — mit einer Ausnahme: die Gateway-Lambda-Syntax in `design.md` weicht minimal vom tatsächlichen Code ab (irrelevant für die Implementierung). Der einzige handlungsrelevante Befund ist, dass **der falsche der zwei `can:admin`-Blöcke als Vorbild falsche URLs erzeugen würde** — daher Empfehlung #1 beachten.

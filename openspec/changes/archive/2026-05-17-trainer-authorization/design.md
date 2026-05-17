# Design: trainer-authorization

## Context

**Aktueller Zustand:**
- `Route::apiResource('trainers', TrainerController::class)` liegt in `backend/routes/api.php` (Zeile ~136) im `auth:sanctum`-Middleware-Block, aber **nicht** im `can:admin`-Block.
- Das `can:admin`-Gate ist in `backend/app/Providers/AppServiceProvider.php` (Zeile 61) definiert: `Gate::define('admin', fn($user) => $user->role === 'admin')`.
- Das `can:admin`-Middleware-Pattern wird bereits für Settings- und PricingItem-Routen genutzt.
- `backend/app/Policies/UserPolicy.php` existiert; `viewAny` erlaubt admin+trainer. Änderung daran wäre Kollateralschaden-Risiko — wird nicht angefasst.

---

## Goals / Non-Goals

**Goals:**
- Alle 5 CRUD-Actions der Trainer-API (`index`, `show`, `store`, `update`, `destroy`) auf admin-only einschränken.
- Konsistenz mit dem bestehenden `can:admin`-Middleware-Pattern (Settings, PricingItem).

**Non-Goals:**
- Keine Änderungen an `UserPolicy`.
- Kein Frontend-Eingriff.
- Kein Refactoring des `TrainerController`.
- Kein Einführen neuer Policy-Klassen.

---

## Decisions

### Decision 1: Route-Middleware statt `authorizeResource()`

**Option A: `Route::middleware('can:admin')->group(...)` um Trainer-Routes** → **GEWÄHLT**
- Konsistent mit dem bestehenden Settings/PricingItem-Muster im Projekt.
- Kein Eingriff in Controller oder Policy.
- Minimaler Diff: einzige geänderte Datei ist `api.php`.

**Option B: `$this->authorizeResource()` im Controller + neue `TrainerPolicy`** → abgelehnt
- Würde eine neue Policy-Klasse erfordern (mehr Dateien, mehr Scope).
- Kein funktionaler Mehrwert gegenüber Route-Middleware für diesen Use-Case.

**Option C: `$this->authorizeResource()` gegen bestehende `UserPolicy`** → abgelehnt
- `viewAny` erlaubt aktuell admin+trainer. Eine Einschränkung auf admin-only würde andere Controller brechen, die `viewAny` nutzen.
- Kollateralrisiko zu hoch.

### Decision 2: Alle 5 apiResource-Actions schützen

Alle Actions (`index`, `store`, `show`, `update`, `destroy`) werden in den `can:admin`-Block verschoben. Auch `show` muss geschützt sein — Trainer-Details sind administrative Daten, kein öffentlicher Lesezugriff.

---

## Implementation Plan

**Einzige Dateiänderung: `backend/routes/api.php`**

Die `Route::apiResource('trainers', ...)` Zeile wird aus dem allgemeinen `auth:sanctum`-Block herausgelöst und in den bestehenden `can:admin`-Middleware-Block verschoben (analog zu Settings- und PricingItem-Routen).

```php
// Vorher (im auth:sanctum-Block, ohne can:admin):
Route::apiResource('trainers', TrainerController::class);

// Nachher (im can:admin-Block, der selbst im auth:sanctum-Block liegt):
Route::middleware('can:admin')->group(function () {
    // ... bestehende admin-Routen ...
    Route::apiResource('trainers', TrainerController::class);
});
```

**Neue Testdatei: `backend/tests/Feature/TrainerApiTest.php`**

Testet alle Szenarien aus der Spec:
- Admin: HTTP 200/201/204 auf alle 5 Actions.
- Trainer-Rolle: HTTP 403 auf alle Actions.
- Customer-Rolle: HTTP 403 auf alle Actions.
- Unauthentifiziert: HTTP 401 auf alle Actions.

---

## Risks / Trade-offs

- `UserPolicy.viewAny` erlaubt trainer-Zugriff — durch den Route-Middleware-Ansatz irrelevant, da die Middleware greift, bevor der Controller aufgerufen wird.
- Falls zukünftig ein Trainer seine eigenen Daten via `show` abrufen soll, muss die Route-Middleware angepasst werden (z. B. separate Route außerhalb des `can:admin`-Blocks). Kein Problem für diesen Change.
- Keine DB-Migration nötig. Kein Frontend-Eingriff nötig.

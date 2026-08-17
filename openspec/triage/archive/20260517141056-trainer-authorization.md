# Triage: trainer-authorization

**Pfad:** klein
**Geschätzter Umfang:** 3–4 Dateien, PHP
**Risiko:** mittel — Sicherheitslücke (fehlendes Authorization-Gate auf CRUD-Routen), aber Behebung ist in gut bekanntem Muster eingegrenzt
**Klarheit:** klar — Anforderung, Betroffene Dateien und Akzeptanzkriterien sind vollständig spezifiziert

## Anforderung (Zusammenfassung)

Die Trainer-CRUD-Endpunkte (`GET/POST/PUT/DELETE /api/v1/trainers`) sind zwar durch `auth:sanctum` gegen unauthentifizierte Zugriffe geschützt, besitzen aber keinerlei Rollen-Autorisierung. Jeder eingeloggte User — unabhängig von Rolle — kann alle Trainer-Operationen ausführen; der Schutz findet nur im Frontend statt. Gefordert ist, dass ausschließlich Admins Zugriff auf alle Trainer-CRUD-Routen erhalten; Trainer und Kunden sollen `403 Forbidden` bekommen. Es sollen Feature-Tests für alle drei Rollen vorliegen.

## Befunde aus dem Repo

| Datei | Befund |
|---|---|
| `backend/app/Http/Controllers/Api/TrainerController.php` | Kein `$this->authorize()` und kein `authorizeResource()` in index, store, show, update, destroy |
| `backend/routes/api.php:136` | `Route::apiResource('trainers', TrainerController::class)` liegt im `auth:sanctum`-Block, aber **nicht** im `can:admin`-Block |
| `backend/app/Policies/UserPolicy.php` | Existiert. `viewAny` erlaubt admin **und** trainer (für Trainer-Verwaltung zu weit). `create`/`delete` korrekt admin-only. Keine dedizierte `TrainerPolicy`. |
| `backend/app/Providers/AppServiceProvider.php:61` | `Gate::define('admin', ...)` ist definiert — `can:admin`-Middleware funktioniert bereits (verwendet von Settings- und PricingItem-Routen). |
| `backend/tests/Feature/` | Kein `TrainerApiTest.php`. Erwähnungen von `trainer` in Tests sind ausschließlich Fixtures in anderen Tests (Model Relationships etc.). |

## Empfohlene Implementierungsstrategie

Das Projekt nutzt bereits das Route-Middleware-Muster `Route::middleware('can:admin')->group(...)` für Settings (Zeile 183) und Admin-Pricing (Zeile 77–85). Dies ist die konsistenteste und risikoärmste Lösung:

1. **`routes/api.php`**: `Route::apiResource('trainers', TrainerController::class)` in den bestehenden `can:admin`-Block verschieben oder einen eigenen solchen Block darum legen.
2. **`TrainerController.php`**: Optional (aber empfohlen für Klarheit): `$this->authorizeResource(User::class, 'trainer')` im Konstruktor — oder auf Route-Ebene belassen, dann keine Controller-Änderung nötig.
3. **`UserPolicy.php` (wenn authorizeResource genutzt wird)**: `viewAny` muss auf admin-only eingeschränkt werden — **Achtung:** dies würde bestehende andere Stellen beeinflussen, die `viewAny` auf dem User-Modell prüfen. Empfehlung: **separate `TrainerPolicy`** anlegen, um Kollateralschäden zu vermeiden.
4. **`tests/Feature/TrainerApiTest.php`** (neu): Admin → 200/201/204, Trainer → 403, Kunde → 403, Unauthenticated → 401 für alle 5 CRUD-Aktionen.

**Empfohlener Pfad (minimales Risiko):** Route-Level-Middleware (`can:admin`) ohne Policy, keine Änderung an `UserPolicy`. Das vermeidet jedes Risiko von Seiteneffekten auf andere Controller.

## Risiken und Abhängigkeiten

- `UserPolicy.viewAny` erlaubt derzeit Trainer-Zugriff — falls `authorizeResource()` gegen `UserPolicy` gebunden wird, müsste `viewAny` geändert werden, was andere Controller (z. B. `CustomerController`, `TrainingLogController`) beeinflussen könnte. → Dieses Risiko entfällt bei reiner Route-Middleware-Lösung.
- `show`-Methode ist zwar nicht explizit in der Anforderung erwähnt, gehört aber zur `apiResource` und muss ebenfalls geschützt werden (Vollständigkeit der 403-Anforderung).
- Keine DB-Migrations, keine API-Shape-Änderungen, kein Frontend-Einfluss.

## Empfohlene nächste Aktion

**Architekt** aufrufen für einen kleinen Change: `proposal.md` → `design.md` → `tasks.md` mit einem einzigen PHP-Task für `dev-php`. Der Change enthält: Route-Absicherung, optional TrainerPolicy, Feature-Test-Datei.

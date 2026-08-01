## Context

- Route-Definition der bestehenden, admin-only Trainer-CRUD-Route:
  `backend/routes/api.php:193-196`
  ```php
  // Trainer Management (Admin only)
  Route::middleware('can:admin')->group(function () {
      Route::apiResource('trainers', TrainerController::class);
  });
  ```
- Gate-Definitionen in `backend/app/Providers/AppServiceProvider.php:60-71`:
  `admin` (nur Admin), `trainer` (Trainer **oder** Admin — Zeile 65-67:
  `Gate::define('trainer', fn ($user) => $user->isTrainer() ||
  $user->isAdmin());`), `customer` (Customer/Trainer/Admin).
- `UserResource::toArray()` (`backend/app/Http/Resources/UserResource.php:24-45`)
  liefert u. a. `email`, `phone`, `mobilePhone`, `street`, `postalCode`,
  `city`, `country`, `qualifications`, `specializations` — vollständiges
  Profil, nicht für die reine Auswahl-Box geeignet.
- `TrainerController::index()`
  (`backend/app/Http/Controllers/Api/TrainerController.php:23-40`) baut die
  Trainerliste über `User::query()->where('role', 'trainer')` und
  unterstützt einen optionalen Such-Parameter über
  `App\Helpers\DatabaseHelper::caseInsensitiveLike()`.
- Bestehendes Ordnungs-Präzedenzfall für "spezifische Route vor
  `apiResource`, um Wildcard-Kollision zu vermeiden":
  `backend/routes/api.php:100-101`
  ```php
  Route::get('/customers/profile', [CustomerController::class, 'profile']);
  Route::apiResource('customers', CustomerController::class);
  ```
- Frontend-Fehlerbehandlungs-Utility bereits vorhanden und im Projekt
  etabliert (siehe Merge "feature/error-state-ui-views"):
  `frontend/src/utils/errorHandler.ts` — `handleApiError(error, fallbackMessage)`
  zeigt über `useToastStore()` einen Fehler-Toast, inkl. spezifischer
  Behandlung für HTTP 403 (Zeile 40-44: "Zugriff verweigert").
  `CustomerFormModal.vue:240` importiert `handleApiError` bereits (genutzt
  in `saveDog`/`removeDog`/`copyPassword`), aber **nicht** in
  `loadTrainers()` (Zeile 338-345, nur `console.error`).
  `CourseFormModal.vue:210` importiert `handleApiError` ebenfalls bereits,
  aber `loadTrainers()` (Zeile 276-283) nutzt es ebenfalls nicht.

## Goals / Non-Goals

**Goals:**
- Trainer und Admin können die Trainerliste in den Formularen
  Kunden-Erstellung und Kurs-Erstellung laden, ohne HTTP 403.
- Keine sensiblen Profildaten (E-Mail, Telefon, Adresse, Qualifikationen)
  werden dabei an Trainer ausgeliefert.
- Ladefehler der Trainerliste werden dem User sichtbar gemeldet statt nur
  in der Browser-Konsole verschluckt zu werden.
- Bestehendes, bewusst getestetes Autorisierungsverhalten der
  Admin-only-CRUD-Route bleibt unangetastet.

**Non-Goals:**
- Keine Änderung am Admin-Zugriff auf `GET /api/v1/trainers` — funktioniert
  bereits korrekt (`backend/tests/Feature/TrainerApiTest.php:17-22`).
- Kein Deaktivieren der Select-Box bei Vorauswahl — Trainer dürfen weiterhin
  einen anderen Trainer wählen (User-Entscheidung, siehe Triage-Datei).
- Keine Such-/Filter-Funktion für den neuen Options-Endpoint (nur die
  bestehende Admin-Route braucht das für ihre Verwaltungs-UI — YAGNI für
  eine reine Select-Box mit überschaubarer Trainer-Anzahl).
- Keine Änderung an `UserResource` selbst — das schlanke Format wird über
  eine separate, neue Resource-Klasse gelöst (Single Responsibility statt
  Parametrisierung einer bestehenden Resource).

## Decisions

### 1. Neue Route `GET /api/v1/trainers/options`, registriert vor der `apiResource`

Die neue Route muss **vor** `Route::apiResource('trainers',
TrainerController::class)` (api.php:195) registriert werden, sonst würde
Laravel `options` als `{trainer}`-Routenparameter der `show`-Route
interpretieren (Route-Matching in Registrierungsreihenfolge). Gleiches
Muster existiert bereits für `/customers/profile` vs.
`Route::apiResource('customers', ...)` (api.php:100-101). Platzierung:
direkt vor dem bestehenden `can:admin`-Block für `trainers`
(api.php:193-196), innerhalb der äußeren `auth:sanctum`-Gruppe
(api.php:76).

```php
// Trainer Options (Admin + Trainer) - reduced fields for select boxes
Route::middleware('can:trainer')->get('/trainers/options', [TrainerController::class, 'options'])
    ->name('trainers.options');

// Trainer Management (Admin only)
Route::middleware('can:admin')->group(function () {
    Route::apiResource('trainers', TrainerController::class);
});
```

**Alternative verworfen:** Bestehende `GET /api/v1/trainers`-Route für
Trainer öffnen (`can:admin` → `can:trainer`). Verworfen, weil diese Route
volle Profildaten liefert (siehe Goals) und weil
`backend/tests/Feature/TrainerApiTest.php:56-61` bewusst 403 für Trainer
erwartet — das explizite Verbot des Users, diesen Test anzufassen, schließt
diese Option aus.

### 2. Autorisierung über bestehendes `trainer`-Gate

`Gate::define('trainer', ...)` (AppServiceProvider.php:65-67) existiert
bereits und deckt genau "Trainer oder Admin" ab — kein neues Gate nötig
(DRY). Middleware: `can:trainer`.

### 3. Neue, schlanke Resource-Klasse `TrainerOptionResource`

Statt `UserResource` um optionale Feldreduktion zu erweitern (Parameter,
Konditional-Logik in einer bereits von mehreren Controllern genutzten
Resource — Verstoß gegen Single Responsibility und Open/Closed, da jede
Nutzungsstelle von `UserResource` mitgeprüft werden müsste), wird eine neue
Resource-Klasse `backend/app/Http/Resources/TrainerOptionResource.php`
angelegt, analog zu den bestehenden domänenspezifischen Resources in
`backend/app/Http/Resources/` (z. B. `CustomerResource`, `DogResource`).

```php
final class TrainerOptionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'firstName' => $this->first_name,
            'lastName' => $this->last_name,
            'fullName' => $this->full_name,
        ];
    }
}
```

Kein `#[\Override]`-Attribut (verboten, PHP 8.3) — Methode ohne Marker,
konsistent mit `UserResource::toArray()`.

### 4. Neue Controller-Methode `TrainerController::options()`

```php
public function options(): AnonymousResourceCollection
{
    $trainers = User::query()
        ->where('role', 'trainer')
        ->orderBy('last_name')
        ->orderBy('first_name')
        ->get();

    return TrainerOptionResource::collection($trainers);
}
```

Identische Sortier-/Filterlogik wie `TrainerController::index()`
(api.php-Zeile 25, 37), aber ohne Such-Parameter (Non-Goal) und mit der
schlanken Resource statt `UserResource`. Reine Eloquent-Query, keine
DB-spezifischen Konstrukte — MySQL- und Postgres-kompatibel
(Abschnitt 4.2 CLAUDE.md, unkritisch).

### 5. Frontend: URL-Wechsel + Fehlerbehandlung statt struktureller Umbau

`loadTrainers()` in beiden Komponenten wird minimal angepasst:
- URL `/api/v1/trainers` → `/api/v1/trainers/options`.
- `catch`-Block ruft zusätzlich `handleApiError(err, '<Kontext-Nachricht>')`
  auf (Utility ist in beiden Dateien bereits importiert), statt nur
  `console.error(...)`. `console.error` darf zusätzlich für die
  Entwickler-Diagnose stehen bleiben.
- Response-Parsing bleibt unverändert
  (`response.data.data || response.data` in `CustomerFormModal.vue:341`,
  `response.data.data` in `CourseFormModal.vue:279`) — `TrainerOptionResource::collection()`
  liefert wie `UserResource::collection()` ein `{ data: [...] }`-Envelope,
  keine Breaking Change am Response-Shape.
- `CourseFormModal.vue:45` Anzeige-Fallback `trainer.fullName ||
  trainer.email` → `trainer.fullName || `${trainer.firstName}
  ${trainer.lastName}`` (analog `CustomerFormModal.vue:106`), weil `email`
  im neuen Endpoint nicht mehr existiert und der bisherige Fallback sonst
  `undefined` anzeigen würde, falls `fullName` einmal leer sein sollte.

Kein Umbau der bestehenden Vorauswahl-Logik
(`CustomerFormModal.vue:291-294`) — sie ist bereits korrekt und wird durch
den nun funktionierenden Datenabruf automatisch wirksam.

## Risks / Trade-offs

- **Datenschutz-Trade-off (akzeptiert):** Trainer sehen künftig die Namen
  aller anderen Trainer (nicht aber deren Kontakt-/Qualifikationsdaten).
  Vom User explizit bestätigt (siehe Rückfragen in der Triage-Datei) —
  reduziertes, aber nicht null Informationsleck; für eine
  Trainer-Zuweisungs-Select-Box im selben Betrieb als akzeptabel bewertet.
- **Routen-Reihenfolge ist ein stilles Kopplungsrisiko:** Sollte künftig
  jemand die `can:admin`-`apiResource('trainers', ...)`-Gruppe vor die neue
  `options`-Route verschieben, würde `options` durch die
  `{trainer}`-Wildcard verdeckt und als ungültige Trainer-ID (404)
  interpretiert. Mitigation: Feature-Test für den Options-Endpoint deckt
  das ab (schlägt fehl, falls Reihenfolge bricht); Kommentar im Routen-Code
  verweist auf das Muster.
- **Zwei nahezu identische `loadTrainers()`-Implementierungen** bleiben
  dupliziert (`CustomerFormModal.vue`, `CourseFormModal.vue`). Eine
  gemeinsame Extraktion in ein Composable (`useTrainerOptions.ts`) wäre
  DRY-konform, ist aber nicht Teil dieses Bugfixes (YAGNI — beide Stellen
  sind bereits vor diesem Change eigenständig implementiert; eine
  Extraktion vergrößert den Diff und das Risiko unnötig für einen
  gezielten Bugfix). Kann als Folge-Change vorgeschlagen werden.

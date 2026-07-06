# Tasks: course-run-booking

**Change-ID:** course-run-booking  
**Stand:** 2026-05-17

---

## Phasen-Übersicht

```
Phase 1: Backend (dev-php)
  T01 → T02 → T03 → T04
                   ↓
                   T05 → T06  → T07
                         T06b ↗
                                ↓
                               T08 (Tests)

  API-Freeze nach T06/T06b/T07 → Phase 2 kann starten

Phase 2: Frontend (dev-javascript)
  T09 (API-Layer) → T10, T11, T12, T13, T14 (parallel)
                                              ↓
                                            T15 (Tests)
```

---

## Phase 1: Backend

---

## T01: DB-Schema-Migrationen

- **Agent:** dev-php
- **Abhängigkeiten:** keine
- **Dateien:**
  - `backend/database/migrations/2026_05_17_000001_create_course_runs_table.php` (NEU)
  - `backend/database/migrations/2026_05_17_000002_add_course_run_id_to_sessions_and_bookings.php` (NEU)
  - `backend/database/migrations/2026_05_17_000003_migrate_data_to_course_runs.php` (NEU)
  - `backend/database/migrations/2026_05_17_000004_finalize_course_run_foreign_keys.php` (NEU)
  - `backend/database/migrations/2026_05_17_000005_drop_legacy_course_fields.php` (NEU)

### Beschreibung

Erstelle fünf Migrations-Dateien in der in `design.md` Abschnitt 2 definierten
Reihenfolge.

**M01** — `create_course_runs_table`:
```php
Schema::create('course_runs', function (Blueprint $table) {
    $table->id();
    $table->foreignId('course_id')->constrained()->onDelete('cascade');
    $table->date('start_date');
    $table->date('end_date')->nullable();
    $table->json('recurrence_rule')->nullable();
    $table->integer('total_sessions')->default(1);
    $table->integer('max_participants')->nullable();
    $table->enum('status', ['planned', 'active', 'completed', 'cancelled'])->default('planned');
    $table->text('notes')->nullable();
    $table->timestamps();
    $table->index('course_id');
    $table->index('status');
    $table->index('start_date');
});
```

**M02** — Füge `course_run_id` (nullable) zu `training_sessions` und `bookings`
hinzu. Noch kein FK-Constraint.

**M03** — Datenmigration (Eloquent-only, `DB::transaction()`):
- Pro `Course` mit `start_date !== null`: CourseRun anlegen mit Start/End-Datum,
  recurrence_rule, total_sessions aus Course
- `training_sessions.course_run_id` für alle Sessions des jeweiligen Kurses setzen
- `bookings.course_run_id` ableiten: über `training_sessions.course_run_id`
- `CourseRun.status` = bisheriger `Course.status` (1:1 Mapping)

**M04** — `course_run_id` in `training_sessions` und `bookings` auf NOT NULL setzen
+ FK constraints hinzufügen. Prüfe vorher ob NULL-Werte existieren (Exception werfen).

**M05** — Legacy-Felder entfernen:
- `training_sessions.course_id` (DROP COLUMN + INDEX)
- `bookings.training_session_id` — **Composite Index droppen (PFLICHT vor DROP COLUMN):**
  Migration `2026_01_04_180000_add_missing_indexes_for_performance.php` hat
  Index `bookings_training_session_id_status_index` angelegt.
  MySQL verweigert `DROP COLUMN` wenn noch ein Index auf der Spalte existiert.
  M05 muss als **erstes** diesen Index droppen:
  ```php
  $table->dropIndex('bookings_training_session_id_status_index');
  $table->dropColumn('training_session_id');
  ```
- `courses.start_date`, `end_date`, `recurrence_rule`, `total_sessions` (DROP COLUMN)
- `courses.status` ENUM ändern:
  1. `UPDATE courses SET status = 'active' WHERE status NOT IN ('active', 'archived')`
  2. `ALTER TABLE` / `$table->enum('status', ['active', 'archived'])` (über
     `$table->enum(...)->change()`)

> **Wichtig DB-Portabilität:** Alle Operationen via Laravel Blueprint-API.
> Kein raw `ALTER TABLE`-SQL außer für den ENUM-Change, der in MySQL und Postgres
> via `->change()` unterstützt wird (Laravel 10+: `doctrine/dbal` benötigt für
> `->change()`; prüfe ob es in `composer.json` vorhanden ist — falls nicht,
> alternatives Vorgehen: Spalte droppen und neu anlegen mit UPDATE-Daten davor
> in separatem Schritt).

### Akzeptanzkriterien

- [ ] `php artisan migrate:fresh` läuft ohne Fehler auf MySQL
- [ ] `php artisan migrate:fresh` läuft ohne Fehler auf PostgreSQL
- [ ] `php artisan migrate:rollback --step=5` läuft ohne Fehler
- [ ] Nach M03 haben alle `training_sessions` und `bookings` einen `course_run_id`
- [ ] Nach M05 sind alle Legacy-Spalten entfernt
- [ ] Keine Postgres- oder MySQL-spezifischen SQL-Konstrukte

---

## T02: `CourseRun`-Model + angepasste Models

- **Agent:** dev-php
- **Abhängigkeiten:** T01
- **Dateien:**
  - `backend/app/Models/CourseRun.php` (NEU)
  - `backend/app/Models/Course.php` (GEÄNDERT)
  - `backend/app/Models/TrainingSession.php` (GEÄNDERT)
  - `backend/app/Models/Booking.php` (GEÄNDERT)
  - `backend/database/factories/CourseRunFactory.php` (NEU)

### Beschreibung

**`CourseRun.php` (neu):**
- Fillable, casts (siehe design.md Abschnitt 3.1)
- Relationen: `course()`, `sessions()`, `bookings()`
- Methoden: `effectiveMaxParticipants()`, `isOpenGroup()`, `isFull()`,
  `availableSpots()`, `canAcceptNewBookings()`

**`Course.php` (angepasst):**
- Entferne aus `$fillable` + `casts()`: `start_date`, `end_date`,
  `recurrence_rule`, `total_sessions`
- Entferne `@property`-PHPDoc für diese Felder
- Füge Relation `runs(): HasMany` → `CourseRun` hinzu
- Entferne Relation `sessions()` (Sessions nur noch via CourseRun)
- Methode `isOpenGroup(): bool` hinzufügen

**`TrainingSession.php` (angepasst):**
- Ersetze `course_id` durch `course_run_id` in `$fillable` und PHPDoc
- Ersetze Relation `course()` durch `courseRun(): BelongsTo → CourseRun`
- `bookings(): HasMany` bleibt (für TrainingLog-Kontext)

**`Booking.php` (angepasst):**
- Ersetze `training_session_id` durch `course_run_id` in `$fillable` und PHPDoc
- Ersetze Relationen `session()` / `trainingSession()` durch `courseRun(): BelongsTo`
- `cancellationDeadline()`: Deadline via `courseRun->start_date` (mit
  `course->cancellation_deadline_hours`), eager-load `courseRun.course` wenn nötig
- PHPDoc und Methodenbeschreibung aktualisieren

**`CourseRunFactory.php` (neu):**
- Factory mit sinnvollen Defaults für Tests

### Akzeptanzkriterien

- [ ] `CourseRun::create()` legt Datensatz an
- [ ] `$course->runs` gibt Collection zurück
- [ ] `$courseRun->sessions` gibt Collection zurück
- [ ] `$booking->courseRun` gibt Objekt zurück
- [ ] `$booking->cancellationDeadline()` gibt korrekte Carbon-Instanz zurück
- [ ] `$courseRun->effectiveMaxParticipants()` fällt auf `course.max_participants` zurück
- [ ] `$courseRun->canAcceptNewBookings()` gibt false zurück wenn Run in Vergangenheit
  und nicht `open_group`
- [ ] `composer compat-check` meldet keine 8.3/8.4-Verstöße

---

## T03: `CourseRunController` + Resource + FormRequests + Route

- **Agent:** dev-php
- **Abhängigkeiten:** T02
- **Dateien:**
  - `backend/app/Http/Controllers/Api/CourseRunController.php` (NEU)
  - `backend/app/Http/Resources/CourseRunResource.php` (NEU)
  - `backend/app/Http/Requests/StoreCourseRunRequest.php` (NEU)
  - `backend/app/Http/Requests/UpdateCourseRunRequest.php` (NEU)
  - `backend/routes/api.php` (GEÄNDERT)

### Beschreibung

**`CourseRunController.php`:**

Methoden:
- `index(Course $course)` — Liste aller Runs eines Kurses; nur `status IN (planned, active)` für Customer
- `store(StoreCourseRunRequest $request, Course $course)` — Erstellt Run mit optionaler
  Session-Generierung via `CourseSessionService::generateFromRecurrence()` wenn
  `recurrenceRule` übergeben; nutzt `DB::transaction()`
- `show(CourseRun $courseRun)` — Einzelansicht
- `update(UpdateCourseRunRequest $request, CourseRun $courseRun)` — Update; gesperrt
  wenn confirmed Buchungen vorhanden
- `destroy(CourseRun $courseRun)` — Löschen; 422 wenn pending/confirmed Buchungen
- `sessions(CourseRun $courseRun)` — Sessions des Runs
- `bookings(CourseRun $courseRun)` — Buchungen des Runs (Trainer + Admin only)

**`CourseRunResource.php`:**  
Felder: id, courseId, startDate, endDate, totalSessions, maxParticipants,
effectiveMaxParticipants, availableSpots, isFull, canAcceptNewBookings, status,
notes, recurrenceRule, createdAt, updatedAt, sessions (whenLoaded), course (whenLoaded)

**FormRequests:**
- `StoreCourseRunRequest`: startDate required, endDate nullable, recurrenceRule nullable,
  totalSessions required integer, maxParticipants nullable integer, status
- `UpdateCourseRunRequest`: alle Felder optional (PATCH-Semantik)

**Routes in `api.php`:**
```php
// Nested unter Course
Route::get('/courses/{course}/runs', [CourseRunController::class, 'index']);
Route::post('/courses/{course}/runs', [CourseRunController::class, 'store']);

// Standalone CourseRun-Ressource
Route::get('/course-runs/{courseRun}', [CourseRunController::class, 'show']);
Route::put('/course-runs/{courseRun}', [CourseRunController::class, 'update']);
Route::delete('/course-runs/{courseRun}', [CourseRunController::class, 'destroy']);
Route::get('/course-runs/{courseRun}/sessions', [CourseRunController::class, 'sessions']);
Route::get('/course-runs/{courseRun}/bookings', [CourseRunController::class, 'bookings']);
```

### Akzeptanzkriterien

- [ ] `GET /api/v1/courses/{course}/runs` gibt 200 mit Runs zurück
- [ ] `POST /api/v1/courses/{course}/runs` mit recurrenceRule erstellt Run + Sessions
- [ ] `DELETE /api/v1/course-runs/{courseRun}` gibt 422 zurück wenn Buchungen vorhanden
- [ ] Customer sieht nur `planned`/`active` Runs
- [ ] Autorisierung: nur Trainer/Admin dürfen store/update/destroy

---

## T04: `CourseSessionService` anpassen

- **Agent:** dev-php
- **Abhängigkeiten:** T02
- **Dateien:**
  - `backend/app/Services/CourseSessionService.php` (GEÄNDERT)

### Beschreibung

- `syncSessions(Course $course, ...)` → `syncSessions(CourseRun $courseRun, array $sessions): array`
  - Setzt `course_run_id` statt `course_id` auf jede Session
  - Bestehende Sessions werden über `$courseRun->sessions()` geladen
  - Signatur-Änderung zieht Anpassungen in `CourseRunController::store()` und
    `CourseRunController::update()` nach sich (T03 muss aligniert sein)
- `generateFromRecurrence()` bleibt unverändert (pure function)

### Akzeptanzkriterien

- [ ] `syncSessions()` akzeptiert `CourseRun`-Instanz als ersten Parameter
- [ ] Erzeugte Sessions haben `course_run_id` gesetzt, kein `course_id`
- [ ] Bestehende Unit-Tests der Service-Methode müssen angepasst und grün sein

---

## T05: `CourseController` anpassen

- **Agent:** dev-php
- **Abhängigkeiten:** T02, T04
- **Dateien:**
  - `backend/app/Http/Controllers/Api/CourseController.php` (GEÄNDERT)
  - `backend/app/Http/Requests/StoreCourseRequest.php` (GEÄNDERT)
  - `backend/app/Http/Requests/UpdateCourseRequest.php` (GEÄNDERT)
  - `backend/app/Http/Resources/CourseResource.php` (GEÄNDERT)
  - `backend/app/Http/Controllers/Api/TrainingSessionController.php` (GEÄNDERT)
  - `backend/routes/api.php` (GEÄNDERT — zusätzlich zu T03)

### Beschreibung

**`CourseController.php`:**
- `store()`: Entferne Session-Generierungslogik (wird jetzt in `CourseRunController`)
  Entferne `start_date`, `end_date`, `recurrence_rule`, `total_sessions` aus
  dem Store-Prozess
- Entferne Methoden `sessions()`, `storeSession()`, `updateSession()`, `destroySession()`
  (werden durch `CourseRunController` ersetzt)
- `publicShow()`: Lade und include nested `runs` (nur `planned`/`active` + zukünftig)
- `index()` / `show()`: Entferne Session-eager-load

**Route-Cleanup in `routes/api.php`:**
- `POST /api/v1/courses/{course}/sessions` entfernen — Session-Anlage läuft
  jetzt ausschließlich über `CourseRunController` (T03). Der GET-Endpunkt
  entfällt ebenfalls (bereits als entfernt dokumentiert).

**`TrainingSessionController.php`:**
- Z. 64: `whereRaw('(SELECT COUNT(*) FROM bookings WHERE bookings.training_session_id = training_sessions.id …')`
  Nach M05 (DROP COLUMN `training_session_id`) bricht diese Query hart. Da
  Buchungen nach dem Change auf Run-Ebene liegen, wird die `availability`-Methode
  entfernt. Die Route `GET /training-sessions/{id}/availability` liefert eine
  leere/statische Antwort oder wird gelöscht (Entscheidung: Route entfernen, da
  Kapazitätsprüfung über `CourseRun.canAcceptNewBookings()` läuft).
- Route `GET /training-sessions/{id}/bookings` ebenfalls entfernen oder leere
  Collection zurückgeben (Buchungen sind auf CourseRun-Ebene).

**`StoreCourseRequest.php` / `UpdateCourseRequest.php`:**
- Entferne Regeln für `startDate`, `endDate`, `recurrenceRule`, `totalSessions`

**`CourseResource.php`:**
- Entferne Felder `startDate`, `endDate`, `recurrenceRule`, `totalSessions` aus `toArray()`
- Füge hinzu: `runs` via `CourseRunResource::collection($this->whenLoaded('runs'))`

### Akzeptanzkriterien

- [ ] `POST /api/v1/courses` ohne Datum-Felder erstellt Course erfolgreich
- [ ] `GET /api/v1/courses/{id}` enthält kein `startDate`/`endDate` mehr
- [ ] `GET /api/v1/public/courses/{id}` enthält nested `runs`
- [ ] Alte `sessions`-Routen unter `/courses/{id}/sessions` sind entfernt / geben 404
- [ ] `POST /api/v1/courses/{course}/sessions` gibt 404
- [ ] `GET /api/v1/training-sessions/{id}/availability` ist entfernt / gibt 404
- [ ] `GET /api/v1/training-sessions/{id}/bookings` ist entfernt / gibt leere Collection

---

## T06: `BookingController` + `StoreBookingRequest` + `BookingResource` anpassen

- **Agent:** dev-php
- **Abhängigkeiten:** T02
- **Dateien:**
  - `backend/app/Http/Controllers/Api/BookingController.php` (GEÄNDERT)
  - `backend/app/Http/Requests/StoreBookingRequest.php` (GEÄNDERT)
  - `backend/app/Http/Resources/BookingResource.php` (GEÄNDERT)

### Beschreibung

**`StoreBookingRequest.php`:**
- Ersetze Regel `trainingSessionId` durch `courseRunId`
  (`required`, `integer`, `exists:course_runs,id`)
- `validatedSnakeCase()`: gibt `course_run_id` zurück statt `training_session_id`

**`BookingController::store()`:**
- Lade `CourseRun` statt `TrainingSession`
- Kapazitätsprüfung: `$courseRun->canAcceptNewBookings()` und `$courseRun->isFull()`
- Mid-Run-Check: `$courseRun->start_date->isPast() && !$courseRun->isOpenGroup()`
  → 422: "Für diesen Kurs können keine Nachbuchungen gemacht werden."
- Duplikat-Check: `Booking::where('course_run_id', ...)->where('dog_id', ...)`
- Event dispatch: `BookingCreated::dispatch($booking)` — bleibt, aber Booking lädt
  `courseRun.course` statt `trainingSession.course`

**`BookingController::index()`:**
- Eager-load: `courseRun.course` statt `trainingSession.course`
- Trainer-Filter: `whereHas('courseRun.course', fn($q) => $q->where('trainer_id', ...))`
- Filter-Parameter: `courseRunId` statt `trainingSessionId`

**`BookingController::cancel()` / `approveCancellation()`:**
- Laden: `$booking->load('courseRun.course')` statt `trainingSession.course`
- Kein Logik-Change, nur andere Relation

**`BookingResource.php`:**
- Ersetze `trainingSessionId` durch `courseRunId`
- Ersetze `trainingSession` (whenLoaded) durch `courseRun` (whenLoaded)

### Akzeptanzkriterien

- [ ] `POST /api/v1/bookings` mit `courseRunId` erstellt Buchung
- [ ] `POST /api/v1/bookings` mit `trainingSessionId` gibt Validierungsfehler zurück
- [ ] Kapazitätscheck auf CourseRun-Ebene funktioniert
- [ ] Mid-Run-Check blockiert Buchung für vergangene nicht-open_group Runs
- [ ] open_group-Runs akzeptieren Buchungen auch wenn `start_date` in Vergangenheit
- [ ] Duplikat-Buchung für gleichen Hund + CourseRun wird abgelehnt
- [ ] `GET /api/v1/bookings` für Trainer filtert korrekt nach `courseRun.course.trainer_id`

---

## T06b: `DashboardController` anpassen

- **Agent:** dev-php
- **Abhängigkeiten:** T02
- **Dateien:**
  - `backend/app/Http/Controllers/Api/DashboardController.php` (GEÄNDERT)

### Beschreibung

Der `DashboardController` ist massiv von `booking->trainingSession->course`
abhängig und war im ursprünglichen Design vergessen. Alle Referenzen müssen
auf `courseRun` umgestellt werden.

**Eager-Loads anpassen:**
- `Booking::with(['trainingSession.course', …])` → `Booking::with(['courseRun.course', …])`

**Property-Zugriffe anpassen:**
- `$booking->trainingSession->course->name` → `$booking->courseRun->course->name`
- `$booking->trainingSession?->course?->name` → `$booking->courseRun?->course?->name`
- `$b->trainingSession?->session_date?->format(…)` → `$b->courseRun?->start_date?->format('d.m.Y')`
  (`session_date` existiert auf `CourseRun` nicht; das Äquivalent ist `start_date`)

**`whereHas`-Abfragen anpassen (Z. 165 ff.):**
```php
// Vorher:
Booking::whereHas('trainingSession', function ($query) use ($trainerCourses) {
    $query->whereIn('course_id', $trainerCourses);
})
// Nachher:
Booking::whereHas('courseRun', function ($query) use ($trainerCourses) {
    $query->whereIn('course_id', $trainerCourses);
})
```

**Raw JOIN ersetzen (Z. 260–261):**
```php
// Vorher (raw JOIN — bricht nach M05):
->join('training_sessions', 'bookings.training_session_id', '=', 'training_sessions.id')
->pluck('training_sessions.course_id')
// Nachher (Eloquent):
->with('courseRun')->get()->pluck('courseRun.course_id')
// oder eleganter:
->whereHas('courseRun', fn ($q) => $q->whereIn('course_id', $trainerCourses))
```

**`TrainingSession`-Abfragen für `$upcomingSessions`:**
- `TrainingSession::with(['course', 'bookings'])` → `TrainingSession::with(['courseRun.course'])`
  (Bookings hängen nicht mehr an Sessions, course nur noch via CourseRun erreichbar)

### Akzeptanzkriterien

- [ ] Admin-Dashboard lädt ohne Fehler (keine `training_session_id`-Referenzen mehr)
- [ ] Trainer-Dashboard lädt ohne Fehler
- [ ] Recent-Bookings werden mit korrektem Kursname dargestellt
- [ ] Buchungsanzahl und Auswertungen stimmen (keine NULL-Werte durch falsche Relation)
- [ ] Kein raw JOIN auf `training_sessions` mehr vorhanden

---

## T07: Events + Mail-Templates anpassen

- **Agent:** dev-php
- **Abhängigkeiten:** T02, T06
- **Dateien:**
  - `backend/app/Events/BookingCreated.php` (ggf. GEÄNDERT)
  - `backend/app/Mail/BookingConfirmation.php` (GEÄNDERT)
  - `backend/app/Mail/BookingCancellationApproved.php` (GEÄNDERT)
  - `backend/app/Console/Commands/TestEmailTemplates.php` (GEÄNDERT)
  - `backend/app/Console/Commands/SendTestEmail.php` (GEÄNDERT)
  - `backend/resources/views/emails/` (relevante Blade-Templates — prüfen)

### Beschreibung

- Lies alle Mail-Klassen und Blade-Templates die auf `trainingSession` oder
  `training_session` zugreifen
- Ersetze Session-Referenzen durch CourseRun-Referenzen
- Buchungsbestätigungs-Mail soll zeigen: Kursname, Durchlauf-Zeitraum
  (`start_date` – `end_date`), Anzahl Sessions, Preis gesamt

**Explizite Änderungen (vom Skeptiker bestätigt):**
- `Mail/BookingConfirmation.php` Z. 46: `$this->booking->trainingSession->course->name`
  → `$this->booking->courseRun->course->name`
- `Mail/BookingCancellationApproved.php` Z. 51: `$this->booking->trainingSession?->course?->name`
  → `$this->booking->courseRun?->course?->name`
- `Console/Commands/TestEmailTemplates.php` Z. 92: `$booking->setRelation('trainingSession', …)`
  → `$booking->setRelation('courseRun', …)`
- `Console/Commands/SendTestEmail.php` Z. 91: `Booking::with(['trainingSession.course', …])`
  → `Booking::with(['courseRun.course', …])`

> **Hinweis:** Erst die bestehenden Dateien lesen, dann gezielt nur das
> Notwendige ändern. Keine Umstrukturierung der Mail-Klassen.

### Akzeptanzkriterien

- [ ] `BookingCreated`-Event wird nach `POST /api/v1/bookings` dispatched
- [ ] Mail enthält Kursname + Durchlaufzeitraum (nicht einzelne Session)
- [ ] Kein Zugriff auf `trainingSession`-Relation in Mail/Event

---

## T08: Backend Feature-Tests

- **Agent:** dev-php
- **Abhängigkeiten:** T03, T04, T05, T06, T07
- **Dateien:**
  - `backend/tests/Feature/CourseRunControllerTest.php` (NEU)
  - `backend/tests/Feature/BookingControllerTest.php` (GEÄNDERT)
  - `backend/tests/Unit/CourseSessionServiceTest.php` (GEÄNDERT)

### Beschreibung

Schreibe / aktualisiere Tests gemäß `TESTING.md` (Pest-Engine, Factory-States,
HTTP-Assertions Laravel-Style, `expect()`-Syntax für Werte).

**`CourseRunControllerTest.php`:**
- `GET /courses/{id}/runs` → 200 für Auth-User
- `POST /courses/{id}/runs` → 201 für Trainer, 403 für Customer
- `DELETE /course-runs/{id}` → 204 ohne Buchungen, 422 mit Buchungen
- `POST /courses/{id}/runs` mit `recurrenceRule` → Sessions werden erzeugt

**`BookingControllerTest.php` (anpassen):**
- `POST /bookings` mit `courseRunId` → 201
- `POST /bookings` mit vollem Run → 422
- `POST /bookings` für vergangenen Run (non-open_group) → 422
- `POST /bookings` für vergangenen Run (open_group) → 201
- Duplikat-Buchung → 422
- Stornierung: Deadline via `courseRun.start_date`

**`CourseSessionServiceTest.php` (anpassen):**
- `syncSessions()` mit `CourseRun`-Instanz

### Akzeptanzkriterien

- [ ] `composer test` (oder `php artisan test`) läuft grün
- [ ] Alle neuen Endpunkte haben mindestens einen Happy-Path-Test
- [ ] Alle Fehlerszenarien aus T06-Akzeptanzkriterien sind getestet
- [ ] `composer qa` (lint + stan + compat-check + pest) läuft ohne Fehler

---

## Phase 2: Frontend

> **Voraussetzung für Phase 2:** T03 (API-Kontrakt) und T06 (Booking-API)
> müssen vollständig implementiert und in einer laufenden Docker-Umgebung
> erreichbar sein.

---

## T09: API Service Layer

- **Agent:** dev-javascript
- **Abhängigkeiten:** T03, T06 (API muss implementiert sein)
- **Dateien:**
  - `frontend/src/services/courseRunService.js` (NEU)
  - `frontend/src/services/bookingService.js` (GEÄNDERT)
  - `frontend/src/services/courseService.js` (ggf. GEÄNDERT)

### Beschreibung

**`courseRunService.js` (neu):**
```js
// Methoden:
// getRuns(courseId, params)       → GET /api/v1/courses/{id}/runs
// getCourseRun(courseRunId)       → GET /api/v1/course-runs/{id}
// createRun(courseId, data)       → POST /api/v1/courses/{id}/runs
// updateRun(courseRunId, data)    → PUT /api/v1/course-runs/{id}
// deleteRun(courseRunId)          → DELETE /api/v1/course-runs/{id}
// getRunSessions(courseRunId)     → GET /api/v1/course-runs/{id}/sessions
// getRunBookings(courseRunId)     → GET /api/v1/course-runs/{id}/bookings
```

**`bookingService.js` (anpassen):**
- `createBooking()`: Payload `courseRunId` statt `trainingSessionId`
- Filter-Methoden: `courseRunId` statt `trainingSessionId`

**`courseService.js` (ggf. anpassen):**
- Entferne `getSessions(courseId)` falls vorhanden (Route entfernt in T05)

### Akzeptanzkriterien

- [ ] Alle API-Methoden in `courseRunService.js` implementiert
- [ ] `bookingService.createBooking()` verwendet `courseRunId`
- [ ] Fehlerbehandlung: 422-Responses werden als Fehler propagiert

---

## T10: `CustomerBookingModal.vue` umbauen

- **Agent:** dev-javascript
- **Abhängigkeiten:** T09
- **Dateien:**
  - `frontend/src/components/CustomerBookingModal.vue` (GEÄNDERT)
  - `frontend/src/components/CourseRunSelector.vue` (NEU)

### Beschreibung

**`CourseRunSelector.vue` (neu):**
- Props: `courseId: number`, `modelValue: number | null`
- Emits: `update:modelValue`
- Lädt Runs via `courseRunService.getRuns(courseId)`
- Zeigt Runs als auswählbare Cards (Datum-Range, freie Plätze, Status)
- Zeigt Lade-Zustand und Leer-Zustand ("Keine Durchläufe verfügbar")

**`CustomerBookingModal.vue` (umbauen):**
- Entferne Session-Ladung via `GET /courses/{id}/sessions`
- Entferne Checkbox-Loop und Multi-Request-`for`-Schleife in `handleSubmit()`
- Integriere `<CourseRunSelector :courseId="courseId" v-model="selectedRunId" />`
- `handleSubmit()` schickt **einen** Request: `bookingService.createBooking({ courseRunId: selectedRunId, customerId, dogId })`
- Zeige Buchungs-Summary (Kursname, Durchlaufzeitraum, Preis gesamt)

### Akzeptanzkriterien

- [ ] Modal zeigt Durchlauf-Liste statt Session-Checkboxen
- [ ] Nur ein API-Request beim Buchen
- [ ] Wenn kein Durchlauf verfügbar: aussagekräftige Meldung
- [ ] Wenn Durchlauf ausgebucht: CTA disabled + Meldung "Ausgebucht"
- [ ] `npm run build` ohne Warnings

---

## T11: Admin Buchungsansicht — `BookingFormModal.vue`

- **Agent:** dev-javascript
- **Abhängigkeiten:** T09
- **Dateien:**
  - `frontend/src/components/BookingFormModal.vue` (GEÄNDERT)

### Beschreibung

- Ersetze Session-Dropdown durch zweistufige Auswahl:
  1. Kurs-Dropdown (existiert bereits)
  2. `<CourseRunSelector>` (aus T10) für den gewählten Kurs
- `courseRunId` wird an `bookingService.createBooking()` übergeben

### Akzeptanzkriterien

- [ ] Admin kann Kurs → Durchlauf → Buchung erstellen
- [ ] Nach Kurs-Wechsel wird Run-Selector zurückgesetzt und neu geladen
- [ ] Validation-Fehler vom Backend werden angezeigt

---

## T12: `CoursesView.vue` + `CourseDetailView.vue` anpassen

- **Agent:** dev-javascript
- **Abhängigkeiten:** T09
- **Dateien:**
  - `frontend/src/views/CoursesView.vue` (GEÄNDERT)
  - `frontend/src/views/CourseDetailView.vue` (GEÄNDERT)

### Beschreibung

**`CoursesView.vue`:**
- Entferne `startDate`/`endDate`-Anzeige auf Kurs-Karten (Felder existieren
  nicht mehr im API-Response)
- Zeige stattdessen: "N Durchläufe geplant" + frühestes Run-Datum
  (aus embedded `runs` wenn verfügbar, oder via separatem API-Call)

**`CourseDetailView.vue`:**
- Entferne direkte Session-Ansicht
- Lade und zeige Durchläufe des Kurses (`courseRunService.getRuns(courseId)`)
- Zeige für jeden Durchlauf: Datum-Range, freie Plätze, Buchungs-CTA
- CTA öffnet `CustomerBookingModal` mit vorausgewähltem `courseRunId`

### Akzeptanzkriterien

- [ ] Kurs-Karte zeigt kein Datum mehr (kein `null`-Fallback nötig)
- [ ] CourseDetailView zeigt Durchlauf-Liste
- [ ] Buchungs-CTA für einzelnen Durchlauf funktioniert

---

## T13: Admin Kursverwaltung — `CourseFormModal.vue` + `CourseRunFormModal.vue`

- **Agent:** dev-javascript
- **Abhängigkeiten:** T09
- **Dateien:**
  - `frontend/src/components/CourseFormModal.vue` (GEÄNDERT)
  - `frontend/src/components/CourseRunFormModal.vue` (NEU)

### Beschreibung

**`CourseFormModal.vue`:**
- Entferne alle Datum-Felder: `startDate`, `endDate`, `recurrenceRule`,
  `totalSessions` (diese Felder kommen vom Backend nicht mehr zurück)
- Template-Only-Formular: Name, Kurstyp, Trainer, Preis, Max-Teilnehmer,
  Stornierungsfrist, Beschreibung

**`CourseRunFormModal.vue` (neu):**
- Formular für neuen Durchlauf: `startDate` (required), `endDate` (optional),
  `recurrenceRule` (optional, mit UI aus `CourseRecurrenceForm.vue` falls
  wiederverwendbar), `totalSessions`, `maxParticipants` (optional), `status`
- `courseRunService.createRun(courseId, data)` beim Speichern

**Verwaltungsansicht:** Trainer-/Admin-Sicht soll Durchläufe eines Kurses
auflisten und "Neuer Durchlauf" + "Löschen"-Aktionen anbieten. Prüfe ob
es eine bestehende `CourseManagementView` o. ä. gibt und integriere dort.

### Akzeptanzkriterien

- [ ] Kurs anlegen ohne Datum-Felder funktioniert
- [ ] Durchlauf anlegen öffnet `CourseRunFormModal`, Run erscheint in Liste
- [ ] Durchlauf löschen mit Bestätigungs-Dialog; 422-Fehler bei Buchungen
  wird angezeigt

---

## T14: `BookingsView.vue` anpassen

- **Agent:** dev-javascript
- **Abhängigkeiten:** T09
- **Dateien:**
  - `frontend/src/views/BookingsView.vue` (GEÄNDERT)

### Beschreibung

- Buchungsliste: Statt "Session am [Datum]" zeige "Kursname (Start – End)"
- Lade-Pfad: `booking.courseRun.course.name` + `booking.courseRun.startDate`
- Filter-UI: `courseRunId`-Filter statt `trainingSessionId`

### Akzeptanzkriterien

- [ ] Buchungsliste zeigt Kursname + Durchlaufzeitraum
- [ ] Keine `null`/`undefined`-Darstellung für entfernte Felder

---

## T15: Frontend-Tests

- **Agent:** dev-javascript
- **Abhängigkeiten:** T10, T11, T12, T13, T14
- **Dateien:**
  - `frontend/src/components/__tests__/CourseRunSelector.test.js` (NEU)
  - `frontend/src/components/__tests__/CustomerBookingModal.test.js` (GEÄNDERT)
  - `frontend/src/services/__tests__/courseRunService.test.js` (NEU)

### Beschreibung

- `CourseRunSelector`: Rendert Runs, wählt einen aus, zeigt Leer-Zustand
- `CustomerBookingModal`: Buchungsflow mit gemocktem `courseRunService`
  und `bookingService` — ein einzelner Request wird abgeschickt
- `courseRunService`: Unit-Tests für alle Service-Methoden (mock `axios`/`fetch`)
- `npm run test` muss grün sein
- `npm run build` muss ohne Warnings durchlaufen

### Akzeptanzkriterien

- [ ] `npm run test` — alle Tests grün
- [ ] `npm run build` — 0 Warnings
- [ ] `CustomerBookingModal` schickt genau einen Booking-Request pro Buchung

---

## Abhängigkeits-Matrix

| Task | Wartet auf |
|------|-----------|
| T01  | — |
| T02  | T01 |
| T03  | T02 |
| T04  | T02 |
| T05  | T02, T04 |
| T06  | T02 |
| T06b | T02 |
| T07  | T02, T06 |
| T08  | T03, T04, T05, T06, T06b, T07 |
| T09  | T03, T06 (API fertig) |
| T10  | T09 |
| T11  | T09 |
| T12  | T09 |
| T13  | T09 |
| T14  | T09 |
| T15  | T10, T11, T12, T13, T14 |

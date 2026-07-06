# Design: course-run-booking

**Change-ID:** course-run-booking  
**Stand:** 2026-05-17

---

## 1. Datenmodell

### 1.1 Übersicht: Neue Entitätsstruktur

```
Course (Template)
  ├── name, course_type, trainer_id, price_per_session, max_participants
  │   cancellation_deadline_hours, description, status
  │
  └──< CourseRun (Durchlauf)
         ├── course_id (FK)
         ├── start_date, end_date, recurrence_rule, total_sessions, status
         │
         └──< TrainingSession
                ├── course_run_id (FK)  ← geändert von course_id
                ├── session_date, start_time, end_time, location
                └── trainer_id, max_participants, status

Booking
  ├── course_run_id (FK → course_runs)  ← geändert von training_session_id
  ├── customer_id, dog_id
  └── status, booking_date, attended, notes, cancellation_reason
```

### 1.2 Tabelle `course_runs` (NEU)

```sql
id               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY
course_id        BIGINT UNSIGNED NOT NULL
start_date       DATE NOT NULL
end_date         DATE NULL
recurrence_rule  JSON NULL
total_sessions   INT NOT NULL DEFAULT 1
max_participants INT NULL      -- Override; NULL = erbt course.max_participants
status           ENUM('planned','active','completed','cancelled') NOT NULL DEFAULT 'planned'
notes            TEXT NULL
created_at       DATETIME NULL
updated_at       DATETIME NULL

FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE
INDEX (course_id)
INDEX (status)
INDEX (start_date)
```

**Laravel-Migration:**

```php
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
```

### 1.3 Tabelle `courses` (GEÄNDERT)

**Zu entfernende Felder** (wandern zu `course_runs`):
- `start_date` (DATE)
- `end_date` (DATE, nullable)
- `recurrence_rule` (JSON, nullable)
- `total_sessions` (INT)

**Geändertes Feld:**
- `status` ENUM: Werte werden auf `['active', 'archived']` reduziert.
  Bestehende Werte `planned`, `active`, `completed`, `cancelled` → alle auf
  `active` gesetzt (Datenmigration), danach Enum-Änderung.

**Beibehalten** (unverändert):
- `id`, `trainer_id`, `name`, `description`, `course_type`, `max_participants`,
  `duration_minutes`, `price_per_session`, `cancellation_deadline_hours`,
  `created_at`, `updated_at`

**Hinweis DB-Portabilität:** MySQL erlaubt `ALTER TABLE … MODIFY COLUMN` für
ENUM-Änderungen. Die Migration muss zuerst alle Status-Werte auf `active` setzen
(UPDATE), dann die ENUM-Spalte neu definieren.

### 1.4 Tabelle `training_sessions` (GEÄNDERT)

**Geändert:**
- `course_id` wird durch `course_run_id` ersetzt

**Migrations-Sequenz:**
1. `course_run_id BIGINT UNSIGNED NULL` hinzufügen (nullable für Datenmigration)
2. Datenmigration: `course_run_id` für alle bestehenden Sessions setzen
3. `course_run_id` auf `NOT NULL` ändern + FK constraint
4. `course_id` droppen

### 1.5 Tabelle `bookings` (GEÄNDERT)

**Geändert:**
- `training_session_id` wird durch `course_run_id` ersetzt

**Migrations-Sequenz:**
1. `course_run_id BIGINT UNSIGNED NULL` hinzufügen (nullable für Datenmigration)
2. Datenmigration: `course_run_id` über `training_sessions` ableiten
3. `course_run_id` auf `NOT NULL` ändern + FK constraint
4. `training_session_id` droppen

---

## 2. Migrations-Strategie

**Gesamtstrategie:** 5 separate Migration-Dateien in deterministischer Reihenfolge.
Jede Migration ist für sich rollback-fähig. Kein irreversionaler `DROP COLUMN`
vor erfolgreicher Datenmigration.

### Migration M01: `create_course_runs_table`

Erstellt die `course_runs`-Tabelle (Schema aus 1.2).

### Migration M02: `add_course_run_id_to_sessions_and_bookings`

Fügt `course_run_id` (nullable) zu `training_sessions` und `bookings` hinzu.
Kein FK constraint noch nicht (Daten fehlen noch).

```php
// training_sessions
$table->unsignedBigInteger('course_run_id')->nullable()->after('course_id');

// bookings
$table->unsignedBigInteger('course_run_id')->nullable()->after('training_session_id');
```

### Migration M03: `migrate_data_to_course_runs`

**Datenmigration — kein Schema-Drop in dieser Migration.**

Algorithmus (PHP, Eloquent):
1. Für jeden `Course`-Datensatz mit `start_date != null`:
   - Lese `start_date`, `end_date`, `recurrence_rule`, `total_sessions`
   - Erstelle einen `CourseRun`-Datensatz mit diesen Werten + `course_id`,
     `status` = bisheriger `course.status` (mapped: `planned`→`planned`,
     `active`→`active`, `completed`→`completed`, `cancelled`→`cancelled`)
   - Setze `training_sessions.course_run_id` für alle Sessions dieses Kurses
   - Setze `bookings.course_run_id` für alle Bookings, die über die Sessions
     auf diesen Kurs zeigen (via `training_sessions.course_id`)

2. Für `Course`-Einträge **ohne** `start_date` (schon als Template geführt):
   - Kein `CourseRun` wird erstellt (korrekt — kein Durchlauf vorhanden)

**Rollback dieser Migration:** Setzt `course_run_id` in Sessions und Bookings
zurück auf NULL, löscht erstellte `CourseRun`-Einträge.

> **Hinweis für dev-php:** Diese Migration läuft Eloquent-basiert (kein raw SQL)
> und ist MySQL+Postgres-kompatibel. Verwende `DB::transaction()` für Atomarität.

### Migration M04: `finalize_course_run_foreign_keys`

- `training_sessions.course_run_id`: Nullable → NOT NULL
- FK constraint: `course_run_id` → `course_runs.id` ON DELETE CASCADE
- `bookings.course_run_id`: Nullable → NOT NULL
- FK constraint: `course_run_id` → `course_runs.id` ON DELETE CASCADE

**Voraussetzung:** M03 muss vollständig erfolgreich gewesen sein (alle
`course_run_id`-Felder befüllt). Die Migration prüft, ob NULL-Werte vorhanden
sind, und wirft eine Exception wenn ja.

### Migration M05: `drop_legacy_course_fields`

Entfernt die nun redundanten Felder:
- `training_sessions.course_id` (DROP COLUMN + DROP INDEX)
- `bookings.training_session_id` (DROP COLUMN + DROP INDEX)
- `courses.start_date`, `courses.end_date`, `courses.recurrence_rule`,
  `courses.total_sessions` (DROP COLUMN jeweils)
- `courses.status` ENUM: Zuerst UPDATE `SET status = 'active'` für alle Nicht-
  `archived`-Werte, dann MODIFY COLUMN auf `ENUM('active','archived')`

> **Hinweis DB-Portabilität (MySQL):** `ALTER TABLE … DROP COLUMN` muss für jede
> Spalte einzeln aufgerufen werden wenn mehrere Spalten gleichzeitig geändert
> werden. Laravel's `Schema::table()` mit mehreren `->dropColumn(['a','b'])`
> funktioniert in Eloquent korrekt und ist DB-agnostisch.

---

## 3. Domain-Modell (PHP)

### 3.1 Neues Model: `CourseRun`

**Datei:** `backend/app/Models/CourseRun.php`

```
Fillable: course_id, start_date, end_date, recurrence_rule, total_sessions,
          max_participants, status, notes

Casts: start_date → date, end_date → date, recurrence_rule → array,
       total_sessions → integer, max_participants → integer

Relationen:
  course(): BelongsTo → Course
  sessions(): HasMany → TrainingSession
  bookings(): HasMany → Booking

Methoden:
  effectiveMaxParticipants(): int
    → return $this->max_participants ?? $this->course->max_participants;

  isOpenGroup(): bool
    → return $this->course->course_type === 'open_group';

  isFull(): bool
    → Buchungen mit status pending|confirmed zählen gegen effectiveMaxParticipants()

  availableSpots(): int
    → effectiveMaxParticipants() minus aktive Buchungen

  canAcceptNewBookings(): bool
    → !isFull() && (isOpenGroup() || start_date->isFuture() || start_date->isToday())
```

### 3.2 Angepasstes Model: `Course`

**Datei:** `backend/app/Models/Course.php`

- `$fillable`: Entferne `start_date`, `end_date`, `recurrence_rule`, `total_sessions`
- `casts()`: Entferne entsprechende Casts
- `@property`-PHPDoc: Entferne Start/End-Datumsfelder
- Neue Relation: `runs(): HasMany → CourseRun`
- `sessions()`-Relation wird entfernt (Sessions gibt es nur noch über CourseRun)
- `isActive()`: Prüft `status === 'active'` (nicht mehr `active/planned`)
- `isOpenGroup(): bool` → `$this->course_type === 'open_group'`

### 3.3 Angepasstes Model: `TrainingSession`

- `$fillable`: Ersetze `course_id` durch `course_run_id`
- `casts()`: Passe entsprechend an
- Neue Relation: `courseRun(): BelongsTo → CourseRun`
- Entferne Relation: `course()` — Zugriff jetzt via `trainingSession->courseRun->course`
- Bestehende Relation `bookings(): HasMany` bleibt erhalten (Sessions haben noch
  immer Buchungskontext für TrainingLog / Attendance)

> **Hinweis:** `Booking` zeigt nicht mehr auf `TrainingSession`. Die `bookings()`-
> Relation auf `TrainingSession` wird daher voraussichtlich nur noch für
> `TrainingLog`-Verknüpfungen benötigt und kann vorerst bleiben.

### 3.4 Angepasstes Model: `Booking`

- `$fillable`: Ersetze `training_session_id` durch `course_run_id`
- Neue Relation: `courseRun(): BelongsTo → CourseRun`
- Entferne Relation: `session()` / `trainingSession()`
- Angepasste Methode `cancellationDeadline()`:
  ```
  Deadline = courseRun.start_date - course.cancellation_deadline_hours
  (lädt courseRun mit course eager wenn nötig)
  ```
- `isCancellationAllowed()`: Keine Änderung der Logik, nur neue Datenquelle

---

## 4. API-Kontrakt

> **Dieser Abschnitt ist der verbindliche Übergabepunkt zwischen Backend (dev-php)
> und Frontend (dev-javascript). Frontend-Tasks T09–T15 dürfen erst beginnen,
> wenn T06 (CourseRunController) vollständig implementiert ist.**

### 4.1 Course-Endpunkte (angepasst)

**`GET /api/v1/courses`**  
Keine Änderung am Route-Pfad. Response-Änderung: `startDate`, `endDate`,
`recurrenceRule`, `totalSessions` entfallen aus `CourseResource`.
Neue Felder: `runs` (nur wenn `?include=runs` übergeben wird, lazy-loaded).

**`POST /api/v1/courses`**  
Request: Entferne `startDate`, `endDate`, `recurrenceRule`, `totalSessions`.  
Response: Wie oben.

**`GET /api/v1/public/courses/{course}`**  
Response: `runs` wird als nested Array mitgeliefert (nur Runs mit
`status IN (planned, active)` und `start_date >= heute`).

**`GET /api/v1/courses/{course}/sessions`**  
**ENTFERNT.** Ersetzt durch `GET /api/v1/course-runs/{courseRun}/sessions`.

### 4.2 CourseRun-Endpunkte (NEU)

**`GET /api/v1/courses/{course}/runs`**

Response:
```json
{
  "data": [
    {
      "id": 1,
      "courseId": 1,
      "startDate": "2026-05-04",
      "endDate": "2026-05-25",
      "totalSessions": 4,
      "maxParticipants": 8,
      "effectiveMaxParticipants": 8,
      "availableSpots": 3,
      "isFull": false,
      "canAcceptNewBookings": true,
      "status": "planned",
      "notes": null,
      "recurrenceRule": { "type": "weekly", "weekday": 1, ... },
      "sessions": [...],  // nur wenn ?include=sessions
      "createdAt": "...",
      "updatedAt": "..."
    }
  ]
}
```

**`POST /api/v1/courses/{course}/runs`** (Trainer + Admin)

Request:
```json
{
  "startDate": "2026-05-04",
  "endDate": "2026-05-25",
  "recurrenceRule": { "type": "weekly", "weekday": 1, "startTime": "18:00", "endTime": "19:00", "count": 4 },
  "totalSessions": 4,
  "maxParticipants": null,
  "status": "planned",
  "notes": null
}
```

Response: `CourseRunResource` (201 Created)

**`GET /api/v1/course-runs/{courseRun}`**

Response: `CourseRunResource` mit nested `course` und `sessions`.

**`PUT /api/v1/course-runs/{courseRun}`** (Trainer + Admin)

Request: Gleiche Felder wie POST (außer `courseId`).  
Einschränkung: Nur wenn keine bestätigten Buchungen vorhanden.

**`DELETE /api/v1/course-runs/{courseRun}`** (Trainer + Admin)

Löscht Durchlauf. Nur wenn keine Buchungen mit `status pending|confirmed`
vorhanden. Gibt 422 zurück wenn Buchungen existieren.

**`GET /api/v1/course-runs/{courseRun}/sessions`**

Response: Array von `TrainingSessionResource`.

**`GET /api/v1/course-runs/{courseRun}/bookings`** (Trainer + Admin)

Response: Paginierte `BookingResource`-Collection.

### 4.3 Booking-Endpunkte (angepasst)

**`POST /api/v1/bookings`**

Request (geändert):
```json
{
  "courseRunId": 1,
  "customerId": 42,
  "dogId": 7,
  "notes": null
}
```

Validierungen:
- `courseRunId` muss existieren und `canAcceptNewBookings()` sein
- Kein Duplikat: Hund ist noch nicht für diesen Run gebucht
- `dogId` gehört zu `customerId`
- Mid-Run-Check: `courseRun.start_date` nicht in der Vergangenheit,
  außer `courseRun.isOpenGroup() === true`

Response: `BookingResource` (geändert):
```json
{
  "id": 1,
  "courseRunId": 1,
  "customerId": 42,
  "dogId": 7,
  "bookingDate": "...",
  "status": "pending",
  "attended": false,
  "cancellationReason": null,
  "notes": null,
  "isConfirmed": false,
  "isCancelled": false,
  "isCancellationRequested": false,
  "cancellationDeadline": "...",
  "isCancellationAllowed": true,
  "createdAt": "...",
  "updatedAt": "...",
  "courseRun": { ... },
  "customer": { ... },
  "dog": { ... }
}
```

**`GET /api/v1/bookings`** (angepasst)

Filter-Parameter: `courseRunId` statt `trainingSessionId`.  
Eager-Load: `courseRun.course` statt `trainingSession.course`.  
Trainer-Sicht: Filtert nach `courseRun.course.trainer_id`.

**`POST /api/v1/bookings/{booking}/cancel`** (unverändert strukturell)

Deadline-Berechnung: `courseRun.start_date - course.cancellation_deadline_hours`.

### 4.4 Entfernte Endpunkte

| Alter Endpunkt | Grund | Ersatz |
|---|---|---|
| `GET /api/v1/courses/{course}/sessions` | Sessions jetzt unter CourseRun | `GET /api/v1/course-runs/{id}/sessions` |
| `POST /api/v1/courses/{course}/sessions` | Sessions jetzt unter CourseRun | `POST /api/v1/course-runs/{id}/sessions` (oder via CourseRun-Create mit Sessions) |
| Filter `trainingSessionId` in `GET /bookings` | FK entfernt | `courseRunId` |

**OP-E (neu):** `GET /training-sessions/{id}/bookings` und `GET /training-sessions/{id}/availability`
werden nach dem Change semantisch inkorrekt. Buchungen sind auf CourseRun-Ebene, nicht mehr
Session-Ebene. Beide Routes werden in diesem Change entfernt bzw. geben eine leere Collection
zurück. Availability-Checks laufen über `CourseRun.canAcceptNewBookings()`.
Cleanup der `TrainingSessionController`-Methoden ist Teil von T05.

---

## 5. Service-Schicht

### `CourseSessionService` (angepasst)

**Datei:** `backend/app/Services/CourseSessionService.php`

- `generateFromRecurrence()`: **Keine Änderung** — pure function, DB-unabhängig.
  Output-Array enthält kein `course_id`-Feld mehr (es wurde nie bewusst gesetzt,
  aber if vorhanden: entfernen).
- `syncSessions(Course $course, ...)`: Signatur ändert sich zu
  `syncSessions(CourseRun $courseRun, array $sessions): array`
  - Setzt `course_run_id` statt `course_id` auf Sessions
  - Sucht bestehende Sessions via `$courseRun->sessions()` statt `$course->sessions()`

### Neuer Service: `CourseRunBookingService` (optional)

Kapselt die Buchungslogik für einen CourseRun:
- `canBook(CourseRun $run, int $dogId): bool`
- `isDuplicateBooking(CourseRun $run, int $dogId): bool`

> **Abwägung:** Wenn `BookingController::store()` nach dem Umbau übersichtlich
> bleibt (< 60 Zeilen), ist kein separater Service nötig. dev-php entscheidet
> im Zuge von T07.

---

## 6. Events & Mail

### `BookingCreated`-Event (angepasst)

Das Event enthält das `Booking`-Modell. Die `Booking`-Klasse lädt nach dem
Change die Relation `courseRun.course` statt `trainingSession.course`.

**Mail-Template** (`resources/views/emails/booking-confirmation.blade.php`
oder ähnlich — dev-php prüft den genauen Pfad): Ersetze Session-Details
durch Run-Details (Start- und Enddatum des Durchlaufs, Anzahl Sessions).

---

## 7. Frontend-Umbau

### 7.1 `CustomerBookingModal.vue` — neuer Buchungs-Flow

**Aktueller Flow:**
1. Modal öffnet sich mit `courseId`
2. Lädt Sessions via `GET /api/v1/courses/{id}/sessions`
3. Zeigt Checkboxen pro Session
4. Schickt n separate `POST /api/v1/bookings`-Requests

**Neuer Flow:**
1. Modal öffnet sich mit `courseId`
2. Lädt Runs via `GET /api/v1/courses/{id}/runs`
3. Zeigt Durchlauf-Liste (Datum, Plätze, Status)
4. Kund:in wählt **einen** Durchlauf aus (Radio-Button / Card-Selection)
5. Schickt **einen** `POST /api/v1/bookings`-Request mit `courseRunId`

**Wichtig:** Wenn nur ein Durchlauf verfügbar ist und dieser buchbar ist,
kann er vorausgewählt sein (UX-Vereinfachung).

### 7.2 `BookingFormModal.vue` (Admin) — Run-Selector

Session-Dropdown → CourseRun-Dropdown (zeigt Kurs + Datum-Range):
1. Trainer wählt Kurs
2. System lädt Runs: `GET /api/v1/courses/{id}/runs`
3. Trainer wählt Run
4. `POST /api/v1/bookings` mit `courseRunId`

### 7.3 `CoursesView.vue` — Template-Ansicht

- Kurs-Karte zeigt kein Datum mehr (es ist ein Template)
- Kurs-Karte zeigt: "N Durchläufe geplant", nächster Run-Starttermin
  (aus embedded `runs`-Array oder separatem API-Call)

### 7.4 `CourseDetailView.vue` — Run-Auswahl

- Zeigt Kurs-Template-Infos (Name, Typ, Trainer, Preis)
- Zeigt Liste der Durchläufe mit Datum, freien Plätzen, Status
- Buchungs-CTA öffnet `CustomerBookingModal` (oder direkte Inline-Buchung)

### 7.5 Neue Komponente: `CourseRunSelector.vue`

Wiederverwendbare Komponente für die Run-Auswahl. Props:
- `courseId: number`
- `modelValue: number | null` (selected courseRunId)

Emits: `update:modelValue`

Lädt Runs selbst via `GET /api/v1/courses/{courseId}/runs`.

### 7.6 Admin: `CourseFormModal.vue` + neue `CourseRunFormModal.vue`

- `CourseFormModal.vue`: Entferne alle Datum-Felder (`startDate`, `endDate`,
  `recurrenceRule`, `totalSessions`). Template-only.
- Neue `CourseRunFormModal.vue`: Formular für einen konkreten Durchlauf
  (Datum, Wiederholungsregel, Status). Analog zum bisherigen Datum-Teil
  von `CourseFormModal.vue`.

### 7.7 `BookingsView.vue` (Admin)

- Buchungsliste zeigt "Kursname (Zeitraum)" statt "Session am Datum"
- Filter: `courseRunId` statt `trainingSessionId`

---

## 8. Offene Punkte (dokumentiert, nicht blockierend)

### OP-A: CourseRun-Löschung mit Buchungen
Wenn ein Trainer versucht, einen Durchlauf mit bestehenden Buchungen zu löschen:
→ 422-Response mit Fehlermeldung. Kund:innen werden **nicht** automatisch
benachrichtigt. Manuelle Stornierung der Buchungen durch Trainer/Admin ist
Voraussetzung vor dem Löschen. (Erweiterung für später: Auto-Benachrichtigung.)

### OP-B: Price-per-Run
Aktuell gibt es nur `price_per_session`. Der Gesamtpreis eines Runs ergibt
sich aus `price_per_session * total_sessions`. Dieses Modell wird beibehalten.
Eine `price_per_run`-Override-Möglichkeit auf `CourseRun`-Ebene ist nicht
in diesem Change.

### OP-C: Warteliste
Keine Warteliste in diesem Change. Response bei vollem Run: HTTP 422 mit
`{"message": "Dieser Durchlauf ist ausgebucht.", "isFull": true}`.

### OP-D: TrainingSession.bookings()-Relation
Nach dem Change zeigt `Booking` nicht mehr auf `TrainingSession`. Die
`bookings(): HasMany`-Relation auf `TrainingSession` wird damit leer bleiben.
Sie kann für `TrainingLog`-Attendance-Tracking relevant sein. Entfernung
dieser Relation ist separater Cleanup-Change.

---

## 9. DB-Portabilitäts-Prüfliste

| Check | Status |
|---|---|
| Keine Postgres-spezifischen Operatoren in Migrations | ✅ |
| `$table->json()` statt `$table->jsonb()` | ✅ |
| `$table->id()` für PKs | ✅ |
| `$table->foreignId()` für FKs | ✅ |
| Enum-Änderung über `ALTER TABLE` / Eloquent | ✅ (siehe M05) |
| Datenmigration via Eloquent, kein raw SQL | ✅ |
| ENUM-Werte: MySQL und Postgres kompatibel | ✅ |

## 10. PHP-Kompatibilitäts-Prüfliste

| Check | Status |
|---|---|
| Kein Property Hooks (PHP 8.4) | ✅ |
| Kein Typed Class Constants (PHP 8.3) | ✅ |
| Kein `#[\Override]` (PHP 8.3) | ✅ |
| `casts()` als Methode (Laravel 11+, PHP 8.2 ok) | ✅ |
| Kein `json_validate()` (PHP 8.3) | ✅ |
| `declare(strict_types=1)` in allen neuen Dateien | ✅ |

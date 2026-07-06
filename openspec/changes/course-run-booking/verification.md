# Verification Report — course-run-booking
**Datum:** 17.05.2026  
**Skeptiker:** skeptic (manuell, GitHub Copilot)

---

## Ergebnis: FREIGABE MIT AUFLAGEN

Das Design ist grundsätzlich korrekt und umsetzbar. Drei vergessene
Abhängigkeiten müssen in `tasks.md` ergänzt werden, bevor Implementierung
startet (Blockierer 1–3). Fünf weitere Punkte sind als Auflagen festgehalten.

---

## Geprüfte Annahmen

### Datenmodell

| Annahme | Status | Befund |
|---------|--------|--------|
| `courses` hat `start_date` (DATE NOT NULL) | ✅ bestätigt | `2025_12_22_184818_create_courses_table.php` Z. 26 |
| `courses` hat `end_date` (DATE NULLABLE) | ✅ bestätigt | `2025_12_22_184818_create_courses_table.php` Z. 27 |
| `courses` hat `total_sessions` (INT) | ✅ bestätigt | `2025_12_22_184818_create_courses_table.php` Z. 23 |
| `courses` hat `recurrence_rule` (JSON NULLABLE) | ✅ bestätigt | `2026_05_14_000001_add_recurrence_rule_to_courses_table.php` |
| `courses` hat `cancellation_deadline_hours` | ✅ bestätigt | `2026_05_04_110000_add_cancellation_deadline_to_courses_table.php` |
| `courses.status` ENUM = `['planned','active','completed','cancelled']` | ✅ bestätigt | Basis-Migration Z. 28 |
| `training_sessions` hat `course_id` (nullable FK) | ✅ bestätigt | `2025_12_22_184838_create_sessions_table.php` Z. 16 — **ON DELETE SET NULL** |
| `training_sessions` hat `trainer_id` | ✅ bestätigt | Z. 17 — separates Feld, Design erwähnt es nicht, aber kein Problem |
| `training_sessions` hat eigenes `max_participants` | ✅ bestätigt | Z. 25 — nicht in Design diskutiert (Auflage 6) |
| `bookings` hat `training_session_id` FK | ✅ bestätigt | `2025_12_22_184856_create_bookings_table.php` Z. 15 |
| `bookings` hat `cancellation_requested`-Status | ✅ bestätigt | `2026_05_04_110001_…php` |
| Kein `course_run`-Konzept vorhanden | ✅ bestätigt | Grep: null Treffer auf `course_runs`, `CourseRun` |
| Composite Index `(training_session_id, status)` auf `bookings` | ✅ bestätigt | `2026_01_04_180000_add_missing_indexes_for_performance.php` Z. 77–78 — **im Design nicht erwähnt** (Blockierer 3) |

### Controller / Services

| Annahme | Status | Befund |
|---------|--------|--------|
| `BookingController::store()` bucht eine Session | ✅ bestätigt | `BookingController.php` Z. 125 |
| `BookingController` filtert nach `trainingSessionId` | ✅ bestätigt | Z. 75–76 |
| `CourseSessionService::generateFromRecurrence()` existiert | ✅ bestätigt | `CourseSessionService.php` |
| `CourseSessionService::syncSessions()` nimmt `Course` als Param | ✅ bestätigt | Z. 137: `['course_id' => $course->id]` — Signatur muss angepasst werden |
| `BookingCreated`-Event wird gefeuert | ⚠️ nicht direkt geprüft | Events-Ordner nicht vollständig gelesen; Mail-Klassen bestätigen indirekten Bezug |
| `DashboardController` ist von `training_session_id` abhängig | ✅ bestätigt | Z. 86, 95, 128, 137, 165, 187–217, 260–261 — **MASSIV betroffen, komplett vergessen** (Blockierer 1) |
| `TrainingSessionController` hat `whereRaw` mit `bookings.training_session_id` | ✅ bestätigt | `TrainingSessionController.php` Z. 64 (Blockierer 2) |

### Routes

| Annahme | Status | Befund |
|---------|--------|--------|
| `GET /api/v1/courses/{course}/sessions` existiert | ✅ bestätigt | `routes/api.php` Z. 129 |
| `POST /api/v1/courses/{course}/sessions` existiert | ✅ bestätigt | `routes/api.php` Z. 130 — **im Design vergessen** (Blockierer 2) |
| `GET /api/v1/training-sessions/{id}/bookings` existiert | ✅ bestätigt | Z. 118 — wird nach dem Change semantisch leer; Entscheidung fehlt (Auflage 3) |
| `GET /api/v1/training-sessions/{id}/availability` existiert | ✅ bestätigt | Z. 119 — nutzt `whereRaw` mit `bookings.training_session_id` (Auflage 3) |

### Mail-Klassen

| Annahme | Status | Befund |
|---------|--------|--------|
| `BookingConfirmation.php` nutzt `trainingSession->course->name` | ✅ bestätigt | `Mail/BookingConfirmation.php` Z. 46 — muss auf `courseRun->course->name` umgestellt werden (Auflage 2) |
| `BookingCancellationApproved.php` nutzt `trainingSession?->course?->name` | ✅ bestätigt | `Mail/BookingCancellationApproved.php` Z. 51 (Auflage 2) |
| Console-Commands nutzen `trainingSession`-Relation | ✅ bestätigt | `TestEmailTemplates.php` Z. 92, `SendTestEmail.php` Z. 91 (Auflage 5) |

### PHP-8.2-Kompatibilität

| Annahme | Status | Befund |
|---------|--------|--------|
| Design enthält keine Typed Class Constants | ✅ bestätigt | Keine gefunden |
| Design enthält kein `#[\Override]` | ✅ bestätigt | Nicht verwendet |
| Design enthält keine Property Hooks | ✅ bestätigt | Nicht verwendet |
| Design enthält keine `readonly`-Klassen (nur Properties) | ✅ bestätigt | `readonly CourseSessionService` in Konstruktor ist ok (8.1+) |

### MySQL/Postgres-Portabilität

| Annahme | Status | Befund |
|---------|--------|--------|
| `$table->json()` statt `$table->jsonb()` für recurrence_rule | ✅ bestätigt | Design Z. 57: `$table->json(…)` |
| Keine DB-spezifischen ENUM-Änderungen in Migrations | ⚠️ teilweise | Design M05 erwähnt `MODIFY COLUMN` für ENUM — muss wie `2026_05_04_110001` mit DB-Driver-Switch umgesetzt werden. Existierendes Beispiel im Repo vorhanden. |
| Datenmigration M03 via Eloquent (kein raw SQL) | ✅ bestätigt | Design schreibt Eloquent-basiert vor |
| Kein `ON CONFLICT` / `RETURNING` | ✅ bestätigt | Nicht verwendet |

---

## Kritische Befunde (BLOCKIERER)

### Blockierer 1 — `DashboardController` komplett vergessen

**Datei:** `backend/app/Http/Controllers/Api/DashboardController.php`

Der Controller ist **massiv** von `booking->trainingSession->course` abhängig
und fehlt in `tasks.md` komplett:

- Z. 86: `Booking::with(['customer.user', 'dog', 'trainingSession.course'])`
- Z. 95: `$booking->trainingSession->course->name`
- Z. 128: `Booking::with(['customer.user', 'dog', 'trainingSession.course'])`
- Z. 137: `$b->trainingSession?->course?->name`
- Z. 138: `$b->trainingSession?->session_date?->format(…)`
- Z. 165: `Booking::whereHas('trainingSession', …)`
- Z. 187–199: Trainer-Sicht auf Bookings via `trainingSession`
- Z. 260–261: **raw JOIN** `bookings.training_session_id = training_sessions.id`
  und `.pluck('training_sessions.course_id')` — nach M05 bricht das hart.

**Erforderliche Aktion:** Task T06 oder neues T06b für DashboardController
hinzufügen. Betrifft Admin-Dashboard, Trainer-Dashboard und Auswertungen.

---

### Blockierer 2 — Zwei Routes vergessen

**Datei:** `backend/routes/api.php` Z. 130

`POST /api/v1/courses/{course}/sessions` existiert und fehlt in den
"Entfernten Endpunkten" des Designs (nur GET wurde dokumentiert). Da
Session-Erstellung jetzt über CourseRun laufen soll, muss auch diese Route
entweder entfernt oder auf den neuen Flow umgeleitet werden.

Außerdem: `TrainingSessionController.php` Z. 64 enthält:
```php
->whereRaw('(SELECT COUNT(*) FROM bookings WHERE bookings.training_session_id = training_sessions.id …)')
```
Nach M05 (DROP COLUMN `training_session_id`) bricht diese Query. Sie ist
**nicht in tasks.md erwähnt**. Muss in Task T06 oder T03 adressiert werden.

**Erforderliche Aktion:** Tasks.md ergänzen: `POST /courses/{course}/sessions`
entfernen, `TrainingSessionController` availability-Logik anpassen.

---

### Blockierer 3 — Composite Index nicht in M05 berücksichtigt

Migration `2026_01_04_180000_add_missing_indexes_for_performance.php` legt an:
```php
$table->index(['training_session_id', 'status']); // bookings_training_session_id_status_index
```

Migration M05 dropt `training_session_id`, aber **erwähnt den Composite Index
nicht**. Auf MySQL schlägt `DROP COLUMN` fehl wenn noch ein Index auf der Spalte
existiert. Die down()-Migration dieses alten Index-Eintrags (Z. 173) zeigt den
korrekten Namen: `bookings_training_session_id_status_index`.

**Erforderliche Aktion:** M05 muss explizit zuerst diesen Index droppen:
```php
$table->dropIndex('bookings_training_session_id_status_index');
$table->dropColumn('training_session_id');
```

---

## Auflagen (nicht blockierend)

### Auflage 1 — `DashboardController` Z. 260 raw JOIN

Neben dem Eager-Load-Problem (Blockierer 1) enthält Z. 260 einen raw JOIN:
```php
->join('training_sessions', 'bookings.training_session_id', '=', 'training_sessions.id')
->pluck('training_sessions.course_id')
```
`course_id` gibt es auf `training_sessions` nach dem Change nicht mehr (wird
durch `course_run_id` ersetzt). Muss auf Eloquent-Relation umgestellt werden:
`->with('courseRun.course')` o.ä.

### Auflage 2 — Mail-Klassen (nicht nur Blade-Templates)

Tasks.md T07 erwähnt "Mail-Templates" — gemeint sind die Blade-Views, aber
auch die **Mail-PHP-Klassen** müssen angepasst werden:
- `Mail/BookingConfirmation.php` Z. 46: Subject via `trainingSession->course->name`
- `Mail/BookingCancellationApproved.php` Z. 51: Subject via `trainingSession?->course?->name`

Beide müssen auf `courseRun->course->name` umgestellt werden.

### Auflage 3 — `GET /api/v1/training-sessions/{id}/bookings` wird semantisch leer

Nach dem Change zeigt `Booking` auf `CourseRun`, nicht mehr auf `TrainingSession`.
Die Route `GET /training-sessions/{trainingSession}/bookings` (Z. 118) und die
`availability`-Route (Z. 119) werden semantisch falsch oder leer. Design muss
entscheiden: Entfernen oder Hinweis als `deprecated`? Derzeit unerwähnt.

### Auflage 4 — `courses.status`-Enum-Reduktion braucht DB-Driver-Switch

M05 reduziert `courses.status` von `['planned','active','completed','cancelled']`
auf `['active','archived']`. Das existierende Migrations-Beispiel im Repo
(`2026_05_04_110001_…php`) zeigt das Pattern mit MySQL/Postgres/SQLite-Branch.
dev-php muss dasselbe Pattern anwenden — nicht einfach `DB::statement('ALTER…')`.

### Auflage 5 — Console-Commands

- `Console/Commands/TestEmailTemplates.php` Z. 92: `$booking->setRelation('trainingSession', …)`
- `Console/Commands/SendTestEmail.php` Z. 91: `Booking::with(['trainingSession.course', …])`

Diese müssen nach dem Change auf `courseRun.course` umgestellt werden.
Nicht in tasks.md. Kann in T07 (Events + Mail) mit erledigt werden.

### Auflage 6 — `TrainingSession.max_participants` vs. `CourseRun.max_participants`

`TrainingSession` hat ein eigenes `max_participants`-Feld (default 10).
`CourseRun.effectiveMaxParticipants()` prüft nur `courseRun.max_participants ?? course.max_participants`.
Die Session-eigene Kapazität wird ignoriert. Das kann dazu führen, dass mehr
Buchungen für einen Run akzeptiert werden, als einzelne Sessions fassen können.

Für den MVP ist das akzeptabel (Buchung auf Run-Ebene, Session-Kapazität irrelevant),
aber dev-php sollte es im task-T02-Notes dokumentieren.

---

## Vergessene Abhängigkeiten (Zusammenfassung)

| Datei | Betroffene Stelle | Zugehörige Task |
|-------|-------------------|-----------------|
| `DashboardController.php` | Z. 86, 95, 128, 137, 165, 187–217, 260–261 | **Neue Task T06b** |
| `TrainingSessionController.php` | Z. 64 (whereRaw availability) | T03 oder neue Sub-Task |
| `routes/api.php` Z. 130 | `POST /courses/{course}/sessions` entfernen | T03 (CourseRunController) |
| `routes/api.php` Z. 118–119 | `training-sessions/{id}/bookings` + `availability` | T06 oder Deprecation-Entscheidung |
| `Mail/BookingConfirmation.php` | Subject-Zeile Z. 46 | T07 |
| `Mail/BookingCancellationApproved.php` | Subject-Zeile Z. 51 | T07 |
| `Console/Commands/TestEmailTemplates.php` | Z. 92 | T07 |
| `Console/Commands/SendTestEmail.php` | Z. 91 | T07 |
| Migration `2026_01_04_180000` | Composite Index auf `bookings` | M05 in T01 |

---

## Freigabe-Bedingungen

Implementierung kann starten, sobald `tasks.md` folgende Ergänzungen enthält:

1. **Task T06b: DashboardController anpassen** (`dev-php`)
   - Eager-Loads `trainingSession.course` → `courseRun.course`
   - raw JOIN Z. 260 auf Eloquent umstellen
   - Trainer-Sicht Z. 165–217 anpassen

2. **M05 in T01 ergänzt:** Composite Index `bookings_training_session_id_status_index`
   vor `DROP COLUMN training_session_id` droppen.

3. **T03 oder T06 ergänzt:** `POST /courses/{course}/sessions` Route entfernen +
   `TrainingSessionController` availability Z. 64 anpassen.

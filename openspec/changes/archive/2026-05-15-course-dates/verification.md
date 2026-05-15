# Verification: course-dates

**Gesamtstatus:** nacharbeit-am-design-nötig

---

## Tabellarische Übersicht

| # | Annahme | Status | Beleg / Datei:Zeile |
|---|---------|--------|---------------------|
| 1 | `training_sessions`-Tabelle mit Feldern `course_id`, `trainer_id`, `session_date`, `start_time`, `end_time`, `location`, `max_participants`, `status`, `notes` | ✅ bestätigt | `backend/database/migrations/2025_12_22_184838_create_sessions_table.php:14–27` |
| 2 | `courses`-Tabelle hat `start_date`, `end_date`, `total_sessions`, `duration_minutes` — kein `recurrence_rule` | ✅ bestätigt | `backend/database/migrations/2025_12_22_184818_create_courses_table.php:14–24` |
| 3 | `CourseController::store()` erstellt aktuell KEINE `TrainingSession`-Einträge | ✅ bestätigt | `backend/app/Http/Controllers/Api/CourseController.php:101–107` |
| 4 | `Booking`-Model referenziert `TrainingSession` | ✅ bestätigt | `backend/app/Models/Booking.php:11,71` (`training_session_id` FK, `session()` BelongsTo) |
| 5 | `onDelete('cascade')` in Bookings-Migration gesetzt | ✅ bestätigt | `backend/database/migrations/2025_12_22_184856_create_bookings_table.php:18` |
| 6 | `GET /api/v1/courses/{course}` ist hinter `auth:sanctum` | ✅ bestätigt | `backend/routes/api.php:62` (auth:sanctum-Block), `:123` (`apiResource('courses', ...)`) |
| 7 | Öffentlicher Route-Prefix ohne `auth:sanctum` existiert (Präzedenzfall `/pricing-items`) | ✅ bestätigt | `backend/routes/api.php:54–56` (`Route::prefix('v1')->group(...)` ohne Middleware) |
| 8 | `CourseResource` gibt Sessions via `whenLoaded` zurück | ✅ bestätigt | `backend/app/Http/Resources/CourseResource.php:37` (`whenLoaded('sessions')`) |
| 9 | `TrainingSessionResource` existiert | ✅ bestätigt | `backend/app/Http/Resources/TrainingSessionResource.php:1` |
| 10 | `CourseFormModal.vue` existiert unter `frontend/src/components/` | ✅ bestätigt | `frontend/src/components/CourseFormModal.vue` |
| 11 | `CourseFormModal.vue` hat Felder `start_date`, `end_date`, `start_time`, `end_time`, `total_sessions` — keine Session-Liste | ✅ bestätigt (mit Nuance) | Felder bestätigt Z.76, 81, 86, 91, 109; keine Session-Liste vorhanden — aber `start_time`/`end_time` werden im API-Payload von `handleSubmit()` **nicht mitgesendet** (Z.246–263) |
| 12 | Router unter `frontend/src/router/index.ts` oder `index.js` | ✅ bestätigt | `frontend/src/router/index.ts` |
| 13 | Frontend verwendet JS — kein `<script setup lang="ts">` | ❌ widerlegt | `frontend/src/components/CourseFormModal.vue:154`: `<script setup lang="ts">`; alle geprüften SFCs verwenden TypeScript |
| 14 | `Course`-Modell hat `$fillable` | ✅ bestätigt | `backend/app/Models/Course.php:42–56` |
| 15 | `Course`-Modell hat `sessions()` HasMany-Beziehung | ✅ bestätigt | `backend/app/Models/Course.php:90–93` |
| 16 | `TrainingSession`-Modell existiert unter `app/Models/TrainingSession.php` | ✅ bestätigt | `backend/app/Models/TrainingSession.php:1` |
| 17 | `CourseController` unter `app/Http/Controllers/Api/CourseController.php` | ✅ bestätigt | `backend/app/Http/Controllers/Api/CourseController.php:1` |
| 18 | `StoreCourseRequest` und `UpdateCourseRequest` existieren | ✅ bestätigt | `backend/app/Http/Requests/StoreCourseRequest.php`, `UpdateCourseRequest.php` |
| 19 | Routen-Datei: `backend/routes/api.php` | ✅ bestätigt | `backend/routes/api.php:1` |

---

## Kritische Befunde

### ❌ Befund 1: Frontend ist TypeScript, nicht JavaScript

**Betroffene Spec-Stellen:**
- `tasks.md` T06, Z.310: „kein TypeScript (bestehender Frontend-Code verwendet JS)"
- `tasks.md` T09, Z.419: `frontend/src/router/index.js` (als bevorzugter Name)

**Tatsächlicher Code:**
- `frontend/src/components/CourseFormModal.vue:154`: `<script setup lang="ts">`
- `frontend/src/router/index.ts` (TypeScript-Datei)
- Stichprobe weiterer Komponenten: `App.vue`, `DogFormModal.vue`, `HtmlEditor.vue`, `PaymentModal.vue`, `DefaultLayout.vue`, `ToastContainer.vue` — alle `<script setup lang="ts">`

**Konsequenz für die Implementierung:**
Die neuen Komponenten `CourseRecurrenceForm.vue`, `CourseSessionList.vue` und `CourseDetailView.vue` müssen `<script setup lang="ts">` verwenden und TypeScript-Props/Emits mit `defineProps<...>()` und `defineEmits<...>()` deklarieren. Der in T06 und T08 gezeigte JS-Code (`defineProps({ modelValue: { type: Object, default: null } })`) ist falsch und würde ohne TypeScript-Typen im bestehenden Projekt inkonsistent sein. Die Router-Datei in T09 heißt korrekt `index.ts`, nicht `index.js`.

---

### ⚠️ Befund 2: `GET /courses/{course}/sessions` existiert bereits

**Betroffene Spec-Stellen:**
- `design.md` Abschnitt 1.3.2: beschreibt neue Endpoints POST/PUT/DELETE für `/courses/{course}/sessions`
- `tasks.md` T04: „Neue Methoden: `storeSession`, `updateSession`, `destroySession`"

**Tatsächlicher Code:**
- `backend/routes/api.php:124`: `Route::get('/courses/{course}/sessions', [CourseController::class, 'sessions']);`
- `backend/app/Http/Controllers/Api/CourseController.php:163–180`: `sessions()`-Methode gibt Sessions mit Bookings zurück (hinter `auth:sanctum`)

**Konsequenz:**
Kein Konflikt mit den geplanten POST/PUT/DELETE-Endpoints — aber die Spec erwähnt diesen bestehenden GET-Endpoint nirgends. T08 (`CourseSessionList`) soll Sessions über `GET /api/v1/courses/{courseId}/sessions` laden — dieser Endpoint **existiert bereits** und ist bereits hinter `auth:sanctum`. Für den öffentlichen `publicShow`-Endpoint in T04 muss beachtet werden, dass Sessions bereits in `CourseResource::whenLoaded('sessions')` integriert sind; ein separater Aufruf auf `/sessions` wäre für öffentliche Nutzung nicht möglich (hinter auth:sanctum). Das Design in `CourseDetailView` (T09), das für nicht-eingeloggte Nutzer `GET /api/v1/public/courses/{id}` aufruft, ist korrekt.

---

### ⚠️ Befund 3: `start_time`/`end_time` im CourseFormModal sind nicht im API-Payload

**Betroffene Spec-Stellen:**
- `proposal.md` Z.108: „CourseFormModal.vue hat aktuell Felder: start_date, end_date, start_time, end_time, total_sessions"
- `triage/20260514141728-course-dates.md` bestätigt dieselbe Aussage

**Tatsächlicher Code:**
- `frontend/src/components/CourseFormModal.vue:86–91`: Form-Felder `start_time`/`end_time` sind im Template vorhanden
- `frontend/src/components/CourseFormModal.vue:246–263`: `handleSubmit()` sendet `startDate`/`endDate`, aber **weder `startTime` noch `endTime`** an die API
- `backend/database/migrations/2025_12_22_184818_create_courses_table.php`: kein `start_time`/`end_time` in der `courses`-Tabelle

**Konsequenz:**
Die Spec-Aussage „hat Felder" ist technisch korrekt — die Form-Felder existieren. Aber sie sind funktionslos (nicht im API-Payload, keine DB-Spalte im Kurs). T07 (CourseFormModal erweitern) arbeitet an einem bestehenden „Dates and Times"-Block und positioniert den neuen Session-Abschnitt darunter. Da `start_time`/`end_time` im Kurs-Formular aktuell leer bleiben und nicht gespeichert werden, kann T07 diese Felder ignorieren oder auf künftige Klärung verweisen. Kein Blocker.

---

### ⚠️ Befund 4: `design.md`-Dateipfad-Tabelle (Abschnitt 2) ohne `backend/`-Prefix

**Betroffene Spec-Stelle:**
- `design.md` Abschnitt 2 (Tabelle „Neue und geänderte Dateien"):
  `app/Models/Course.php`, `app/Services/CourseSessionService.php`, `app/Http/Requests/...`, `app/Http/Controllers/...`

**Tatsächlicher Code:**
- Alle Dateien liegen unter `backend/app/...` (Workspace-Pfad), z.B. `backend/app/Models/Course.php`
- `tasks.md` verwendet konsistent den `backend/`-Prefix: `backend/app/Models/Course.php`, `backend/app/Services/CourseSessionService.php` etc.
- Einzig `backend/routes/api.php` hat in der design.md-Tabelle den korrekten Prefix

**Konsequenz:**
Kein funktionaler Fehler — Laravel-Entwickler kennen die Konvention. Kann jedoch bei `dev-php` zu Verwirrung führen. Da `tasks.md` konsistent die korrekten `backend/`-Pfade verwendet, ist dort kein Problem. `design.md` sollte für Konsistenz korrigiert werden.

---

### ⚠️ Befund 5: `throttle:60,1` vs. Named Rate Limiters

**Betroffene Spec-Stelle:**
- `design.md` Abschnitt 1.3.3: `Route::prefix('v1')->middleware('throttle:60,1')->group(...)`
- `tasks.md` T04: dieselbe Middleware-Syntax

**Tatsächlicher Code:**
- `backend/routes/api.php:41`: `middleware('throttle:login')`
- `backend/routes/api.php:49`: `middleware('throttle:contact')`
- Das Projekt nutzt durchgehend Named Rate Limiters (konfiguriert in `RouteServiceProvider` oder `AppServiceProvider`)

**Konsequenz:**
`throttle:60,1` ist eine valide Laravel-Syntax und funktioniert. Aber es weicht vom Projekt-Pattern ab. `dev-php` sollte entweder einen Named Limiter anlegen (`throttle:public-course`) oder die Inline-Variante mit einem Kommentar versehen. Kein Blocker, aber inkonsistent.

---

## Neue Elemente (Plausibilität)

| Element | Status | Bewertung |
|---------|--------|-----------|
| `backend/database/migrations/2026_05_14_000001_add_recurrence_rule_to_courses_table.php` | neu | Pfad konsistent mit bestehenden Migrations in `backend/database/migrations/` |
| `backend/app/Services/CourseSessionService.php` | neu | `backend/app/Services/` existiert nicht geprüft — aber Services-Verzeichnis ist Standard-Laravel-Konvention; kein Konflikt erwartet |
| `backend/app/Http/Requests/StoreCourseSessionRequest.php` | neu | Konsistent mit `StoreCourseRequest.php` im selben Verzeichnis |
| `backend/app/Http/Requests/UpdateCourseSessionRequest.php` | neu | Konsistent |
| `frontend/src/components/CourseRecurrenceForm.vue` | neu | Pfad konsistent mit `frontend/src/components/CourseFormModal.vue` |
| `frontend/src/components/CourseSessionList.vue` | neu | Pfad konsistent |
| `frontend/src/views/CourseDetailView.vue` | neu | Bestehende Views liegen in `frontend/src/views/` (z.B. `HomeView.vue`, `DashboardView.vue`) — Pfad konsistent; views-Unterordner (`courses/`, `customers/`) existieren auch |
| Router-Route `/courses/:id` | neu | Bestehende Routen: `/app/courses` → `CoursesView` (nur im `/app/`-Segment). Neue Route `/courses/:id` ohne `/app/`-Prefix → liegt im PublicLayout-Block; das ist konsistent mit der Anforderung, öffentlich zugänglich zu sein |

---

## Empfehlungen

### Empfehlung 1 (kritisch): TypeScript in allen neuen Frontend-Komponenten

In `tasks.md` T06, T07, T08, T09 den Hinweis „kein TypeScript" ersetzen durch:

> „TypeScript verwenden: `<script setup lang="ts">`. Props mit `defineProps<{ ... }>()`, Emits mit `defineEmits<{ ... }>()`."

Beispiel-Korrektur für T06:
```ts
// Statt:
defineProps({ modelValue: { type: Object, default: null } })
defineEmits(['update:modelValue'])

// Korrekt:
interface RecurrenceRule { type: 'weekly' | 'monthly'; ... }
const props = defineProps<{ modelValue: RecurrenceRule | null }>()
const emit = defineEmits<{ 'update:modelValue': [value: RecurrenceRule | null] }>()
```

### Empfehlung 2 (informativ): GET `/courses/{course}/sessions` in T08 dokumentieren

`tasks.md` T08 beschreibt `GET /api/v1/courses/{courseId}/sessions` als zu implementierenden Endpunkt. Dieser **existiert bereits** (`backend/routes/api.php:124`). T08 kann direkt auf diesen Endpunkt bauen — kein Backend-Task nötig für das GET.

### Empfehlung 3 (minor): Dateipfade in `design.md` Abschnitt 2 auf `backend/`-Prefix vereinheitlichen

Alle Backend-Pfade in der Dateitabelle um `backend/` ergänzen, konsistent mit `tasks.md`.

### Empfehlung 4 (minor): Named Rate Limiter für öffentlichen Kurs-Endpoint

Statt `throttle:60,1` einen Named Limiter `throttle:public-course` definieren (analog zu `throttle:contact`) — oder zumindest in einem Code-Kommentar dokumentieren, warum hier die Inline-Syntax verwendet wird.

---

## Abschluss-Urteil

**(b) Spezifikation ist umsetzbar — mit Korrekturen.**

17 von 19 geprüften Annahmen sind korrekt. Der einzige echte Implementierungs-Blocker ist **Befund 1 (TypeScript)**: Alle neuen Vue-Komponenten müssen TypeScript verwenden, da das gesamte Frontend `<script setup lang="ts">` nutzt. Würde `dev-javascript` die Spec wörtlich nehmen, entstünden inkonsistente Komponenten ohne TS-Typen. Die restlichen Befunde (2–5) sind Hinweise und Nuancen, keine Blocker.

`dev-php` kann direkt mit T01–T05 beginnen. `dev-javascript` kann mit T06–T10 beginnen, sobald der API-Kontrakt (T01–T04) abgenommen ist — muss aber die TypeScript-Korrektur aus Empfehlung 1 beachten.

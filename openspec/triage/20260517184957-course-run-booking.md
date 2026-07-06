# Triage: course-run-booking

**Pfad:** gross
**Geschätzter Umfang:** 15–20 Dateien, PHP + Vue.js (beide Stacks)
**Risiko:** hoch — Kernbuchungslogik (umsatzkritisch), breaking Schema-Änderung, Migration bestehender Daten
**Klarheit:** klar — Kernentscheidungen durch User beantwortet (17.05.2026)

---

## Anforderung (Zusammenfassung)

Kund:innen sollen Kurse immer als gesamten Durchlauf ("CourseRun") buchen,
nicht mehr Einzel-Session für Einzel-Session per Checkbox. Gibt es denselben
Kurs mehrfach im Jahr, wählen Kund:innen zunächst den gewünschten Durchlauf
(z. B. "Junghundekurs (04.05.–25.05.)") und buchen damit automatisch alle
zugehörigen Sessions. Optional soll "Drop-In"-Buchung für explizit dafür
markierte Kurse weiterhin möglich sein.

---

## Ist-Zustand (belegter Code-Befund)

### Datenmodell

| Entity             | Datei                                                    | Kernfelder                                         |
|--------------------|----------------------------------------------------------|----------------------------------------------------|
| `Course`           | `backend/app/Models/Course.php` (Z. 1–100)               | `start_date`, `end_date`, `recurrence_rule` (JSON), `total_sessions`, `price_per_session` |
| `TrainingSession`  | `backend/app/Models/TrainingSession.php` (Z. 1–120)      | `course_id`, `session_date`, `start_time`, `end_time` |
| `Booking`          | `backend/app/Models/Booking.php` (Z. 1–100)              | **`training_session_id`** (kein Kurs/Run-Bezug) |

**Schlüsselproblem im Schema:** Die `bookings`-Tabelle referenziert eine
einzelne `training_session_id` (`database/migrations/2025_12_22_184856_create_bookings_table.php`,
Z. 15–17). Das Konzept "CourseRun/CourseInstance" existiert **nicht**:
weder als Tabelle noch als Model noch als Relation.

Der `Course` fungiert aktuell sowohl als **Kurs-Template** (Name, Typ,
Trainer) als auch als **Kursdurchlauf** (konkretes `start_date`,
`end_date`, Sessions). Mehrere Durchläufe desselben Kurses werden heute
implizit durch separate `Course`-Datensätze abgebildet (z. B. zwei
separate Kurseinträge "Junghundekurs Mai" / "Junghundekurs Juni").

### Buchungslogik Backend

`BookingController::store()` (`backend/app/Http/Controllers/Api/BookingController.php`,
Z. 120–157) erstellt exakt **eine Buchung pro `training_session_id`**.
Die Kapazitätsprüfung, Duplikat-Prüfung und Event-Dispatch sind alle
session-bezogen. Keine Logik für Sammel- oder Serienbuchung vorhanden.

Session-Generierung via `CourseSessionService::generateFromRecurrence()`
(`backend/app/Services/CourseSessionService.php`, Z. 51–103) wird beim
Anlegen eines Kurses aufgerufen und erzeugt Sessions aus der
`recurrence_rule`. Kein Durchlauf-Konzept.

### Buchungs-UI Frontend

`CustomerBookingModal.vue` (`frontend/src/components/CustomerBookingModal.vue`):
- Erhält `courseId` als Prop (Z. 28–30)
- Lädt alle Sessions des Kurses via `GET /api/v1/courses/{id}/sessions` (Z. 57)
- **Zeigt bei mehreren Sessions Checkboxen** (Z. 268–290) — das ist das
  beschriebene Problem
- `handleSubmit()` (Z. 110–130) schickt für jede `selectedSessionId` einen
  separaten `POST /api/v1/bookings`-Request in einer `for`-Schleife

`BookingFormModal.vue` (Admin-Seite, `frontend/src/components/BookingFormModal.vue`,
Z. 55–60): Wählt ebenfalls einzelne Sessions per Dropdown aus.

`CourseFormModal.vue` / `CourseRecurrenceForm.vue`: Ermöglichen das Anlegen
einer Terminserie, aber ohne Durchlauf-Konzept.

### Keine CourseRun-Entität vorhanden

Grep über alle PHP- und Vue-Dateien: kein Treffer auf
`course_run`, `course_instance`, `CourseRun`, `CourseInstance`, `Durchlauf`.
Das Konzept ist vollständig neu zu implementieren.

---

## Betroffene Dateien (Schätzung)

### Backend (PHP)
- **Neu:** Migration `create_course_runs_table`, Model `CourseRun`, Controller `CourseRunController`, Resource `CourseRunResource`, FormRequests
- **Geändert:** `Course.php` — neue `hasMany(CourseRun)` Relation
- **Geändert:** `TrainingSession.php` — neue `course_run_id`-Spalte/-Relation (Migration nötig)
- **Geändert:** `Booking.php` — Referenz auf `CourseRun` statt (oder zusätzlich zu) `TrainingSession`
- **Geändert:** `BookingController.php` — Buchungslogik für gesamten Durchlauf
- **Geändert:** `CourseController.php` — Session-Generierung in CourseRun-Kontext
- **Geändert:** `CourseSessionService.php` — Anpassung an neues Modell
- **Geändert:** `StoreBookingRequest.php`, `UpdateBookingRequest.php`
- **Geändert:** `routes/api.php` — neue CourseRun-Routen

### Frontend (Vue.js)
- **Geändert:** `CustomerBookingModal.vue` — Checkboxen → Durchlauf-Auswahl
- **Geändert:** `BookingFormModal.vue` — Admin-Buchung auf Durchlauf umstellen
- **Geändert:** `CoursesView.vue` — Darstellung nach Kurs (mit Durchläufen) neu denken
- **Geändert:** `CourseDetailView.vue` — Durchlauf-Auswahl anzeigen
- **Geändert:** `BookingsView.vue` — Buchungsanzeige mit Durchlauf-Kontext
- Ggf. neue Komponente `CourseRunSelector.vue`

**Summe: ~15–20 Dateien**, beide Stacks.

---

## Risiken und Abhängigkeiten

### Risiko 1 — Datenbank-Migration (HOCH)
Die `bookings`-Tabelle muss um eine `course_run_id`-Spalte erweitert werden
(oder `training_session_id` wird durch `course_run_id` ersetzt). Bestehende
Daten müssen migriert werden. Dies ist eine **breaking Schemaänderung**.

Migrations müssen MySQL-kompatibel sein (CLAUDE.md Abschnitt 4.2).

### Risiko 2 — Buchungslogik-Komplexität (HOCH)
- Kapazitätsprüfung: Gilt sie pro Durchlauf-Buchung oder pro Session?
- Stornierung: Gilt sie für den gesamten Durchlauf oder nur einzelne Sessions?
- Nachrücker: Wenn jemand mid-run nachbucht, bucht er noch laufende Sessions?
- Email-Benachrichtigungen: `BookingCreated`-Event für alle Sessions auf einmal?

### Risiko 3 — Course als Template vs. Run (MITTEL)
Die aktuelle `Course`-Struktur hat konkrete Felder (`start_date`, `end_date`),
die bei sauberem Template/Run-Split zum `CourseRun` wandern würden. Das ist
ein größerer Umbau als nur eine neue Tabelle anzuhängen.

**Designentscheidung offen:** Wird `Course` zum reinen Template (ohne
Startdatum), oder bleibt `Course` eine Einheit und ein neues `CourseRun`-Layer
wird hinzugefügt? Diese Entscheidung bestimmt Migrations-Aufwand massiv.

### Risiko 4 — Drop-In-Kurse (NIEDRIG)
Der `open_group`-Kurstyp existiert bereits im DB-Enum
(`database/migrations/2026_01_03_144125_add_open_group_to_course_type_enum.php`).
Die Sonderbehandlung für Drop-In muss konsistent dazu sein.

### Abhängigkeit
- Stornierungslogik ist bereits im System (`cancel()`-Methode im
  `BookingController`, Z. 189–250, mit Deadline-Prüfung). Diese muss auf
  das neue Durchlauf-Modell angepasst werden.
- `BookingCreated`-Event und Mail-Templates (vorhanden) müssen aktualisiert werden.

---

## Entscheidungen (User-Antworten, 17.05.2026)

1. **Template vs. Run:** ✅ **Option A — `Course` wird reines Template.**
   Kurse werden ohne Startdatum als Vorlage angelegt (Name, Typ, Trainer,
   Preis, Konfiguration). Konkrete Durchläufe (Serien mit eigenem Startdatum
   und Sessions) werden als separate `CourseRun`-Entitäten darunter verwaltet.
   Hintergrund: Manche Kurse werden mehrfach im Jahr angeboten; Kund:innen
   sollen die passende Serie auswählen können.
   **Trainer-Sicht:** Abgeschlossene Serien können gelöscht, neue können
   dem Kurs-Template hinzugefügt werden.

2. **Stornierung innerhalb eines Durchlaufs:** ✅ **Buchung ist unteilbar.**
   Nur der gesamte Durchlauf kann storniert werden, keine Einzel-Session.

3. **Nachrücker / Mid-Run-Buchung:** ✅ **Keine Nachbuchung für laufende Kurse.**
   Ausnahme: `open_group`-Kurse (offene Kurse) erlauben Einstieg jederzeit.

## Offene Fragen (für Architekt zu klären im Design)

4. **Warteliste:** Soll es eine Warteliste auf Durchlauf-Ebene geben?
5. **Drop-In-Markierung:** Reicht `course_type = 'open_group'` als Drop-In-Marker?
6. **Migration bestehender Daten:** Produktive Daten vorhanden oder sauberer Schnitt möglich?

---

## Empfohlener nächster Schritt

**Architekt-Agent beauftragen** (Pfad: `gross`). Kernentscheidungen sind getroffen.



1. Architekt erstellt `proposal.md` und `design.md` mit Entscheidung für
   Template/Run-Modell und DB-Schema.
2. Skeptiker prüft insbesondere: DB-Portabilität (MySQL+Postgres), PHP-8.2-
   Kompatibilität, Shared-Hosting-Tauglichkeit der Migrationsstrategie.
3. Tasks werden in zwei Spuren aufgeteilt: `dev-php` (Backend) und
   `dev-javascript` (Frontend), mit definiertem API-Kontrakt als Übergabepunkt.

**Kommando für den nächsten Schritt:**
```
@architect Erstelle den openspec-Change basierend auf
openspec/triage/20260517184957-course-run-booking.md
```

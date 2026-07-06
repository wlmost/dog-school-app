# Proposal: course-run-booking

**Change-ID:** course-run-booking  
**Erstellt:** 2026-05-17  
**Triage:** `openspec/triage/20260517184957-course-run-booking.md`  
**Status:** in-design

---

## Problem

Das aktuelle Buchungssystem erlaubt es Kund:innen, einzelne Sessions eines
Kurses per Checkbox auszuwählen und separat zu buchen. Dies führt zu mehreren
Problemen:

1. **UX-Problem:** Kund:innen müssen manuell alle gewünschten Termine auswählen
   — es ist unklar, dass sie "einen Kurs" und nicht beliebige Einzelstunden buchen.
2. **Datenmodell-Problem:** Die `Booking`-Entität referenziert eine einzelne
   `TrainingSession`, nicht einen zusammenhängenden Kursdurchlauf. Es gibt
   keine Entität, die einen konkreten Kurs-Durchlauf (z. B. "Junghundekurs
   April–Mai 2026") repräsentiert.
3. **Mehrfach-Kurs-Problem:** Wird derselbe Kurs mehrfach im Jahr angeboten,
   müssen Trainer heute separate `Course`-Einträge anlegen (z. B. "Junghundekurs
   Mai", "Junghundekurs Juni"). Es gibt keine saubere Möglichkeit, diese als
   Durchläufe eines gemeinsamen Kurs-Templates zu gruppieren.

---

## Ziel

Kund:innen buchen einen Kurs immer als **gesamten Durchlauf** ("CourseRun").
Trainer verwalten Kurse als **Templates**, unter denen mehrere Durchläufe
angelegt werden können. Die Buchungslogik wird atomisiert: eine Buchung = ein
Durchlauf.

---

## Vorgeschlagene Lösung

### Kernkonzept: Template / Run Trennung (Option A)

`Course` wird zum **reinen Template** ohne konkrete Termine:

- Enthält: Name, Kurstyp, Trainer, Preis pro Session, Max-Teilnehmer,
  Stornierungsfrist, Beschreibung
- Enthält **nicht mehr**: `start_date`, `end_date`, `recurrence_rule`,
  `total_sessions`

Neue Entität `CourseRun` repräsentiert einen **konkreten Durchlauf**:

- Gehört zu einem `Course`-Template (`course_id`)
- Enthält: `start_date`, `end_date`, `recurrence_rule`, `total_sessions`, `status`
- Enthält alle zugehörigen `TrainingSessions`
- Ist die Buchungseinheit: eine `Booking` referenziert `course_run_id`

### Buchungslogik nach dem Change

1. Kund:in wählt einen Kurs (Template) aus
2. Das System zeigt alle **offenen Durchläufe** des Kurses an
3. Kund:in wählt einen Durchlauf aus
4. Eine einzelne Buchung für den gesamten Durchlauf wird erstellt
5. Keine Checkboxen mehr, kein Loop über Sessions

### Drop-In / Open-Group

Kurse mit `course_type = 'open_group'` erlauben Buchungen auch dann, wenn der
Durchlauf bereits begonnen hat ("Mid-Run-Buchung"). Für alle anderen Kurstypen
ist Mid-Run-Buchung gesperrt.

---

## In Scope

- Neues Datenmodell: `course_runs`-Tabelle, Migrationen
- Umbau `courses`-Tabelle: Datum-Felder wandern zu `course_runs`
- Umbau `training_sessions`: `course_id` → `course_run_id`
- Umbau `bookings`: `training_session_id` → `course_run_id`
- Neue API-Endpunkte für CourseRun-CRUD
- Angepasste Buchungs-API (`POST /api/v1/bookings`)
- Frontend-Umbau: Durchlauf-Auswahl statt Session-Checkboxen
- Admin-UI: Durchläufe anlegen, bearbeiten, löschen
- Datenmigration bestehender Daten (Strategie: bestehende `Course`-Einträge
  werden zu einem CourseRun umgewandelt)
- Angepasste `cancellationDeadline`-Logik basierend auf `courseRun.start_date`
- `BookingCreated`-Event + Mail-Template an neues Modell anpassen

## Nicht in Scope

- Wartelisten-Funktion (separater Change empfohlen)
- Zahlungsintegration für Raten-/Kurs-Preise (bestehende `price_per_session`
  Logik bleibt erhalten)
- Kalender-/iCal-Export
- Umbau der `TrainingLog`-Entität
- Anamnese-Zuordnung zu Buchungen (unverändert)
- Umbenennung von `open_group` oder neue Drop-In-Flags

---

## Offene Punkte (für Design zu klären)

### OP-1: Warteliste
Soll bei ausgebuchtem Durchlauf eine Warteliste angeboten werden? Aktuell
liefert das System einen 422-Fehler "Session is full". Das bisherige Verhalten
(kein Wartelistenplatz) wird beibehalten, mit aussagekräftigerem Fehler.
**Entscheidung (Design):** Keine Warteliste in diesem Change.

### OP-2: Drop-In-Markierung
`course_type = 'open_group'` reicht als Drop-In-Marker aus. Kein separates
`is_drop_in`-Flag benötigt.
**Entscheidung (Design):** `open_group`-Check direkt auf `course.course_type`.

### OP-3: Datenmigrations-Sicherheit
Produktive Daten können vorhanden sein. Die Migrations-Strategie muss
rollback-fähig und MySQL-kompatibel sein.
**Entscheidung (Design):** Mehrere Migration-Dateien in definierter Reihenfolge,
keine irreversen Drops in einem einzigen Schritt (siehe Design).

### OP-4: Course-Status-Semantik
`Course.status` (aktuell `planned|active|completed|cancelled`) bezieht sich
bisher auf den Kursdurchlauf. Als Template sind diese Werte nicht mehr passend.
**Entscheidung (Design):** Enum bleibt — Template-`status` wird auf
`active|archived` reduziert (Migration ändert bestehende Werte auf `active`).
`CourseRun.status` übernimmt die vollen Werte `planned|active|completed|cancelled`.

---

## Risikobewertung

| Risiko | Schwere | Maßnahme |
|--------|---------|----------|
| Breaking Schema-Änderung an `bookings` | HOCH | Datenmigration in reversibler Reihenfolge |
| Mehrere DB-Portabilitäts-Fallen | HOCH | Eloquent-only, kein raw SQL |
| Buchungslogik-Komplexität | MITTEL | Klare Domänenregel: 1 Buchung = 1 Run |
| Mid-Run-Buchung für open_group | NIEDRIG | Einmalige `isOpenGroup()`-Prüfung |

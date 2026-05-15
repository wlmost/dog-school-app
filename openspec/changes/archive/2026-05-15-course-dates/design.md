# Design: course-dates

**Change-ID:** course-dates
**GitHub Issue:** #33

---

## 1. Architekturentscheidungen

### 1.1 Rekurrenz-Berechnung: Backend

**Entscheidung:** Die Rekurrenz-Logik lebt vollständig im PHP-Backend
(`CourseSessionService`). Das Frontend schickt nur die Regel; das Backend
berechnet daraus die `TrainingSession`-Einträge und speichert sie.

**Begründung:**
- Die berechneten Sessions müssen für Buchungs-Konflikt-Checks server-seitig
  vorhanden sein — ein reines Frontend-Rendering würde eine Doppelimplementierung
  erfordern.
- Reine PHP-Logik, kein Queue-Worker, kein Shell-Exec → Shared-Hosting-kompatibel.
- Wenn später eine externe Integration (z. B. API-Import) Sessions anlegen will,
  nutzt sie denselben Service.

### 1.2 Datenmodell

**`courses`-Tabelle:** Neues nullable JSON-Feld `recurrence_rule`.

```
courses.recurrence_rule: JSON | null
```

Inhalt (Beispiel wöchentlich):
```json
{
  "type": "weekly",
  "weekday": 1,
  "startTime": "10:00",
  "endTime": "11:00",
  "startDate": "2025-03-03",
  "count": 8
}
```

Inhalt (monatlich):
```json
{
  "type": "monthly",
  "dayOfMonth": 15,
  "startTime": "10:00",
  "endTime": "11:00",
  "startDate": "2025-03-15",
  "count": 6
}
```

**`training_sessions`-Tabelle:** Keine Schema-Änderung erforderlich.
Alle nötigen Felder (`course_id`, `session_date`, `start_time`, `end_time`,
`location`, `max_participants`, `status`, `notes`) sind bereits vorhanden.

Beim manuellen Bearbeiten einzelner Sessions aus einer Serie (Verschieben,
Löschen, Ergänzen) wird kein `is_exception`-Flag benötigt — die Wahrheit liegt
in den Zeilen der `training_sessions`-Tabelle selbst. Die gespeicherte
`recurrence_rule` am Kurs ist nur noch als Referenzinformation zu verstehen,
nicht als aktive Steuerungsregel nach dem ersten Generieren.

**Migration:**
- Neue Datei: `database/migrations/2026_05_14_000001_add_recurrence_rule_to_courses_table.php`
- `$table->json('recurrence_rule')->nullable()->after('total_sessions');`
- MySQL + PostgreSQL kompatibel (kein `jsonb()`)

### 1.3 API-Design

**Entscheidung:** Hybridansatz — Erweiterung der bestehenden Course-Endpoints
für die initiale Session-Erzeugung **plus** neue dedizierte Sub-Ressource für
individuelle Session-Verwaltung.

#### 1.3.1 Erweiterung `POST /api/v1/courses` und `PUT /api/v1/courses/{course}`

Beide Endpoints akzeptieren optional zwei neue Felder, die sich gegenseitig
ausschließen:

| Feld | Typ | Bedeutung |
|------|-----|-----------|
| `sessionsMode` | `"manual"\|"recurrence"\|null` | Aktiviert Session-Erzeugung |
| `sessions` | array | Pflicht wenn `sessionsMode = "manual"` |
| `recurrenceRule` | object | Pflicht wenn `sessionsMode = "recurrence"` |

**Payload-Schema `sessions` (manuell):**
```json
{
  "sessionsMode": "manual",
  "sessions": [
    {
      "sessionDate": "2025-03-03",
      "startTime": "10:00",
      "endTime": "11:00",
      "location": "Platz A",
      "maxParticipants": 8
    }
  ]
}
```

**Payload-Schema `recurrenceRule`:**
```json
{
  "sessionsMode": "recurrence",
  "recurrenceRule": {
    "type": "weekly",
    "weekday": 1,
    "startTime": "10:00",
    "endTime": "11:00",
    "startDate": "2025-03-03",
    "count": 8,
    "location": null,
    "maxParticipants": null
  }
}
```

Fehlende `location` und `maxParticipants` im `recurrenceRule` fallen auf
`null` (location) bzw. `course.max_participants` (maxParticipants) zurück.

**Abwärtskompatibilität:** Fehlt `sessionsMode` komplett, verhält sich der
Endpoint exakt wie bisher — keine Sessions werden erstellt oder verändert.

**`update()` Session-Abgleich-Strategie:**
- Sessions mit `sessionsMode` in `PUT` ersetzen **nur Sessions ohne Buchungen**;
  Sessions mit Buchungen bleiben immer erhalten.
- Zurückgegeben wird ein `warnings`-Array wenn Sessions mit Buchungen
  ignoriert wurden (Buchungs-Konflikt-Typ `"protected_session"`).
- Empfehlung an Frontend: Bei Rückkehr eines `warnings`-Arrays Toast-Meldung
  zeigen.

#### 1.3.2 Neue Endpoints für individuelle Session-Verwaltung

```
POST   /api/v1/courses/{course}/sessions
PUT    /api/v1/courses/{course}/sessions/{session}
DELETE /api/v1/courses/{course}/sessions/{session}
```

Diese Endpoints werden zum `CourseController` hinzugefügt (kein separater
Controller — der Scope bleibt klein genug).

**`POST /api/v1/courses/{course}/sessions` — Session anlegen:**

Request body (alle Felder aus `training_sessions`):
```json
{
  "sessionDate": "2025-04-07",
  "startTime": "10:00",
  "endTime": "11:00",
  "location": "Platz A",
  "maxParticipants": 8,
  "status": "scheduled",
  "notes": null
}
```

Response: `201 Created` mit `TrainingSessionResource`.

**`PUT /api/v1/courses/{course}/sessions/{session}` — Session aktualisieren:**

Request body: beliebige Teilmenge der Session-Felder (alle `sometimes`).

Response bei Buchungen (200, kein Fehler):
```json
{
  "data": { ...TrainingSessionResource... },
  "warnings": [
    {
      "type": "booking_conflict",
      "message": "Diese Einheit hat 3 aktive Buchungen.",
      "bookingCount": 3
    }
  ]
}
```

Response ohne Buchungen: `{ "data": { ...TrainingSessionResource... } }` (kein `warnings`-Key).

**`DELETE /api/v1/courses/{course}/sessions/{session}` — Session löschen:**

Response ohne Buchungen: `204 No Content`.

Response mit Buchungen (200, kein 204, kein 422):
```json
{
  "deleted": true,
  "warnings": [
    {
      "type": "booking_conflict",
      "message": "Diese Einheit hatte 2 aktive Buchungen. Die Buchungen wurden storniert.",
      "bookingCount": 2
    }
  ]
}
```

Die Session **und** ihre Buchungen werden trotz Warnung gelöscht (`onDelete('cascade')`
ist bereits in der Bookings-Migration gesetzt — das greift automatisch).

#### 1.3.3 Öffentlicher Kursdetail-Endpoint

**Entscheidung:** Neuer unauthentifizierter Endpoint `GET /api/v1/public/courses/{course}`.

**Begründung:** Der bestehende `GET /api/v1/courses/{course}` ist hinter
`auth:sanctum`. Eine Policy-Ausnahme dort zu basteln birgt Risiko (Policy
muss auf `null`-User reagieren). Der Präzedenzfall für einen öffentlichen Prefix
ist bereits vorhanden (`/pricing-items` ist public — `Route::prefix('v1')->group()`
ohne Middleware).

Response: `CourseResource` mit `load('sessions')`, aber mit reduziertem Scope
(keine internen Felder wie `cancellationDeadlineHours` sind für öffentliche
Darstellung irrelevant, bleiben aber vorhanden — die Vue-Komponente entscheidet,
was sie zeigt).

---

## 2. Neue und geänderte Dateien

### Backend

| Datei | Status | Anmerkung |
|-------|--------|-----------|
| `database/migrations/2026_05_14_000001_add_recurrence_rule_to_courses_table.php` | **neu** | JSON nullable, MySQL+PG portabel |
| `app/Models/Course.php` | **ändern** | `recurrence_rule` in `$fillable` + `casts()` als `array` |
| `app/Services/CourseSessionService.php` | **neu** | Rekurrenz-Berechnung + Session-Sync |
| `app/Http/Requests/StoreCourseRequest.php` | **ändern** | `sessionsMode`, `sessions.*`, `recurrenceRule.*` validieren |
| `app/Http/Requests/UpdateCourseRequest.php` | **ändern** | analog |
| `app/Http/Controllers/Api/CourseController.php` | **ändern** | `store()`, `update()`, `storeSession()`, `updateSession()`, `destroySession()` |
| `backend/routes/api.php` | **ändern** | 3 neue Session-Sub-Routen + 1 öffentliche Kursroute |

### Frontend

| Datei | Status | Anmerkung |
|-------|--------|-----------|
| `frontend/src/components/CourseFormModal.vue` | **ändern** | Modus-Schalter + Session-Inputs integrieren |
| `frontend/src/components/CourseRecurrenceForm.vue` | **neu** | Formular für Wiederholungsregel |
| `frontend/src/components/CourseSessionList.vue` | **neu** | Liste + CRUD-Aktionen für Sessions |
| `frontend/src/views/CourseDetailView.vue` | **neu** | Öffentliche + Trainer-Detailseite |
| `frontend/src/router/index.ts` | **ändern** | Route `/courses/:id` hinzufügen |

---

## 3. Service-Design: `CourseSessionService`

```
app/Services/CourseSessionService.php
```

Öffentliche Methoden (PHP 8.2-kompatibel):

```php
// Erzeugt Session-Rohdaten aus einer Rekurrenzregel (keine DB-Schreibzugriffe)
public function generateFromRecurrence(array $rule, int $trainerId, int $maxParticipants): array

// Erstellt TrainingSession-Einträge aus einer Session-Liste (upsert)
// Gibt Warning-Array zurück falls Sessions mit Buchungen ignoriert wurden
public function syncSessions(Course $course, array $sessions): array  // returns warnings[]

// Prüft ob eine Session aktive Buchungen hat
public function getBookingCount(TrainingSession $session): int
```

`generateFromRecurrence` ist ein reines Value-Objekt-Kalkül ohne
Datenbankzugriff — isoliert testbar. `syncSessions` schreibt in die DB.

---

## 4. UI-Muster: Formular-Aktions-Buttons

**Entscheidung:** Alle Formulare in diesem Change (CourseFormModal, Inline-Session-Edit,
Inline-Session-Add) verwenden einheitlich drei Aktions-Buttons in dieser festen
Reihenfolge von links nach rechts:

| Button | Aktion | Verhalten |
|--------|--------|-----------|
| **Abbrechen** | Sekundär (Ghost/Outline) | Schließt Modal/Inline-Form ohne API-Aufruf; verwirft nicht gespeicherte Änderungen |
| **Zurücksetzen** | Sekundär (Outline) | Setzt alle Eingabefelder auf den Ausgangswert zurück (Anlegen → leeres Formular; Bearbeiten → ursprüngliche Werte des Datensatzes) |
| **Speichern** | Primär (Filled) | Validiert und submittet; deaktiviert während des API-Aufrufs (Loading-State) |

**Begründung:** Konsistente Button-Anordnung reduziert kognitive Last; Zurücksetzen
verhindert, dass Trainer versehentlich halbfertige Serienregeln speichern.

**Implementierungshinweis Frontend:**
- `handleCancel()` — emittiert `@close` (Modal) oder setzt `editingId = null` (Inline)
- `handleReset()` — kopiert den initialen Formzustand zurück in `form` (bei Anlegen:
  `Object.assign(form, emptyForm())`; bei Bearbeiten: `Object.assign(form, { ...originalValues })`)
- Loading-State: `const isSubmitting = ref(false)` — Speichern-Button mit `:disabled="isSubmitting"`

---

## 5. Shared-Hosting-Kompatibilität

| Aspekt | Bewertung |
|--------|-----------|
| PHP 8.2 | ✓ — kein 8.3/8.4-Feature geplant; `json_decode()` statt `json_validate()` |
| MySQL + PostgreSQL | ✓ — `$table->json()`, Eloquent-only, kein raw SQL |
| Kein Queue-Worker | ✓ — Rekurrenz-Berechnung ist synchron in `store()` |
| Kein Shell-Exec | ✓ — reine PHP-Logik |
| Build-Artefakte | ✓ — Vue-Komponenten werden über Vite gebaut, kein Node zur Laufzeit |

---

## 6. Risiken und Gegenmaßnahmen

| Risiko | Maßnahme |
|--------|----------|
| `update()` löscht Sessions mit Buchungen | `syncSessions()` überspringt Sessions mit Buchungen, gibt `warnings` zurück |
| Sehr viele Sessions (z. B. 100×) aus Rekurrenz | `count`-Maximum in Validierung auf 52 begrenzen (max. 1 Jahr wöchentlich) |
| Race Condition: zwei parallele `update()`-Aufrufe | Einfaches DB-Lock nicht nötig auf Shared Hosting; akzeptiertes Risiko |
| Öffentlicher Endpoint wird gecrawlt | `throttle`-Middleware analog zum bestehenden `throttle:contact`-Pattern |

---

## 7. Übergabepunkt Backend → Frontend

Der Frontend-Entwickler (dev-javascript) kann mit T07/T08/T09/T10 erst beginnen,
wenn T01–T04 abgenommen sind und der **API-Kontrakt** (Abschnitt 1.3) in
Staging abrufbar ist. Als Zwischenlösung kann das Frontend gegen einen lokalen
Mock-Endpoint entwickeln — der Kontrakt ist in diesem Dokument vollständig
definiert.

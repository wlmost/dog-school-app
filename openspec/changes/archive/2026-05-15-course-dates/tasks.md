# Tasks: course-dates

**Change-ID:** course-dates
**GitHub Issue:** #33

---

## T01: Migration + Course Model — ✅ DONE

- **Status:** Implementiert · Reviewed (APPROVED) · Getestet (6/6 grün) · Abgenommen
- **Agent:** dev-php
- **Dateien:**
  - `backend/database/migrations/2026_05_14_000001_add_recurrence_rule_to_courses_table.php` *(neu)*
  - `backend/app/Models/Course.php` *(ändern)*
- **Abhängigkeiten:** keine
- **Beschreibung:**
  Neue Migration, die der `courses`-Tabelle eine nullable JSON-Spalte
  `recurrence_rule` hinzufügt. Position: nach `total_sessions`.

  ```php
  $table->json('recurrence_rule')->nullable()->after('total_sessions');
  ```

  Kein `jsonb()` — MySQL + PostgreSQL portabel.

  `Course`-Modell anpassen:
  - `recurrence_rule` in `$fillable` aufnehmen
  - In `casts()` als `'array'` deklarieren (Laravel parst JSON automatisch)
  - PHPDoc-Annotation: `@property array|null $recurrence_rule`
- **Akzeptanzkriterien:**
  - [x] `php artisan migrate` läuft ohne Fehler (PostgreSQL und MySQL)
  - [x] `php artisan migrate:rollback` kehrt die Migration korrekt um
  - [x] `Course::create(['recurrence_rule' => [...]])` speichert und liest das Feld
  - [x] `Course::create([])` (ohne das Feld) funktioniert weiterhin
  - [x] `composer compat-check` meldet keine PHP-8.3/8.4-Verstöße

---

## T02: CourseSessionService — ✅ DONE

- **Status:** Implementiert · Reviewed (APPROVED nach Fixes) · Getestet (24/24 grün, 49 Assertions) · Abgenommen
- **Agent:** dev-php
- **Dateien:**
  - `backend/app/Services/CourseSessionService.php` *(neu)*
- **Abhängigkeiten:** T01
- **Beschreibung:**
  Neuer Service für die Rekurrenz-Berechnung und Session-Synchronisation.
  Kein Framework-Kopplung im Kern; DB-Zugriffe nur in `syncSessions()`.

  **Methoden:**

  ```php
  /**
   * Erzeugt Session-Rohdaten aus einer Rekurrenzregel.
   * Kein DB-Schreibzugriff — gibt ein Array von Session-Arrays zurück.
   *
   * @param array $rule  Keys: type, weekday|dayOfMonth, startTime, endTime,
   *                     startDate, count, location (nullable), maxParticipants (nullable)
   * @param int $trainerId
   * @param int $fallbackMaxParticipants  Kurs-Standardwert
   * @return array<int, array<string, mixed>>
   */
  public function generateFromRecurrence(array $rule, int $trainerId, int $fallbackMaxParticipants): array

  /**
   * Schreibt Sessions für einen Kurs in die DB.
   * Sessions mit bestehenden Buchungen werden NICHT gelöscht (werden übersprungen).
   * Gibt ein warnings[]-Array zurück für übersprungene Sessions.
   *
   * @param Course $course
   * @param array<int, array<string, mixed>> $sessions  Normalisierte Session-Daten
   * @return array<int, array<string, mixed>>  Warnings (leer wenn keine Konflikte)
   */
  public function syncSessions(Course $course, array $sessions): array

  /**
   * Gibt die Anzahl aktiver Buchungen einer Session zurück.
   */
  public function getBookingCount(TrainingSession $session): int
  ```

  **Rekurrenz-Typen:**
  - `weekly`: Gegeben `startDate`, `weekday` (0=So, 1=Mo, …, 6=Sa), `startTime`,
    `endTime`, `count` → erzeugt `count` Datumswerte ab dem ersten Wochentag
    ≥ `startDate`
  - `monthly`: Gegeben `startDate`, `dayOfMonth` (1–28), `startTime`, `endTime`,
    `count` → erzeugt `count` Monatsdaten; wenn der `dayOfMonth` im Monat nicht
    existiert (z. B. 31. Februar), wird das Datum übersprungen

  **PHP 8.2-Hinweise:**
  - Datumsberechnung über `\DateTimeImmutable` + `\DateInterval`
  - Kein `json_validate()` (8.3+); falls JSON validiert werden muss, `json_decode()`
    mit `JSON_THROW_ON_ERROR`
  - Kein `array_find()` (8.4+)

- **Akzeptanzkriterien:**
  - [x] `generateFromRecurrence(['type' => 'weekly', 'weekday' => 1, 'startDate' => '2025-03-03', ...])`
        erzeugt die korrekte Anzahl Sessions am richtigen Wochentag
  - [x] `generateFromRecurrence` mit `type = 'monthly'` erzeugt die korrekte Folge
  - [x] `syncSessions()` erstellt Sessions in der DB; Sessions mit bestehenden
        Buchungen werden nicht gelöscht
  - [x] `syncSessions()` gibt ein nichtleeres `warnings`-Array zurück, wenn
        Sessions mit Buchungen übersprungen wurden
  - [x] Unit-Tests für `generateFromRecurrence` (beide Typen) sind grün
  - [x] `composer compat-check` meldet keine Verstöße

---

## T03: Request-Validierung erweitern — ✅ DONE

- **Agent:** dev-php
- **Dateien:**
  - `backend/app/Http/Requests/StoreCourseRequest.php` *(ändern)*
  - `backend/app/Http/Requests/UpdateCourseRequest.php` *(ändern)*
- **Abhängigkeiten:** T02 (wegen API-Kontrakt-Shape)
- **Beschreibung:**
  Beide Request-Klassen erhalten optionale neue Validierungsregeln.
  Bestehende Regeln bleiben unverändert (Abwärtskompatibilität).

  **Neue Felder in `StoreCourseRequest::rules()`:**

  ```php
  'sessionsMode'                        => ['nullable', 'in:manual,recurrence'],
  // manuell:
  'sessions'                            => ['nullable', 'array', 'max:52',
                                           'required_if:sessionsMode,manual'],
  'sessions.*.sessionDate'              => ['required', 'date'],
  'sessions.*.startTime'                => ['required', 'date_format:H:i'],
  'sessions.*.endTime'                  => ['required', 'date_format:H:i', 'after:sessions.*.startTime'],
  'sessions.*.location'                 => ['nullable', 'string', 'max:255'],
  'sessions.*.maxParticipants'          => ['nullable', 'integer', 'min:1', 'max:50'],
  // Rekurrenz:
  'recurrenceRule'                      => ['nullable', 'array',
                                           'required_if:sessionsMode,recurrence'],
  'recurrenceRule.type'                 => ['required_with:recurrenceRule', 'in:weekly,monthly'],
  'recurrenceRule.weekday'              => ['required_if:recurrenceRule.type,weekly', 'integer', 'min:0', 'max:6'],
  'recurrenceRule.dayOfMonth'           => ['required_if:recurrenceRule.type,monthly', 'integer', 'min:1', 'max:28'],
  'recurrenceRule.startTime'            => ['required_with:recurrenceRule', 'date_format:H:i'],
  'recurrenceRule.endTime'              => ['required_with:recurrenceRule', 'date_format:H:i'],
  'recurrenceRule.startDate'            => ['required_with:recurrenceRule', 'date'],
  'recurrenceRule.count'                => ['required_with:recurrenceRule', 'integer', 'min:1', 'max:52'],
  'recurrenceRule.location'             => ['nullable', 'string', 'max:255'],
  'recurrenceRule.maxParticipants'      => ['nullable', 'integer', 'min:1', 'max:50'],
  ```

  In `UpdateCourseRequest` identisch, aber alle Session-Felder zusätzlich `nullable`
  (beim Update kann man Sessions auch komplett weglassen).

  `validatedSnakeCase()` muss `sessions` und `recurrenceRule` aus dem Rückgabe-Array
  **ausschließen** (sie werden nicht direkt via `Course::create/update` geschrieben).
  Stattdessen: separate Getter `getSessionsPayload(): ?array` und
  `getRecurrenceRule(): ?array`.

- **Akzeptanzkriterien:**
  - [x] Ein Request mit `sessionsMode = manual` und fehlendem `sessions`-Array
        gibt Validierungsfehler 422 zurück
  - [x] Ein Request ohne `sessionsMode` wird ohne Fehler akzeptiert (Abwärtskompatibilität)
  - [x] `recurrenceRule.count` > 52 gibt Validierungsfehler zurück
  - [x] `validatedSnakeCase()` enthält **kein** `sessions`- oder `recurrence_rule`-Feld
        aus dem neuen Request-Input
  - [x] Bestehende Feature-Tests für `StoreCourseRequest` bleiben grün

---

## T04: CourseController erweitern — ✅ DONE

- **Status:** Implementiert · Reviewed (APPROVED nach Security-Fixes) · Getestet (21/21 grün) · Abgenommen · Response-Shape vereinheitlicht (`meta.warnings`)
- **Agent:** dev-php
- **Dateien:**
  - `backend/app/Http/Controllers/Api/CourseController.php` *(ändern)*
  - `backend/routes/api.php` *(ändern)*
- **Abhängigkeiten:** T02, T03
- **Hinweise aus T03-Abnahme (Architekt):**
  - `$request->getRecurrenceRule()` liefert bereits **snake_case-Keys**
    (`start_date`, `day_of_month`, `start_time`, `end_time`, `max_participants` usw.) →
    Array kann **direkt** an `CourseSessionService::generateFromRecurrence()` übergeben werden,
    keine eigene Konvertierung im Controller nötig.
  - `$request->getSessionsPayload()` liefert **camelCase-Keys** (`sessionDate`, `startTime`,
    `endTime`). `syncSessions()` erwartet snake_case-Keys (`session_date`, `start_time`,
    `end_time`). → Controller muss die Keys vor dem `syncSessions()`-Aufruf konvertieren.
    Empfehlung: Hilfsmethode `normalizeSessionKeys(array $sessions): array` mit
    `Str::snake()` pro Key.
  - `getBookingCount()` / `syncSessions()` — laut T02-Abnahme bereits defensiv gegen
    `null`-Werte. Kein zusätzlicher Null-Check im Controller nötig.
- **Beschreibung:**

  **`store()` erweitern:**
  Nach `Course::create(...)` prüfen ob `$request->getSessionsPayload()` oder
  `$request->getRecurrenceRule()` befüllt ist. Falls ja:
  - Beim manuellen Modus: Sessions-Array zuerst normalisieren (camelCase → snake_case),
    dann `CourseSessionService::syncSessions()` aufrufen
  - Beim Rekurrenz-Modus: Zuerst `generateFromRecurrence()`, dann `syncSessions()`
  - `Course` danach mit `load(['trainer', 'sessions'])` zurückgeben
  - Warnings aus `syncSessions()` als `meta.warnings` in die JSON-Antwort einbetten
    (über eine `additional()`-Methode des `JsonResource` oder manuelles Array-Merge)

  **`update()` erweitern:** analog zu `store()`.

  **Neue Methoden:**

  ```php
  public function storeSession(StoreCourseSessionRequest $request, Course $course): JsonResponse

  public function updateSession(UpdateCourseSessionRequest $request, Course $course, TrainingSession $session): JsonResponse

  public function destroySession(Course $course, TrainingSession $session): JsonResponse
  ```

  - `updateSession()` und `destroySession()` rufen `CourseSessionService::getBookingCount()`
    auf. Bei Buchungsanzahl > 0 wird ein `warnings`-Key in die Antwort eingebettet.
  - `destroySession()` löscht die Session (`$session->delete()`); Cascade auf Bookings
    ist bereits in der Migration gesetzt — keine manuelle Buchungslöschung nötig.
  - **Neue FormRequests erstellen:**
    `StoreCourseSessionRequest` und `UpdateCourseSessionRequest`
    (in `app/Http/Requests/`):

    ```php
    // StoreCourseSessionRequest Felder (required):
    'sessionDate', 'startTime', 'endTime'
    // optional: 'location', 'maxParticipants', 'status', 'notes'

    // UpdateCourseSessionRequest: alle Felder 'sometimes'
    ```

  **Neue öffentliche Methode:**

  ```php
  public function publicShow(Course $course): CourseResource
  ```

  Kein `authorize()`-Aufruf. Gibt `CourseResource` mit `load('sessions')` zurück.
  Sessions werden nach `session_date` aufsteigend sortiert zurückgegeben.

  **Routen in `routes/api.php`:**

  ```php
  // Öffentlich (kein auth:sanctum, mit Throttle)
  Route::prefix('v1')->middleware('throttle:60,1')->group(function () {
      Route::get('/public/courses/{course}', [CourseController::class, 'publicShow']);
  });

  // Geschützt — in den bestehenden auth:sanctum-Block hinzufügen:
  Route::post('/courses/{course}/sessions', [CourseController::class, 'storeSession']);
  Route::put('/courses/{course}/sessions/{session}', [CourseController::class, 'updateSession']);
  Route::delete('/courses/{course}/sessions/{session}', [CourseController::class, 'destroySession']);
  ```

- **Akzeptanzkriterien:**
  - [x] `POST /api/v1/courses` mit `sessionsMode = recurrence` erstellt Kurs +
        korrekte Anzahl `TrainingSession`-Einträge in der DB
  - [x] `POST /api/v1/courses` ohne `sessionsMode` erstellt Kurs ohne Sessions
        (Abwärtskompatibilität)
  - [x] `POST /api/v1/courses/{course}/sessions` erstellt eine neue Session; Response 201
  - [x] `PUT /api/v1/courses/{course}/sessions/{session}` gibt bei Session mit Buchungen
        `{ "data": {...}, "meta": { "warnings": [...] } }` zurück; HTTP 200
  - [x] `DELETE /api/v1/courses/{course}/sessions/{session}` gibt bei Session mit Buchungen
        `{ "deleted": true, "warnings": [...] }` zurück; HTTP 200
  - [x] `DELETE /api/v1/courses/{course}/sessions/{session}` ohne Buchungen gibt 204 zurück
  - [x] `GET /api/v1/public/courses/{course}` ist ohne Auth-Header erreichbar;
        gibt Kurs + Sessions zurück
  - [x] `GET /api/v1/public/courses/{course}` ist für nicht-existierende Kurse 404

---

## T05: Backend-Tests — ✅ DONE

- **Status:** Implementiert · Reviewed (APPROVED nach Fixes) · Getestet (alle Tests grün) · Abgenommen
- **Agent:** dev-php
- **Dateien:**
  - `backend/tests/Unit/Services/CourseSessionServiceUnitTest.php` *(neu)*
  - `backend/tests/Feature/Domain/CourseSessionServiceSyncTest.php` *(neu)*
  - `backend/tests/Feature/Services/CourseSessionServiceFeatureTest.php` *(neu)*
  - `backend/tests/Feature/CourseController/StoreWithSessionsTest.php` *(neu)*
  - `backend/tests/Feature/CourseController/SessionManagementTest.php` *(neu)*
- **Abhängigkeiten:** T02, T04
- **Beschreibung:**
  Pest-Syntax (Engine läuft über PHPUnit — siehe TESTING.md).

  **Unit-Tests `CourseSessionServiceTest`:**
  - `generateFromRecurrence` wöchentlich: korrekte Anzahl, richtiger Wochentag
  - `generateFromRecurrence` monatlich: korrekte Monate
  - `generateFromRecurrence` monatlich mit ungültigem `dayOfMonth` (z. B. 31 im Februar):
    Datum wird übersprungen
  - `syncSessions` mit Session ohne Buchungen: Session wird angelegt
  - `syncSessions` mit Session mit Buchungen: Session wird **nicht** gelöscht,
    Warning zurückgegeben

  **Feature-Tests `StoreWithSessionsTest`:**
  - Store mit `sessionsMode = manual`, gültige Sessions → 201, Sessions in DB
  - Store mit `sessionsMode = recurrence`, wöchentlich 4× → 201, 4 Sessions in DB
  - Store ohne `sessionsMode` → 201, keine Sessions in DB (Abwärtskompatibilität)
  - Store mit ungültigem `recurrenceRule.count` (> 52) → 422

  **Feature-Tests `SessionManagementTest`:**
  - `storeSession` als Trainer → 201, Session in DB
  - `storeSession` als Kunde → 403
  - `updateSession` ohne Buchungen → 200, kein `warnings`-Key
  - `updateSession` mit Buchungen → 200, `warnings`-Key vorhanden
  - `destroySession` ohne Buchungen → 204
  - `destroySession` mit Buchungen → 200, `{ "deleted": true, "warnings": [...] }`
  - `publicShow` ohne Auth → 200 mit Sessions
  - `publicShow` für nicht-existierenden Kurs → 404

- **Akzeptanzkriterien:**
  - [x] `composer test` ist vollständig grün
  - [x] Alle neuen Tests verwenden Factory-States (keine Magic Strings für Status-Werte)
  - [x] Alle API-Tests verwenden `actingAs()` + HTTP-Assertions Laravel-Style
  - [x] `composer compat-check` meldet keine Verstöße

---

## T06: CourseRecurrenceForm-Komponente — ✅ DONE

- **Status:** Implementiert · Reviewed (APPROVED) · Getestet (16/16 grün) · Abgenommen
- **Agent:** dev-typescript
- **Dateien:**
  - `frontend/src/components/CourseRecurrenceForm.vue` *(neu)*
- **Abhängigkeiten:** T03 (API-Kontrakt aus `design.md` — kein laufendes Backend erforderlich)
- **Beschreibung:**
  Neue SFC-Komponente für die Konfiguration einer Wiederholungsregel.
  `<script setup lang="ts">` Composition API — konsistent mit allen bestehenden
  SFCs im Projekt.

  **Props/Emits (TypeScript):**
  ```ts
  interface RecurrenceRule {
    type: 'weekly' | 'monthly'
    weekday?: number        // 0–6, nur bei type='weekly'
    dayOfMonth?: number     // 1–28, nur bei type='monthly'
    startTime: string       // 'HH:MM'
    endTime: string         // 'HH:MM'
    startDate: string       // 'YYYY-MM-DD'
    count: number           // 1–52
    location?: string | null
    maxParticipants?: number | null
  }

  const props = defineProps<{ modelValue: RecurrenceRule | null }>()
  const emit = defineEmits<{ 'update:modelValue': [value: RecurrenceRule] }>()
  ```

  **Formularfelder:**
  - Typ-Auswahl: `select` mit Optionen „Wöchentlich" (`weekly`) / „Monatlich" (`monthly`)
  - Bei `weekly`: Wochentag-Auswahl (`select`, Montag–Sonntag, Wert 0–6)
  - Bei `monthly`: Tag des Monats (`input[type=number]`, min=1, max=28)
  - Startdatum (`input[type=date]`)
  - Startzeit / Endzeit (`input[type=time]`)
  - Anzahl Einheiten (`input[type=number]`, min=1, max=52)
  - Ort (`input[type=text]`, optional)
  - Max. Teilnehmer (`input[type=number]`, optional)

  Vorschau-Text unterhalb des Formulars:
  „Jeden Montag ab 03.03.2025, 10:00–11:00 Uhr, 8 Einheiten"

  Das Komponent emittiert ein `recurrenceRule`-Objekt passend zum API-Schema
  aus `design.md` (Abschnitt 1.3.1).

- **Akzeptanzkriterien:**
  - [x] Komponent rendert bei `type = weekly` den Wochentag-Selector
  - [x] Komponent rendert bei `type = monthly` den Monatstag-Input
  - [x] Vorschau-Text ändert sich reaktiv beim Ändern der Felder
  - [x] Emittiertes Objekt entspricht dem API-Schema (Felder `type`, `weekday`/`dayOfMonth`,
        `startTime`, `endTime`, `startDate`, `count`, `location`, `maxParticipants`)
  - [x] `npm run test` (Vitest) — Snapshot-/Unit-Test für das emittierte Objekt grün
  - [x] `npm run build` ohne Warnings

---

## T07: CourseFormModal erweitern — ✅ DONE

- **Status:** Implementiert · Reviewed (APPROVED) · Getestet (34/34 grün) · Abgenommen
- **Agent:** dev-typescript
- **Dateien:**
  - `frontend/src/components/CourseFormModal.vue` *(ändern)*
- **Abhängigkeiten:** T06
- **Beschreibung:**
  Abschnitt „Termine" im Modal ergänzen. Unterhalb des bestehenden
  „Dates and Times"-Blocks einen neuen Block „Kurs-Einheiten" einfügen.

  **Modus-Schalter:**
  ```html
  <!-- None / Manual / Recurrence -->
  <select v-model="form.sessionsMode">
    <option value="">Keine Einheiten jetzt festlegen</option>
    <option value="manual">Einzeltermine manuell eintragen</option>
    <option value="recurrence">Terminserie definieren</option>
  </select>
  ```

  **Modus „manual":** Dynamische Liste (`v-for`) von Zeilen mit
  `sessionDate`, `startTime`, `endTime`, `location`-Inputs.
  Schaltflächen „+ Termin hinzufügen" und „× entfernen" je Zeile.

  **Modus „recurrence":** Einbindung von `<CourseRecurrenceForm v-model="form.recurrenceRule" />`.

  **Button-Leiste (unterhalb der Formularfelder):**
  Drei Buttons in dieser festen Reihenfolge:
  1. **Abbrechen** — schließt das Modal ohne zu speichern (emittiert `@close`)
  2. **Zurücksetzen** — setzt alle Formularwerte auf den Ausgangszustand zurück
     (beim Anlegen: leeres Formular; beim Bearbeiten: ursprüngliche Kurswerte)
  3. **Speichern** — submittet das Formular (bestehende Logik)

  **Payload beim Submit:**
  In `handleSubmit()` wird `sessionsMode` aus `form` in den API-Payload übernommen:
  - Bei `manual`: `sessions: form.sessions`
  - Bei `recurrence`: `recurrenceRule: form.recurrenceRule`
  - Bei `""`: weder `sessions` noch `recurrenceRule` in den Payload

  **Warning-Handling:**
  Wenn die API-Antwort (store oder update) ein `meta.warnings`-Array enthält,
  wird ein Toast/Alert angezeigt: „Hinweis: X Session(s) konnten wegen
  bestehender Buchungen nicht verändert werden."

  **Beim Bearbeiten (edit-Modus):**
  Die bestehenden Sessions werden **nicht** in die Form geladen (Bearbeitung
  von Einzelterminen erfolgt über `CourseSessionList` außerhalb des Modals).
  Der `sessionsMode` steht auf `""` wenn der Kurs geöffnet wird.

- **Akzeptanzkriterien:**
  - [x] Modus-Schalter ist sichtbar und funktional
  - [x] Manual-Modus: Termine können dynamisch hinzugefügt und entfernt werden
  - [x] Recurrence-Modus: `CourseRecurrenceForm` wird korrekt eingebettet
  - [x] Payload enthält `sessions` (manual) oder `recurrenceRule` (recurrence)
        oder keines der beiden (kein Modus gewählt)
  - [x] Warn-Toast erscheint wenn `meta.warnings` in der Antwort vorhanden sind
  - [x] Modal hat die drei Buttons **Abbrechen / Zurücksetzen / Speichern** in dieser Reihenfolge
  - [x] „Abbrechen" schließt das Modal ohne API-Aufruf
  - [x] „Zurücksetzen" stellt den Formularinhalt auf den Ausgangswert zurück
  - [x] Bestehende Kurs-Create/Update-Tests (falls vorhanden) bleiben grün
  - [x] `npm run build` ohne Warnings

---

## T08: CourseSessionList-Komponente — ✅ DONE

- **Status:** Implementiert · Reviewed (APPROVED, SOLLTE-Befunde fixiert) · Getestet (34/34 grün) · Abgenommen
- **Agent:** dev-typescript
- **Dateien:**
  - `frontend/src/components/CourseSessionList.vue` *(neu)*
- **Abhängigkeiten:** T04 (Session-Management-Endpoints — kann gegen Mock entwickeln)
- **Beschreibung:**
  Komponente zur Anzeige und Verwaltung von Kurs-Sessions.

  **Props (TypeScript — `<script setup lang="ts">`):**
  ```ts
  const props = withDefaults(defineProps<{
    courseId: number
    editable?: boolean
  }>(), { editable: false })
  ```

  **Verhalten:**
  - Beim Mount: `GET /api/v1/courses/{courseId}/sessions` laden
  - Tabelle/Liste mit Spalten: Datum, Uhrzeit, Ort, Status, Teilnehmer (belegend/max)
  - Bei `editable = true`: je Zeile Buttons „Bearbeiten" (öffnet ein Inline-Formular
    oder kleines Modal) und „Löschen"
  - „+ Termin hinzufügen"-Button bei `editable = true`
  - **Löschen mit Buchungen:** Wenn die DELETE-Response `warnings` enthält,
    Confirm-Dialog anzeigen: „Diese Einheit hat X Buchungen. Wirklich löschen?"
    → Trainer hat den Löschen-Button bereits betätigt und API hat schon gelöscht
    (die API löscht immer). Der Dialog informiert im Nachhinein. Alternativ:
    Vor dem API-Aufruf erst prüfen ob Sessions Buchungen haben (via `bookings`-Count
    in der Session-Response), dann Confirm-Dialog zeigen — **diese Variante ist
    bevorzugt** (bessere UX).

    Implementierungs-Hinweis: `TrainingSessionResource` enthält bereits
    `bookings` wenn mit `load('bookings')` geladen — dev-javascript prüft
    ob `session.bookingsCount` oder `session.bookings.length > 0` verfügbar
    ist (nach Abstimmung mit Backend-Response aus T04).

  - **Bearbeiten:** Einfaches Inline-Form-Pattern mit `v-if="editingId === session.id"`.
    Felder: `sessionDate`, `startTime`, `endTime`, `location`. Button-Leiste:
    **Abbrechen** (schließt Inline-Form, verwirft Änderungen),
    **Zurücksetzen** (stellt ursprüngliche Session-Werte wieder her),
    **Speichern** (ruft `PUT /api/v1/courses/{courseId}/sessions/{sessionId}` auf).
    Wenn Response `warnings` enthält: Toast anzeigen.

  - **Termin hinzufügen:** Inline-Form mit denselben Feldern. Button-Leiste:
    **Abbrechen** (schließt Formular ohne API-Aufruf),
    **Zurücksetzen** (leert alle Eingabefelder),
    **Speichern** (ruft `POST /api/v1/courses/{courseId}/sessions` auf).

- **Akzeptanzkriterien:**
  - [x] Sessions werden geladen und in Listenform angezeigt
  - [x] Bei `editable = false` sind keine Bearbeiten/Löschen-Buttons sichtbar
  - [x] Session-Löschen mit Buchungen zeigt Warn-Dialog vor API-Aufruf
    (wenn `session.bookings.length > 0`) oder zeigt Toast nach API-Antwort
    mit `warnings`
  - [x] Session bearbeiten: Inline-Form zeigt Buttons **Abbrechen / Zurücksetzen / Speichern**
  - [x] „Abbrechen" im Bearbeiten-Form schließt die Zeile ohne Änderungen
  - [x] „Zurücksetzen" im Bearbeiten-Form stellt die ursprünglichen Session-Werte wieder her
  - [x] Session bearbeiten und speichern aktualisiert den Eintrag in der Liste
  - [x] „+ Termin hinzufügen" öffnet ein Inline-Formular mit **Abbrechen / Zurücksetzen / Speichern**
  - [x] Speichern des neuen Termins ruft `POST /api/v1/courses/{courseId}/sessions` auf
  - [x] `npm run build` ohne Warnings

---

## T09: CourseDetailView — ✅ DONE

- **Agent:** dev-typescript
- **Dateien:**
  - `frontend/src/views/CourseDetailView.vue` *(neu)*
  - `frontend/src/router/index.ts` *(ändern)*
- **Abhängigkeiten:** T08
- **Beschreibung:**
  Neue View-Komponente für die Kursdetailseite.

  **Route (`<script setup lang="ts">`, Router-Datei: `frontend/src/router/index.ts`):**
  ```ts
  {
    path: '/courses/:id',
    name: 'course-detail',
    component: () => import('@/views/CourseDetailView.vue'),
    meta: { requiresAuth: false }  // öffentlich zugänglich
  }
  ```

  **Daten laden:**
  - Nicht eingeloggt oder Rolle Customer: `GET /api/v1/public/courses/{id}` (kein Auth-Header)
  - Eingeloggt als Trainer/Admin: `GET /api/v1/courses/{id}` (mit Auth, lädt Sessions)

  **Anzeige:**
  - Kursname, Beschreibung, Typ, Trainer-Name, Preis pro Einheit
  - `<CourseSessionList :courseId="course.id" :editable="isTrainerOrAdmin" />`
  - Für Kunden: Schaltfläche „Termin buchen" (verweist auf bestehenden Buchungsflow)
  - Für Trainer: Schaltfläche „Kurs bearbeiten" (öffnet bestehenden `CourseFormModal`)

  **Responsive:** Mobile-first, bestehende Tailwind-Klassen nutzen.

- **Akzeptanzkriterien:**
  - [x] Route `/courses/:id` ist erreichbar ohne Login (öffentlich)
  - [x] Kurs-Infos werden korrekt angezeigt
  - [x] Sessions werden via `CourseSessionList` angezeigt
  - [x] Für eingeloggte Trainer ist `CourseSessionList` editierbar
  - [x] Für nicht eingeloggte User / Kunden ist die Liste nur lesend
  - [x] 404-Anzeige für nicht-existierende Kurse
  - [x] `npm run build` ohne Warnings

---

## Abhängigkeitsgraph

```
T01 (Migration + Model)
  └─► T02 (CourseSessionService)
        └─► T03 (Request-Validierung)
              └─► T04 (Controller + Routes)
                    └─► T05 (Backend-Tests)

T06 (CourseRecurrenceForm)    [parallel zu T01–T05 startbar]
  └─► T07 (CourseFormModal)
        └─► T08 (CourseSessionList)   [blockiert auf T04 für Live-API]
              └─► T09 (CourseDetailView)
```

**Übergabepunkt Backend → Frontend:**
T06 und T07 können direkt gestartet werden (API-Kontrakt ist in `design.md`
definiert). T08 und T09 sollten warten bis T04 abgenommen ist.

---

## Schätzung Gesamtumfang

| Task | Dateien | Komplexität |
|------|---------|-------------|
| T01 | 2 | niedrig |
| T02 | 1 | mittel |
| T03 | 2 | niedrig |
| T04 | 4 | mittel–hoch |
| T05 | 3 | mittel |
| T06 | 1 | mittel |
| T07 | 1 | mittel |
| T08 | 1 | mittel–hoch |
| T09 | 2 | niedrig–mittel |

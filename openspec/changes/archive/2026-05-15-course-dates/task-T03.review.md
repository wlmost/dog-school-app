# Review: T03 — Request-Validierung

**Reviewer:** reviewer-agent
**Datum:** 2026-05-14
**Status:** APPROVED *(mit Anmerkungen — keine Blocker)*

---

## Befunde

### 🔴 MUSS (Blocker)

*Keine.*

---

### 🟡 SOLLTE (Non-Blocker)

#### [Korrektheit] `UpdateCourseRequest` — `sometimes` auf required Session-Sub-Feldern macht Validierung zu permissiv

**Betroffen:** `backend/app/Http/Requests/UpdateCourseRequest.php`, Zeilen 51–56 und 67–72

Die Session-Sub-Felder `sessions.*.sessionDate`, `sessions.*.startTime` und `sessions.*.endTime`
sowie die Recurrence-Sub-Felder `recurrenceRule.type`, `recurrenceRule.startTime`,
`recurrenceRule.endTime`, `recurrenceRule.startDate`, `recurrenceRule.count` haben
`['sometimes', 'required', ...]` bzw. `['sometimes', 'required_with:...', ...]`.

Das `sometimes`-Präfix bewirkt bei Wildcard-Array-Regeln: Fehlt der Key
`sessionDate` in einem Session-Objekt, überspringt der Validator *alle* Regeln
einschließlich `required`. Damit kann ein Client

```json
{ "sessions": [{ "startTime": "09:00", "endTime": "10:00" }] }
```

als `UpdateCourse`-Request schicken und bekommt HTTP 200 statt 422 zurück.
`syncSessions()` in T04 würde dann mit einem unvollständigen Session-Array
arbeiten und auf DB-Ebene mit einem `NOT NULL`-Fehler oder einem unbehandelten
`null`-Datum brechen — statt sauber mit 422 zu scheitern.

Die Spec sagt: *"In `UpdateCourseRequest` identisch, aber alle Session-Felder
zusätzlich `nullable`"* — gemeint ist, dass das *Array* `sessions` nullable ist
(also die ganze Sessions-Sektion entfallen kann), nicht die einzelnen
Pflichtfelder innerhalb eines Session-Items. Das ist bereits durch `['sometimes',
'nullable', 'array', ...]` auf `sessions` selbst abgedeckt.

**Empfehlung:** `sometimes` von den drei required Session-Sub-Feldern sowie von
den required Recurrence-Sub-Feldern entfernen:

```php
// War:
'sessions.*.sessionDate' => ['sometimes', 'required', 'date'],
'sessions.*.startTime'   => ['sometimes', 'required', 'date_format:H:i'],
'sessions.*.endTime'     => ['sometimes', 'required', 'date_format:H:i', 'after:sessions.*.startTime'],
'recurrenceRule.type'    => ['sometimes', 'required_with:recurrenceRule', 'in:weekly,monthly'],
// ... analog für startTime, endTime, startDate, count

// Wird:
'sessions.*.sessionDate' => ['required', 'date'],
'sessions.*.startTime'   => ['required', 'date_format:H:i'],
'sessions.*.endTime'     => ['required', 'date_format:H:i', 'after:sessions.*.startTime'],
'recurrenceRule.type'    => ['required_with:recurrenceRule', 'in:weekly,monthly'],
// ... analog
```

`sessions.*.location` und `sessions.*.maxParticipants` sind nullable — dort kann
`sometimes` bleiben oder entfernt werden (nullable allein reicht).

---

#### [Stil/Sicherheit] `getSessionsPayload()` und `getRecurrenceRule()` greifen auf `$this->input()` statt `$this->validated()` zu

**Betroffen:**
- `StoreCourseRequest.php` Zeilen 113, 130
- `UpdateCourseRequest.php` Zeilen 108, 126

Beide Getter verwenden `$this->input('sessions')` / `$this->input('recurrenceRule')`.
Das sind Rohwerte vor dem Validierungsprozess. Obwohl in der Praxis sicher
(Getter werden erst im Controller nach bestandener Validierung aufgerufen),
ist `$this->validated()` das idiomatische Laravel-Pattern für FormRequests und
garantiert, dass ausschließlich validierte Daten aus einem Request-Getter
zurückgegeben werden.

**Empfehlung:**

```php
public function getSessionsPayload(): ?array
{
    $sessions = $this->validated()['sessions'] ?? null;
    if (\!is_array($sessions) || empty($sessions)) {
        return null;
    }
    return $sessions;
}

public function getRecurrenceRule(): ?array
{
    $rule = $this->validated()['recurrenceRule'] ?? null;
    if (\!is_array($rule) || empty($rule)) {
        return null;
    }
    $converted = [];
    foreach ($rule as $key => $value) {
        $converted[Str::snake((string) $key)] = $value;
    }
    return $converted;
}
```

---

### 🟢 OK

- **Spec-Konformität (Store):** Alle 20 neuen Felder vorhanden, Regeln exakt wie
  in der Spec — `required_if:sessionsMode,manual`, `required_if:sessionsMode,recurrence`,
  `required_with:recurrenceRule`, `required_if:recurrenceRule.type,weekly/monthly` —
  alles korrekte Laravel-Syntax, auch für Nested-Array-Felder via Dot-Notation. ✅

- **`required_if` Syntax:** `required_if:sessionsMode,manual` (ohne `=,`) ist die
  korrekte Laravel-Form. ✅

- **`validatedSnakeCase()` — alle drei Felder ausgeschlossen:** `sessions`,
  `recurrence_rule` (via `Str::snake('recurrenceRule')`) und `sessions_mode`
  (via `Str::snake('sessionsMode')`) werden korrekt per `unset()` entfernt.
  Mass-Assignment über `Course::create/update` ist damit nicht möglich. ✅

- **camelCase → snake_case in `getRecurrenceRule()`:** `Str::snake()` auf alle Keys —
  `startDate → start_date`, `dayOfMonth → day_of_month`, `maxParticipants → max_participants`,
  `startTime → start_time`, `endTime → end_time`; Keys ohne camelCase-Anteil
  (`type`, `weekday`, `count`, `location`) bleiben korrekt unverändert. ✅

- **`getSessionsPayload()` null-Guard:** `\!is_array($sessions) || empty($sessions)`
  deckt null, Non-Array und leeres Array korrekt ab. ✅

- **PHP 8.2-Konformität:** `declare(strict_types=1)` vorhanden, alle Return-Types
  deklariert, kein `json_validate()`, kein `array_find()`, keine 8.3/8.4-Features. ✅

- **Payload-Größenbegrenzung:** `max:52` auf `sessions` (Array-Länge) und
  `recurrenceRule.count` schützt gegen absichtlich überdimensionierte Requests. ✅

- **Abwärtskompatibilität:** `sessionsMode` ist `nullable` (Store) / `sometimes,nullable`
  (Update) — ein Request ohne `sessionsMode` wird in beiden Klassen ohne Fehler
  akzeptiert. ✅

- **Status-Default nur in Store:** `if (\!isset($snakeCase['status'])) → 'planned'`
  ist nur in `StoreCourseRequest`, nicht in `UpdateCourseRequest`. Korrekt —
  beim Update soll der bestehende Status nicht überschrieben werden. ✅

---

## KÖNNTE (Optional)

**`recurrenceRule.endTime` fehlt `after:recurrenceRule.startTime`:**
`sessions.*.endTime` hat korrekt `after:sessions.*.startTime`, aber
`recurrenceRule.endTime` hat diese Gegenlauf-Prüfung nicht (auch nicht in der Spec).
Ein Rekurrenzregel mit `endTime < startTime` würde bis in den Service durchschlagen.
Wäre ein sinnvoller Schutz, ist aber kein Blocker für T03.

---

## Fazit

Keine Blocker. Der Code ist spec-konform, PHP-8.2-kompatibel und sicher gegen
Mass-Assignment. Das `sometimes`-Problem in `UpdateCourseRequest` ist eine
praxisrelevante Schwäche der Validierungsschicht (inkonsistente 422-Abdeckung vor
T04's Serviceaufrufen) und sollte vor T04 behoben werden, um saubere Fehlermeldungen
sicherzustellen.

**Empfehlung:** Fix der `sometimes`-Regeln in `UpdateCourseRequest` durchführen,
dann kann T04 ohne Vorbehalt gestartet werden.

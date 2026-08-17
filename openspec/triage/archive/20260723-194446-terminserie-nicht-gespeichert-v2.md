# Triage: Terminserie nicht gespeichert / nicht dargestellt (v2)

**Pfad:** klein
**Geschätzter Umfang:** 3 Dateien, PHP + TypeScript
**Risiko:** niedrig — keine Schnittstellen-Breaks, kein Auth-Eingriff, nur additive API-Response-Ergänzung und Frontend-Initialisierung
**Klarheit:** klar — alle Problemstellen sind durch Code-Analyse eindeutig lokalisiert

---

## Anforderung (Zusammenfassung)

PR #74 hat `recurrence_rule` korrekt aus dem `unset()` in `validatedSnakeCase()` entfernt
(Backend speichert die Regel jetzt korrekt in die DB). Dennoch wird die Terminserie nach dem
Bearbeiten eines Kurses nicht aktualisiert und in der Kurskachel nicht angezeigt.

Die Untersuchung ergibt: Das Problem liegt **nicht** mehr im Controller-/Request-Flow,
sondern an zwei weiteren Stellen — einer im Backend (API-Response) und einer im Frontend
(Formular-Initialisierung beim Bearbeiten).

---

## Gefundene Problemstellen (mit Datei + Zeile)

### BUG A — KRITISCH — Backend: `CourseResource` enthält `recurrenceRule` nicht

**Datei:** [backend/app/Http/Resources/CourseResource.php](backend/app/Http/Resources/CourseResource.php#L18-L41)

`toArray()` gibt folgende Felder zurück, aber `recurrenceRule` fehlt vollständig:

```php
// Zeile 19–41 — recurrenceRule ist NICHT dabei
return [
    'id'                        => $this->id,
    'trainerId'                 => $this->trainer_id,
    'name'                      => $this->name,
    // …
    // ← hier fehlt: 'recurrenceRule' => $this->recurrence_rule,
    // ← hier fehlt: 'durationMinutes' => $this->duration_minutes,
    // ← hier fehlt: 'totalSessions'   => $this->total_sessions,
    // ← hier falsch: 'price' => $this->price  (Model hat price_per_session, nicht price)
];
```

**Konsequenz:** Selbst wenn die DB den Wert korrekt gespeichert hat, erhält das
Frontend bei GET /api/v1/courses und GET /api/v1/courses/{id} nie eine `recurrenceRule`.
Das Formular kann das Feld daher nicht vorausfüllen.

**Nebeneffekt:** Weil auch `durationMinutes`, `totalSessions` und `pricePerSession`
(falsch als `price` — Model hat `price_per_session`) fehlen, zeigt das Bearbeitungsformular
für diese Felder stets die Standardwerte (`60`, `8`, `25`), nicht die gespeicherten Werte.

---

### BUG B — KRITISCH — Frontend: Bearbeitungsformular initialisiert `sessionsMode` und `recurrenceRule` nicht aus den Kursdaten

**Datei:** [frontend/src/components/CourseFormModal.vue](frontend/src/components/CourseFormModal.vue#L304-L325)

Der `watch`-Callback für das `course`-Prop setzt beim Öffnen des Bearbeitungsmodals:

```typescript
// Zeile ~305–322
watch(() => props.course, (newCourse) => {
  if (newCourse) {
    form.value = {
      // …
      sessionsMode: '',        // ← IMMER leer, ignoriert bestehende Mode
      sessions: [],
      recurrenceRule: null     // ← IMMER null, ignoriert bestehende Regel
    }
  }
})
```

Dasselbe Problem in `resetForm()`:

```typescript
// Zeile ~330–358
function resetForm() {
  if (props.course) {
    form.value = {
      // …
      sessionsMode: '',        // ← IMMER leer
      sessions: [],
      recurrenceRule: null     // ← IMMER null
    }
  }
}
```

**Konsequenz:** Beim Bearbeiten eines Kurses mit Terminserie:
1. `sessionsMode` bleibt `''` → Recurrence-Formular wird nie angezeigt
2. `recurrenceRule` bleibt `null` → `handleSubmit()` schickt weder `sessionsMode`
   noch `recurrenceRule` an die API (nur `basePayload`)
3. Der Controller bekommt kein `recurrenceRule` → `getRecurrenceRule()` → `null`
   → keine Session-Regenerierung
4. Die bestehende `recurrence_rule` in der DB bleibt erhalten (wird nicht überschrieben),
   aber Änderungen an der Regel sind unmöglich, und der User sieht die bestehende Regel nie

**Abhängigkeit von Bug A:** Selbst wenn Bug B behoben wird (`recurrenceRule` aus `course`
lesen), scheitert das am Fehlen von Bug A-Fix — da die API `recurrenceRule` gar nicht
zurückgibt, wäre `course.recurrenceRule` immer `undefined`.

---

### BUG C — UX — Frontend: Kurskachel zeigt keine Terminserie

**Datei:** [frontend/src/views/courses/CoursesView.vue](frontend/src/views/courses/CoursesView.vue#L44-L100)

Die Kurskachel in `CoursesView.vue` zeigt Start-/Enddatum, Teilnehmer, Typ und Stornierungsfrist,
aber keinerlei Terminserie-Information. Es gibt kein `v-if="course.recurrenceRule"` und
keine Darstellung von Wiederholungstyp, Wochentag oder Uhrzeit.

**Abhängigkeit von Bug A:** Solange `CourseResource` kein `recurrenceRule` zurückgibt,
ist `course.recurrenceRule` in der View immer `undefined`. Bug A muss zuerst behoben werden.

---

## Ausgeschlossene Problemstellen (nach Analyse als korrekt befunden)

| Stelle | Status |
|--------|--------|
| `StoreCourseRequest.validatedSnakeCase()` | ✅ korrekt — `recurrence_rule` NICHT in `unset()` (PR #74 Fix) |
| `UpdateCourseRequest.validatedSnakeCase()` | ✅ korrekt — `recurrence_rule` NICHT in `unset()` (PR #74 Fix) |
| `StoreCourseRequest.getRecurrenceRule()` | ✅ korrekt — konvertiert camelCase-Keys zu snake_case |
| `UpdateCourseRequest.getRecurrenceRule()` | ✅ korrekt — identisch |
| `CourseController.store()` | ✅ korrekt — `Course::create($request->validatedSnakeCase())` + Session-Generierung |
| `CourseController.update()` | ✅ korrekt — `$course->update($request->validatedSnakeCase())` + Session-Generierung |
| `Course.$fillable` | ✅ korrekt — `recurrence_rule` ist drin |
| `Course.casts()` | ✅ korrekt — `recurrence_rule` → `'array'` |
| Migration | ✅ korrekt — `$table->json('recurrence_rule')->nullable()` |
| `CourseFormModal` handleSubmit() | ✅ korrekt — sendet `recurrenceRule` wenn `sessionsMode === 'recurrence'` |
| `CourseRecurrenceForm` v-model | ✅ korrekt — emittiert `update:modelValue` mit vollständiger Regel |

---

## Root-Cause-Zusammenfassung

Die Kausalkette ist:

```
API gibt recurrenceRule nicht zurück (Bug A, CourseResource)
  → Frontend kann Bearbeitungsformular nicht vorausfüllen (Bug B, CourseFormModal)
  → User sieht bestehende Terminserie nicht, kann sie nicht bearbeiten
  → Beim Speichern wird kein recurrenceRule mitgeschickt
  → Controller regeneriert keine Sessions
  → Kurskachel zeigt keine Terminserie (Bug C, CoursesView)
```

Bug A ist der primäre Root Cause. Bug B ist ein unabhängiger zweiter Root Cause
(würde selbst bei Fix von A noch bestehen). Bug C ist sekundär zu A.

---

## Empfohlene nächste Aktion

**Kein Architekt nötig.** Alle Fixes sind klar lokalisiert, keine Schnittstellen-Änderungen,
keine neuen Abhängigkeiten, keine DB-Migrationen.

**Direkt zwei dev-Agents beauftragen (parallel):**

### Task für `dev-php`

**Datei:** `backend/app/Http/Resources/CourseResource.php`

Ergänze in `toArray()`:
```php
'recurrenceRule'             => $this->recurrence_rule,
'durationMinutes'            => $this->duration_minutes,
'totalSessions'              => $this->total_sessions,
'pricePerSession'            => $this->price_per_session,  // Korrektur: 'price' → 'pricePerSession'
```

Hinweis: Das Entfernen von `'price'` ist ein Breaking-Change für `CourseApiTest.php`
(Zeile ~32 erwartet `'price'` in der JSON-Struktur). Test muss angepasst werden.

### Task für `dev-typescript`

**Datei 1:** `frontend/src/components/CourseFormModal.vue`

In `watch(() => props.course, ...)` und in `resetForm()` die Initialisierung von
`sessionsMode` und `recurrenceRule` aus den Kursdaten lesen:

```typescript
sessionsMode: newCourse.recurrenceRule
  ? 'recurrence'
  : (newCourse.sessions?.length ? 'manual' : ''),
sessions: [],
recurrenceRule: newCourse.recurrenceRule ?? null
```

**Datei 2:** `frontend/src/views/courses/CoursesView.vue`

In der Kurskachel-Karte eine Terminserie-Anzeige ergänzen, wenn `course.recurrenceRule`
vorhanden:
```html
<div v-if="course.recurrenceRule" class="col-span-2">
  <p class="text-xs text-gray-500 mb-1">Terminserie</p>
  <p class="text-sm font-medium ...">
    {{ formatRecurrenceRule(course.recurrenceRule) }}
  </p>
</div>
```
(Helper-Funktion `formatRecurrenceRule` im `<script setup>` implementieren.)

---

## Reihenfolge

1. `dev-php`-Task zuerst (oder parallel) → `CourseResource` korrigieren
2. `dev-typescript`-Task (kann parallel laufen, sollte aber wissen, dass API
   nach dem PHP-Fix `recurrenceRule` als camelCase-Sub-Keys zurückgibt,
   z. B. `{ type: 'weekly', weekday: 1, startTime: '09:00', … }`)
3. Nach Implementierung: `CourseRequestValidationTest` und `CourseApiTest` prüfen/anpassen


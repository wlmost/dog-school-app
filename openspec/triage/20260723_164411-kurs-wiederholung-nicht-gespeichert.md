# Triage: Kurs-Wiederholungsregel nicht gespeichert

**Pfad:** klein
**Geschätzter Umfang:** 3 Dateien, PHP
**Risiko:** niedrig — bestehende DB-Spalte wird nur nicht beschrieben; kein Schema-Change, keine API-Änderung nötig
**Klarheit:** klar — Root Cause exakt lokalisiert (unset-Zeile in zwei FormRequests)

---

## Anforderung (Zusammenfassung)

Wenn ein Admin/Trainer einen Kurs mit Wiederholungsregel (wöchentlich, bis zu einem bestimmten Datum) anlegt,
werden die Wiederholungsdaten (`recurrence_rule`) nicht in der Datenbank gespeichert.
Die Kurs-Sessions werden korrekt generiert, aber die Regel selbst geht verloren, sodass sie
weder angezeigt noch für spätere Re-Generierungen genutzt werden kann.

---

## Root-Cause-Analyse

### Smoking Gun

**`backend/app/Http/Requests/StoreCourseRequest.php`**, Zeile ~90, Methode `validatedSnakeCase()`:

```php
unset($snakeCase['sessions'], $snakeCase['recurrence_rule'], $snakeCase['sessions_mode']);
```

`recurrence_rule` wird hier explizit aus den Daten entfernt, bevor `Course::create()` aufgerufen wird.
Dasselbe Problem in **`UpdateCourseRequest.php`**, Zeile 95.

### Warum es ein Bug ist

- Die DB-Spalte `recurrence_rule` existiert (Migration `2026_05_14_000001_add_recurrence_rule_to_courses_table.php`)
- Das Model `Course.php` hat `recurrence_rule` in `$fillable` (Zeile ~52) und in `casts()` (Zeile ~68, Typ `array`)
- Der `CourseController::store()` extrahiert die Regel via `$request->getRecurrenceRule()` und generiert Sessions daraus — aber speichert die Regel selbst nie zurück ins Model
- Ergebnis: Sessions werden erstellt, die Regel ist nach `Course::create()` immer `null`

### Bestätigender Test (dokumentiert den Fehler)

**`backend/tests/Feature/CourseRequestValidationTest.php`**, Zeilen 72–81:

```php
it('speichert keinen recurrence_rule-wert in der datenbank wenn validatedSnakeCase ihn ausschließt', function () {
    ...
    $course = Course::latest('id')->first();
    expect($course->recurrence_rule)->toBeNull();  // ← dokumentiert den Bug als "richtig"
});
```

Dieser Test muss umgekehrt werden: er soll nach dem Fix prüfen, dass `recurrence_rule` **nicht null** ist.

### Vollständiger Datenpfad

```
Frontend (CourseFormModal.vue:~350)
  → payload.recurrenceRule = form.value.recurrenceRule   (camelCase-Keys)
  → POST /api/v1/courses

Backend StoreCourseRequest::validatedSnakeCase() (Zeile ~87-90)
  → Str::snake('recurrenceRule') = 'recurrence_rule'   ✓ korrekte Konvertierung
  → unset($snakeCase['recurrence_rule'])               ← BUG: entfernt das Feld

CourseController::store() (Zeile ~104)
  → Course::create($request->validatedSnakeCase())     ← recurrence_rule fehlt

CourseController::store() (Zeile ~108-132)
  → $recurrenceRule = $request->getRecurrenceRule()    ← snake_case-Keys
  → $this->camelizeRuleKeys($recurrenceRule)           ← zurück zu camelCase (korrekt für Service)
  → sessionService->generateFromRecurrence(...)        ← Sessions werden erstellt ✓
  → recurrence_rule in DB: NULL                        ← BUG: nie geschrieben
```

### Warum der camelCase-Roundtrip kein eigener Bug ist

`getRecurrenceRule()` konvertiert zu snake_case; `camelizeRuleKeys()` konvertiert zurück zu camelCase,
weil `CourseSessionService::generateFromRecurrence()` camelCase-Keys erwartet (docblock Zeile ~43).
Funktionell korrekt, aber redundant. Kein Fix nötig.

---

## Fix-Plan (3 Dateien)

### 1. `backend/app/Http/Requests/StoreCourseRequest.php` — Zeile ~90

```php
// Vorher:
unset($snakeCase['sessions'], $snakeCase['recurrence_rule'], $snakeCase['sessions_mode']);

// Nachher:
unset($snakeCase['sessions'], $snakeCase['sessions_mode']);
```

→ `recurrence_rule` wird mit den camelCase-Sub-Keys (wie vom Frontend gesendet) gespeichert.
   Das ist konsistent mit dem TypeScript-Interface `RecurrenceRule`.

### 2. `backend/app/Http/Requests/UpdateCourseRequest.php` — Zeile 95

Gleiche Änderung wie oben.

### 3. `backend/tests/Feature/CourseRequestValidationTest.php` — Zeilen 72–81

Test umbenennen und Assertion invertieren:

```php
it('speichert recurrence_rule in der datenbank wenn sessionsMode recurrence ist', function () {
    ...
    $course = Course::latest('id')->first();
    expect($course->recurrence_rule)->not->toBeNull();
    expect($course->recurrence_rule['type'])->toBe('weekly');
});
```

---

## Betroffene Dateipfade

| Datei | Zeile | Problem |
|-------|-------|---------|
| `backend/app/Http/Requests/StoreCourseRequest.php` | ~90 | `unset($snakeCase['recurrence_rule'])` entfernen |
| `backend/app/Http/Requests/UpdateCourseRequest.php` | 95 | `unset($snakeCase['recurrence_rule'])` entfernen |
| `backend/tests/Feature/CourseRequestValidationTest.php` | 72–81 | Test invertieren |

---

## Hinweis: Mögliche Folgearbeit (kein Scope dieser Triage)

Die `RecurrenceRule` kennt nur `count` (Anzahl der Einheiten), kein `repeatUntil`-Datum.
Der User-Text "bis zu einem bestimmten Datum" könnte auf eine zukünftige UX-Anforderung
hindeuten (Enddate statt Count eingeben). Das ist ein separates Feature, kein Teil dieses Bug-Fixes.

---

## Empfohlene nächste Aktion

**Direkt `dev-php` beauftragen** — kein Architekt nötig.

Auftrag: Fix in `StoreCourseRequest`, `UpdateCourseRequest` und
`CourseRequestValidationTest` gemäß Fix-Plan oben. Kein Schema-Change, keine API-Änderung,
keine Frontend-Änderung nötig.

Nach der Implementierung: `composer qa` (lint + stan + compat-check + pest) im Docker-Container.

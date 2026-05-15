# Test-Report: T01

**Change-ID:** course-dates
**Task:** T01 — Migration + Course Model
**Tester:** tester-agent
**Datum:** 2026-05-14
**Status:** ✅ ALLE GRÜN

---

## Hinzugefügte / geänderte Tests

- `tests/Unit/Models/CourseRecurrenceRuleTest.php`: 6 neue Tests (neu erstellt)

---

## Akzeptanzkriterien-Abdeckung

| # | Akzeptanzkriterium | Status | Test |
|---|-------------------|--------|------|
| 1 | `Course::create(['recurrence_rule' => [...]])` speichert und liest das Feld korrekt (als Array, nicht als JSON-String) | ✅ | `it speichert recurrence_rule als array und liest es korrekt zurück` |
| 2 | `Course::create([])` (ohne das Feld) funktioniert weiterhin — `recurrence_rule` ist `null` | ✅ | `it setzt recurrence_rule auf null wenn das feld beim erstellen weggelassen wird` |
| 3 | `recurrence_rule` wird korrekt als `array` gecastet (nicht als String zurückgegeben) | ✅ | `it gibt recurrence_rule als array zurück und nicht als json-string` |
| 4 | Das Feld akzeptiert `weekly`-Regelstrukturen | ✅ | `it akzeptiert eine weekly-regelstruktur mit allen feldern` |
| 4 | Das Feld akzeptiert `monthly`-Regelstrukturen | ✅ | `it akzeptiert eine monthly-regelstruktur mit allen feldern` |
| — | Zusatz: `recurrence_rule` kann nach dem Erstellen auf `null` zurückgesetzt werden | ✅ | `it lässt recurrence_rule auf null setzen nach dem erstellen` |
| — | `php artisan migrate` / `migrate:rollback` — nicht automatisiert testbar in Unit-Tests | ℹ️ | Manuell zu prüfen (siehe Hinweis) |
| — | `composer compat-check` — nicht Teil dieser Test-Datei | ℹ️ | Läuft separat als `composer qa` |

---

## Ausführungs-Ergebnis

```
   PASS  Tests\Unit\Models\CourseRecurrenceRuleTest
  ✓ it speichert recurrence_rule als array und liest es korrekt zurück   0.09s
  ✓ it setzt recurrence_rule auf null wenn das feld beim erstellen weggelassen wird
  ✓ it gibt recurrence_rule als array zurück und nicht als json-string   0.01s
  ✓ it akzeptiert eine weekly-regelstruktur mit allen feldern
  ✓ it akzeptiert eine monthly-regelstruktur mit allen feldern
  ✓ it lässt recurrence_rule auf null setzen nach dem erstellen

  Tests:    6 passed (15 assertions)
  Duration: 0.14s
```

---

## Fehler

_Keine._

---

## Hinweise / Abweichungen

### Explizites `uses(Tests\TestCase::class)` in Unit-Datei

Die Datei liegt in `tests/Unit/Models/` (per Auftraggeber-Vorgabe). Tests mit
DB-Zugriff gehören laut `TESTING.md` §7 eigentlich in `tests/Feature/Domain/`
(Gruppe `domain`). Da `Pest.php` den Laravel-Container nur automatisch für
`tests/Feature/` aktiviert, musste die Datei `uses(Tests\TestCase::class,
RefreshDatabase::class)` explizit deklarieren, um den Container und
`RefreshDatabase` zu erhalten. Ohne diese Zeile scheitern alle Tests mit
`RuntimeException: A facade root has not been set.`

**Empfehlung für den Architekten:** Datei bei Gelegenheit nach
`tests/Feature/Domain/Course/CourseRecurrenceRuleTest.php` verschieben und
Gruppe auf `domain`, `course` ändern. Das ist aber kein Blocker — die Tests
laufen korrekt, und die Abweichung ist dokumentiert.

### Nicht automatisch testbar

- **Migration-Ausführung** (`php artisan migrate` / `migrate:rollback`): Wird
  durch `RefreshDatabase` implizit abgedeckt (jeder Test startet mit frischem
  Schema). Ein expliziter Rollback-Test wurde nicht hinzugefügt, da
  `RefreshDatabase` keine Rollback-spezifische Assertion anbietet. Die
  `down()`-Methode ist durch den Reviewer bereits als korrekt bestätigt
  (`task-T01.review.md`).
- **`composer compat-check`**: Läuft als separater CI-Schritt, nicht als
  PHPUnit-Test.

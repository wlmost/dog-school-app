# Test-Report: T03 — Kurse & Buchungen

**Status:** alle-gruen

## Hinzugefügte / geänderte Tests

Keine neuen Testdateien. Begründung: siehe
`task-T02.test-report.md` Abschnitt "Entscheidung gegen
Klassen-Assertion-Tests" — gilt identisch für T03 (ausschließlich
statische `dark:`-Klassenergänzungen, keine neue Verzweigungslogik).

Bestehende, für T03-Dateien relevante Testdateien wurden als
Regressions-Check ausgeführt (siehe Ausführungs-Ergebnis):

- `frontend/src/components/CourseRecurrenceForm.test.ts` (betrifft
  `CourseRecurrenceForm.vue`)
- `frontend/src/views/courses/CoursesView.test.ts` (betrifft
  `CoursesView.vue`)

Für `CourseFormModal.vue`, `CourseSessionList.vue`,
`BookingFormModal.vue`, `BookingsView.vue` existiert **keine** eigene
Vitest-Datei (weder vor noch nach diesem Change).

## Akzeptanzkriterien-Abdeckung

- [ ] `CourseFormModal.vue` inkl. eingebettetem
      `CourseRecurrenceForm.vue` im Dark-Mode vollständig lesbar —
      **nicht automatisiert testbar**, siehe "Offene Punkte" unten.
- [ ] `CourseSessionList.vue` in `CourseDetailView.vue` im Dark-Mode
      lesbar — **nicht automatisiert testbar**.
- [ ] `CoursesView.vue` im Dark-Mode lesbar — **nicht automatisiert
      testbar**. `CoursesView.test.ts` prüft ausschließlich
      Rollen-/Auth-abhängige Sichtbarkeit von Buttons/Badges
      (`wrapper.text()`-Assertions), keine Klassen — bestätigt keine
      Regression durch die Klassen-Ergänzung, deckt aber das
      Dark-Mode-Kriterium selbst nicht ab.
- [ ] `BookingFormModal.vue` im Dark-Mode vollständig lesbar — **nicht
      automatisiert testbar**.
- [ ] `BookingsView.vue`: alle Textfarben mit `dark:`-Pendant, keine
      dunkle Schrift auf `dark:bg-gray-800`-Hintergrund mehr — **nicht
      automatisiert testbar**.
- [x] Gemeinsame Akzeptanzkriterien: `npm run test` bleibt grün, `npm
      run build` läuft ohne neue Fehler/Warnungen.

## Ausführungs-Ergebnis

```
$ npx vitest run
 Test Files  18 passed (18)
      Tests  194 passed (194)
```
`CourseRecurrenceForm.test.ts` und `courses/CoursesView.test.ts` sind
Teil dieses grünen Laufs und wurden durch die T03-Klassenänderungen
nicht beeinträchtigt (keine Klassen-Assertions in beiden Dateien,
verifiziert per Durchsicht).

```
$ npm run build
> vue-tsc -b && vite build
✓ 643 modules transformed.
✓ built in 1.30s
```
Keine TypeScript-Fehler, keine Build-Warnungen für die sechs T03-
Dateien.

## Fehler

Keine.

## Offener Punkt (nicht selbst geprüft/erfunden)

Alle in `tasks.md` T03 gelisteten "manuell im Dark-Mode geprüft"-
Akzeptanzkriterien (inkl. des explizit genannten verschachtelten
Formulars `CourseFormModal.vue` + `CourseRecurrenceForm.vue`) wurden
**nicht** durchgeführt — kein Browser-Tool in dieser Session verfügbar.
`task-T03.notes.md` empfiehlt bereits selbst: "finale visuelle
Browser-Prüfung mit Dark-Mode-Toggle obliegt `reviewer`/`tester` in
Schritt 9 des Workflows" — dieser Teil bleibt offen für einen Menschen
oder ein Browser-Automatisierungs-Tool.

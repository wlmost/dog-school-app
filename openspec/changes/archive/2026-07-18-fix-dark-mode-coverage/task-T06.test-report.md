# Test-Report: T06 — Einstellungen, Mail-Vorschau, rechtliche Seiten

**Status:** alle-gruen

## Hinzugefügte / geänderte Tests

Keine neuen Testdateien. Begründung: siehe
`task-T02.test-report.md` Abschnitt "Entscheidung gegen
Klassen-Assertion-Tests" — gilt identisch für T06.

Bestehende, für T06 relevante Testdatei wurde als Regressions-Check
ausgeführt:

- `frontend/src/views/SettingsView.test.ts` (betrifft
  `SettingsView.vue`, die Datei mit der größten Anzahl an
  Änderungsstellen in T06, siehe `task-T06.notes.md`)

Für `EmailPreviewModal.vue`, `AgbView.vue`, `DatenschutzView.vue`
existiert **keine** eigene Vitest-Datei.

## Besonderheit T06: zwei der vier Dateien benötigten keine Code-Änderung

Laut `task-T06.notes.md` stellte sich bei genauerer Prüfung heraus,
dass `AgbView.vue` und `DatenschutzView.vue` (anders als von der
`design.md`-Heuristik mit 23/11 bzw. 29/12 `text-*`/`dark:text-*`-
Vorkommen vermutet) bereits **vollständig** `dark:`-abgedeckt waren —
die Zählmethode hatte den Substring `text-gray-300` innerhalb von
`dark:text-gray-300` fälschlich doppelt gezählt. Für diese beiden
Dateien gibt es daher **keinen Diff** in diesem Change (verifiziert:
beide Dateien fehlen im `git status`-Output zum Zeitpunkt dieses
Reports, siehe eingangs eingeholter `git status`). Das ist keine
Lücke im Test-Report, sondern korrekt dokumentiertes Ergebnis der
Entwickler-Verifikation.

## Akzeptanzkriterien-Abdeckung

- [ ] `SettingsView.vue`: alle Formular-Sektionen im Dark-Mode
      vollständig lesbar — **nicht automatisiert testbar**, siehe
      "Offene Punkte" unten. `SettingsView.test.ts` deckt
      funktionale Aspekte ab (Speichern, Validierung, Upload-Verhalten
      laut Dateiname), keine Klassen-Assertions — regressionsfrei
      bestätigt für die T06-Änderungen, deckt das Dark-Mode-Kriterium
      selbst aber nicht ab.
- [ ] `EmailPreviewModal.vue`: Vorschau-Inhalt (inkl. eingebettetem
      `dark:prose-invert`) im Dark-Mode vollständig lesbar — **nicht
      automatisiert testbar**.
- [x] `AgbView.vue` und `DatenschutzView.vue`: vollständig lesbar im
      Dark-Mode — laut `task-T06.notes.md` bereits vor diesem Change
      vollständig abgedeckt (keine Code-Änderung nötig, per Grep
      verifiziert). Auch dieser Befund ist eine statische
      Code-Verifikation, keine visuelle Bestätigung — siehe "Offene
      Punkte".
- [x] Gemeinsame Akzeptanzkriterien: `npm run test` bleibt grün, `npm
      run build` läuft ohne neue Fehler/Warnungen.

## Ausführungs-Ergebnis

```
$ npx vitest run
 Test Files  18 passed (18)
      Tests  194 passed (194)
```
`SettingsView.test.ts` ist Teil dieses grünen Laufs; keine
Klassen-Assertions in der Datei, daher keine Regression durch die
T06-Änderungen möglich/beobachtet.

```
$ npm run build
> vue-tsc -b && vite build
✓ 643 modules transformed.
✓ built in 1.30s
```
Keine TypeScript-Fehler, keine Build-Warnungen für
`SettingsView.vue`/`EmailPreviewModal.vue`.

## Fehler

Keine.

## Offener Punkt (nicht selbst geprüft/erfunden)

Die "im Dark-Mode vollständig lesbar"-Akzeptanzkriterien für
`SettingsView.vue` und `EmailPreviewModal.vue` (inkl. der expliziten
Prüfung von `dark:prose-invert` für die Rich-Text-Vorschau) sowie die
abschließende visuelle Bestätigung für `AgbView.vue`/
`DatenschutzView.vue` verlangen laut `tasks.md` eine manuelle/visuelle
Browser-Prüfung. Diese wurde in dieser Session **nicht** durchgeführt —
kein Browser-Tool verfügbar. Verbleibt als offener Punkt für einen
Menschen oder ein Browser-Automatisierungs-Tool.

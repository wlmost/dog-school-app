# Test-Report: T04 — Trainer & Rechnungen

**Status:** alle-gruen

## Hinzugefügte / geänderte Tests

Keine neuen Testdateien. Begründung: siehe
`task-T02.test-report.md` Abschnitt "Entscheidung gegen
Klassen-Assertion-Tests" — gilt identisch für T04.

Für keine der fünf T04-Dateien (`TrainerFormModal.vue`,
`InvoiceDetailModal.vue`, `InvoiceFormModal.vue`,
`TrainersView.vue`, `InvoicesView.vue`) existiert eine eigene
Vitest-Datei (weder vor noch nach diesem Change) — die gesamte
Regressionsabsicherung für T04 läuft daher ausschließlich über
`npm run build` (Typprüfung/Kompilierbarkeit) und die unveränderte
Gesamtsuite (kein Test importiert eine der fünf Dateien transitiv in
einer Weise, die brechen könnte).

## Akzeptanzkriterien-Abdeckung

- [ ] `TrainerFormModal.vue` im Dark-Mode vollständig lesbar — **nicht
      automatisiert testbar**, siehe "Offene Punkte" unten.
- [ ] `TrainersView.vue`: alle Tabellen-/Card-Texte mit `dark:`-Pendant
      — **nicht automatisiert testbar**.
- [ ] `InvoiceDetailModal.vue` und `InvoiceFormModal.vue` im Dark-Mode
      vollständig lesbar — **nicht automatisiert testbar**.
- [ ] `InvoicesView.vue`: alle Tabellen-/Card-Texte mit `dark:`-Pendant
      (inkl. Status-/Betrags-Anzeigen) — **nicht automatisiert
      testbar**.
- [x] Gemeinsame Akzeptanzkriterien: `npm run test` bleibt grün, `npm
      run build` läuft ohne neue Fehler/Warnungen.

## Ausführungs-Ergebnis

```
$ npx vitest run
 Test Files  18 passed (18)
      Tests  194 passed (194)
```

```
$ npm run build
> vue-tsc -b && vite build
✓ 643 modules transformed.
✓ built in 1.30s
```
Keine TypeScript-Fehler, keine Build-Warnungen für die fünf T04-
Dateien (inkl. der beiden Status-Mapping-Funktionen `getStatusClass()`
in `InvoiceDetailModal.vue` und `invoiceStatusClass()` in
`InvoicesView.vue`, deren erweiterte Klassenstrings weiterhin
typkorrekt sind).

## Fehler

Keine.

## Offener Punkt (nicht selbst geprüft/erfunden)

Alle "im Dark-Mode vollständig lesbar"-Akzeptanzkriterien in T04
verlangen laut `tasks.md` eine manuelle/visuelle Prüfung. `task-
T04.notes.md` weist selbst explizit darauf hin: "Eine tatsächliche
visuelle Prüfung im Browser (Dark-Mode-Toggle, wie in den
Akzeptanzkriterien beschrieben) sollte laut Workflow im `tester`-Schritt
(Schritt 9) erfolgen." Diese Prüfung wurde in dieser Session **nicht**
durchgeführt — kein Browser-Tool verfügbar. Verbleibt als offener
Punkt für einen Menschen oder ein Browser-Automatisierungs-Tool.

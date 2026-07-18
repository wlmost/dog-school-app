# Test-Report: T01 — Layout & geteilte Komponenten

**Status:** alle-gruen

## Hinzugefügte / geänderte Tests

- `frontend/src/layouts/DefaultLayout.test.ts` (neu, 3 Cases). Es gab
  zuvor keine Testdatei für `DefaultLayout.vue`.

Für `SearchInput.vue`, `HtmlEditor.vue` und `NotFoundView.vue` wurden
**keine** neuen Tests ergänzt (Begründung siehe Abschnitt "Entscheidung
gegen Klassen-Assertion-Tests" unten). Es existieren für diese drei
Dateien auch vor diesem Change keine eigenen Vitest-Dateien.

## Warum genau `DefaultLayout.vue` einen neuen Test bekommt, die anderen drei Dateien in T01 nicht

`DefaultLayout.vue` ist der einzige Fall in diesem gesamten Change, bei
dem die Änderung über eine reine `class`-Attribut-Ergänzung
hinausgeht: die `computed`-Property `backgroundStyle`
(`DefaultLayout.vue:169-180`) wurde um eine Fallunterscheidung auf
`themeStore.isDark` erweitert (siehe `task-T01.notes.md` Abschnitt 1).
Das ist eine echte, testbare Verhaltensänderung (welcher von zwei
Werten wird abhängig vom Store-Zustand zurückgegeben), kein reiner
Klassenstring in statischem Markup — dafür ist ein Unit-Test
gerechtfertigt und wertvoll (deckt den eigentlichen, in `proposal.md`
beschriebenen Bug ab: "Tailwind-`dark:`-Klassen können eine per
`:style` gesetzte Inline-Regel nicht überschreiben").

`SearchInput.vue`, `HtmlEditor.vue` und `NotFoundView.vue` erhielten in
T01 ausschließlich zusätzliche `dark:`-Klassen auf statischem Markup —
kein neuer State, keine neue Verzweigung. Für diese drei gilt dieselbe
Begründung wie in Abschnitt "Entscheidung gegen Klassen-Assertion-Tests
für T02–T06" unten.

## Wichtiger technischer Befund während der Testerstellung (Environment-Limitation)

Beim Versuch, `backgroundStyle` über das gerenderte DOM-`style`-Attribut
zu prüfen (`wrapper.get('div.min-h-screen').attributes('style')`),
zeigte ein Spike-Test, dass **happy-dom** (die in
`frontend/vitest.config.ts` konfigurierte Test-Umgebung) das
`background`-Shorthand beim Serialisieren normalisiert und dabei den
`linear-gradient(...)`-Layer verliert:

- Eingabe: `background: 'linear-gradient(rgba(17, 24, 39, 0.75), rgba(17, 24, 39, 0.85)), url(foo.jpg)'`
- Ausgabe von `attributes('style')`: `background: url("foo.jpg") center center / cover fixed;`
  (die `rgba(...)`-Werte fehlen vollständig)

Ein Test, der auf das DOM-`style`-Attribut geprüft hätte, wäre daher
**unabhängig vom Zustand des Produktivcodes immer fehlgeschlagen** —
eine Limitation der Test-Umgebung, kein Hinweis auf einen Bug. Die
Tests prüfen deshalb stattdessen direkt gegen
`(wrapper.vm as any).backgroundStyle.background` (funktioniert auch bei
`<script setup>`-Komponenten im Dev-Build, verifiziert per Spike-Test)
— das testet exakt dieselbe Business-Logik ohne die
DOM-Serialisierungs-Einschränkung.

## Akzeptanzkriterien-Abdeckung

- [x] `DefaultLayout.vue`: Seitenhintergrund ist im Dark-Mode sichtbar
      dunkel getönt, im Light-Mode exakt wie vorher — getestet in
      `DefaultLayout.test.ts::setzt im Light-Mode das ursprüngliche
      helle Overlay` und `::setzt im Dark-Mode ein dunkles Overlay
      statt des hellen Overlays`. Zusätzlich
      `::behält das Hintergrundbild in beiden Modi bei` deckt die
      Non-Regression-Anforderung aus `design.md` D2 ab (Hintergrundbild
      bleibt erhalten, nur das Overlay ändert sich).
- [ ] `SearchInput.vue`: keine Textfarbe ohne `dark:`-Pendant mehr
      (manuell im Browser mit aktivem Dark-Mode auf mind. einer der
      vier Verwendungsstellen geprüft) — **nicht automatisiert
      testbar/getestet.** Dies ist explizit eine manuelle
      Browser-Sichtprüfung laut Akzeptanzkriterium in `tasks.md`. Kein
      Browser-Tool in dieser Session verfügbar (siehe "Offene Punkte"
      unten). Statische Code-Verifikation (Grep auf vollständige
      `dark:`-Paarung) wurde bereits vom Entwickler in
      `task-T01.notes.md` dokumentiert.
- [ ] `HtmlEditor.vue`: im Dark-Mode lesbar sowohl in
      `CourseFormModal.vue` als auch in `AnnouncementsView.vue` —
      **nicht automatisiert testbar/getestet**, gleiche Begründung wie
      oben (manuelle Browser-Prüfung).
- [ ] `NotFoundView.vue`: im Dark-Mode lesbar dargestellt — **nicht
      automatisiert testbar/getestet**, gleiche Begründung wie oben.
- [x] Gemeinsame Akzeptanzkriterien: `npm run test` bleibt grün (siehe
      Ausführungs-Ergebnis) und `npm run build` läuft ohne neue
      Fehler/Warnungen (siehe Ausführungs-Ergebnis).

## Ausführungs-Ergebnis

```
$ npx vitest run
 Test Files  18 passed (18)
      Tests  194 passed (194)
```
(17 bestehende Testdateien/191 Tests + 1 neue Datei/3 neue Tests aus
dieser Session = 18/194; die neue Datei betrifft ausschließlich T01.)

```
$ npm run build
> vue-tsc -b && vite build
✓ 643 modules transformed.
✓ built in 1.30s
```
Keine TypeScript-Fehler, keine Build-Warnungen. `dist/` ist über
`frontend/.gitignore` ausgeschlossen, kein Commit nötig (reiner
Lauffähigkeits-Check gemäß `CLAUDE.md` Abschnitt 7.1).

## Fehler

Keine.

## Offener Punkt (nicht selbst geprüft/erfunden)

Die Akzeptanzkriterien für `SearchInput.vue`, `HtmlEditor.vue` und
`NotFoundView.vue` verlangen explizit eine **manuelle** Prüfung "im
Browser mit aktivem Dark-Mode" an den in `tasks.md` genannten
Verwendungsstellen (`DogsView.vue`, `CustomersView.vue`,
`TrainersView.vue`, `CoursesView.vue` für `SearchInput.vue`;
`CourseFormModal.vue`/`AnnouncementsView.vue` für `HtmlEditor.vue`).
Als Tester-Agent stand mir kein Browser-Tool zur Verfügung — diese
Prüfung wurde **nicht durchgeführt** und sollte von einem Menschen oder
mit einem Browser-Automatisierungs-Tool (z. B. Playwright, das laut
`proposal.md` bereits im Projekt vorhanden ist, aber aktuell keine
Theme-spezifischen Tests hat) nachgeholt werden, bevor die Task
endgültig als erledigt gilt.

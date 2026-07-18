# Test-Report: T02 — Hunde & Kunden

**Status:** alle-gruen

## Hinzugefügte / geänderte Tests

Keine neuen Testdateien. Begründung siehe "Entscheidung gegen
Klassen-Assertion-Tests" unten (gilt projektweit für T02–T06, dort
zentral dokumentiert und hier nicht wiederholt in voller Länge).

Bestehende, für T02-Dateien relevante Testdateien wurden als
Regressions-Check ausgeführt (siehe Ausführungs-Ergebnis):

- `frontend/src/components/DogFormModal.test.ts` (13 Tests, betrifft
  `DogFormModal.vue`)
- `frontend/src/components/CustomerBookingModal.test.ts` (betrifft
  `CustomerBookingModal.vue`)

Für `CustomerFormModal.vue`, `CustomerDetailModal.vue`,
`CustomerDogRequestModal.vue`, `CustomersView.vue`, `DogsView.vue`
existiert **keine** eigene Vitest-Datei (weder vor noch nach diesem
Change).

## Entscheidung gegen Klassen-Assertion-Tests (T02–T06, zentral begründet)

Der Architekt/Skeptiker hat in `proposal.md`
("Out of Scope") und `design.md` ("Non-Goals") bewusst keine
automatisierten Visual-Regression-Tests für diesen Change vorgesehen
und die endgültige Entscheidung über zusätzliche automatisierte
Prüfungen explizit dem Tester-Agenten überlassen. Für T02–T06 (im
Gegensatz zu T01, siehe `task-T01.notes.md`/`task-T01.test-report.md`)
sind **alle** Änderungen reine Ergänzungen von Tailwind-`dark:`-Klassen
auf statischem Markup — keine neue Verzweigungslogik, kein neuer
State, keine bedingten Klassen außer den bereits vor diesem Change
vorhandenen Status-Badge-Funktionen (deren Rückgabewerte selbst nur
Klassenstrings sind, siehe `task-T02.notes.md` Abschnitt
`CustomerDetailModal.vue`).

Gegen das Ergänzen von Vitest-Tests, die exakte Tailwind-Klassenstrings
per `wrapper.classes()`/`wrapper.html()`-Substring-Match prüfen, spricht:

1. **Kein echter Nutzen-Nachweis:** Vitest (mit happy-dom, siehe
   `frontend/vitest.config.ts`) führt kein CSS aus und rendert keine
   tatsächlichen Farben — ein Test kann nur belegen, dass ein
   bestimmter String im `class`-Attribut steht, nicht, dass der
   Kontrast im Dark-Mode tatsächlich ausreichend ist (das eigentliche
   Akzeptanzkriterium). Das ist exakt der Grund, warum die
   Akzeptanzkriterien in `tasks.md` durchgängig "manuell im Browser
   geprüft" verlangen, nicht "per Test verifiziert".
2. **Hohe Fragilität, geringer Schutzwert:** Ein Test, der z. B.
   `expect(wrapper.find('.dialog-panel').classes()).toContain('dark:bg-gray-800')`
   prüft, bricht bei jeder zukünftigen, funktional gleichwertigen
   Farbanpassung (z. B. Wechsel auf `dark:bg-slate-800` im Rahmen einer
   Design-System-Überarbeitung), ohne dass ein echter Regressionsfehler
   vorliegt — Wartungslast ohne Sicherheitsgewinn.
3. **Kein bestehendes Projektmuster:** Keine der 17 vor diesem Change
   existierenden Vitest-Suiten prüft CSS-Klassenstrings (verifiziert
   bereits in `verification.md` des Changes und in allen sechs
   `task-T0X.notes.md`-Dateien der Entwickler-Agenten). Neue Tests, die
   ein bisher unbenutztes Assertion-Muster einführen, sollten laut
   `TESTING.md` Prinzip "bestehende Patterns übernehmen" nicht ohne
   triftigen Grund eingeführt werden.
4. **Sinnvolle Alternative bereits vorhanden:** Die statische
   Grep-Verifikation ("kein `text-gray-*`/`bg-white` ohne `dark:`-
   Pendant") wurde von den Entwickler-Agenten bereits pro Datei
   dokumentiert (siehe `task-T02.notes.md`) — das ist der angemessene,
   nicht-fragile Prüfmechanismus für reine Klassen-Vollständigkeit,
   kein Vitest-Test-Ersatz nötig.

Ausnahme (siehe `task-T01.test-report.md`): Wo eine Änderung tatsächlich
Verhaltenslogik betrifft (Computed-Property mit Store-Abhängigkeit),
wurde sehr wohl ein Test ergänzt.

## Akzeptanzkriterien-Abdeckung

- [ ] Alle fünf Modals öffnen sich im Dark-Mode mit dunklem Panel-
      Hintergrund und durchgängig lesbarem Text (manuell geprüft) —
      **nicht automatisiert testbar**, siehe "Offene Punkte" unten.
      Statische Code-Verifikation (28/38/27/25/18 `dark:`-Vorkommen je
      Datei) bereits vom Entwickler in `task-T02.notes.md` dokumentiert.
- [ ] `CustomersView.vue`: Tabellenzeilen im Dark-Mode lesbar — **nicht
      automatisiert testbar**, gleiche Begründung.
- [ ] `DogsView.vue`: alle Textfarben mit `dark:`-Pendant — **nicht
      automatisiert testbar**, gleiche Begründung.
- [x] Gemeinsame Akzeptanzkriterien: `npm run test` bleibt grün, `npm
      run build` läuft ohne neue Fehler/Warnungen (siehe
      Ausführungs-Ergebnis; Regressionscheck für `DogFormModal.vue` und
      `CustomerBookingModal.vue` über die bestehenden Testdateien
      explizit bestätigt — keine Verhaltensänderung durch die
      Klassen-Ergänzungen).

## Ausführungs-Ergebnis

```
$ npx vitest run
 Test Files  18 passed (18)
      Tests  194 passed (194)
```
(Gesamte Suite inkl. der für T02 relevanten `DogFormModal.test.ts` und
`CustomerBookingModal.test.ts` — beide grün, keine Anpassung nötig, da
keine der beiden Dateien Klassen-Assertions enthält.)

```
$ npm run build
> vue-tsc -b && vite build
✓ 643 modules transformed.
✓ built in 1.30s
```
Keine TypeScript-Fehler, keine Build-Warnungen für die sieben T02-
Dateien.

## Fehler

Keine.

## Offener Punkt (nicht selbst geprüft/erfunden)

Das Akzeptanzkriterium "manuell geprüft: Modal öffnen, Dark-Mode-
Toggle im Header betätigen" für alle fünf Modals sowie die
Tabellen-/Karten-Lesbarkeit in `CustomersView.vue`/`DogsView.vue`
wurden **nicht** durchgeführt — kein Browser-Tool in dieser Session
verfügbar. Verbleibt als offener Punkt für einen Menschen oder ein
Browser-Automatisierungs-Tool, wie in `task-T02.notes.md` selbst bereits
empfohlen ("interaktive Browser-Prüfung obliegt Reviewer/Tester").

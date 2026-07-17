# CI-Fix: DOMPurify/happy-dom-Isolationsproblem in `AnnouncementBanner.test.ts`

**Kontext:** Direkter Fix auf `feature/add-announcement-banner` (bereits
gemergter Stand von PR #70), kein neuer openspec-Change. Auftrag und
Root-Cause-Hypothese siehe
`openspec/triage/20260717173845-fix-dompurify-vitest-isolation-ci-flake.md`.

## Root Cause (verifiziert)

- `frontend/node_modules/dompurify/dist/purify.cjs.js:1535`:
  `var purify = createDOMPurify();` — der Default-Export von `dompurify`
  ist ein **Modul-Singleton**, das genau einmal beim ersten Laden des
  Moduls erzeugt wird.
- `frontend/node_modules/dompurify/dist/purify.cjs.js:396-398`:
  `function createDOMPurify(root = window) { ... const DOMPurify = root =>
  createDOMPurify(root); ... }` — `root` (Default: das zum Ladezeitpunkt
  globale `window`) wird beim Erzeugen des Singletons fest gebunden. Das
  zurückgegebene `DOMPurify`-Objekt ist selbst aufrufbar
  (`(root?: WindowLike) => DOMPurify`, siehe
  `frontend/node_modules/dompurify/dist/purify.es.d.mts:217-224`) und kann
  so erneut mit einem anderen `window` instanziiert werden — genau das
  macht der Fix.
- `frontend/vitest.config.ts:8`: `environment: 'happy-dom'`. Vitest erzeugt
  pro Testdatei ein **neues** `happy-dom`-`window`-Objekt, das
  `dompurify`-Modul selbst wird aber pro Worker-Prozess nur **einmal**
  importiert (Node-/ESM-Modul-Cache). Teilen sich mehrere Testdateien einen
  Worker (abhängig von CPU-Kernzahl/Scheduling des jeweiligen Runners),
  bleibt der `dompurify`-Default-Export an das `window` der zuerst
  geladenen Testdatei gebunden — in der beobachteten CI-Fehlschlag-Reihenfolge
  war das `src/views/courses/CoursesView.test.ts` (nutzt dasselbe
  Sanitizing-Pattern), während `AnnouncementBanner.test.ts` mit dem
  bereits "verbrauchten"/fremden `window` sanitized hat, wodurch DOMPurifys
  interne Node-Type-Checks (`instanceof` etc. gegen das *aktuelle*
  Test-`window`) nicht mehr griffen und weder `<script>` noch nicht
  erlaubte `<img>`-Tags/Attribute entfernt wurden.
- Lokal (macOS) reproduzierte sich das nicht zuverlässig, da Worker-Anzahl/
  Datei-Verteilung von der auf `ubuntu-latest`/Node 20 abweicht — reines
  Testumgebungs-/Nichtdeterminismus-Problem, kein Produktivcode-Bug (im
  echten Browser existiert immer nur ein `window`).

## Fix

`frontend/src/components/AnnouncementBanner.vue`:

- Import geändert von `import DOMPurify from 'dompurify'` zu
  `import createDOMPurify from 'dompurify'` (reine lokale Umbenennung des
  Default-Imports — es gibt in `dompurify@3.4.3` **keinen** separaten
  Named Export `createDOMPurify`; der Default-Export selbst ist die
  Factory-Funktion, siehe `purify.es.d.mts:217-224`,
  `interface DOMPurify { (root?: WindowLike): DOMPurify; ... }`).
- In `sanitizeHtml()` wird pro Aufruf `const purify = createDOMPurify(window)`
  gebildet und `purify.sanitize(...)` statt des globalen Singletons
  verwendet. Damit ist die Instanz immer explizit an das zum Aufrufzeitpunkt
  aktuelle `window` gebunden — unabhängig davon, welches `window` beim
  ersten Modul-Import zufällig global war.
- Dies ist das von DOMPurify offiziell dokumentierte Muster für
  nicht-Standard-Umgebungen/Tests (JSDoc-Kommentar der Factory-Funktion:
  "Creates a DOMPurify instance using the given window-like object.
  Defaults to `window`.").
- Keine Änderung an Test-Erwartungen in `AnnouncementBanner.test.ts`
  nötig — die Assertions bleiben unverändert korrekt.

## Bewusst NICHT angefasst (außerhalb Scope)

`frontend/src/views/courses/CoursesView.vue:153,320,324` nutzt exakt
dasselbe anfällige Muster (`import DOMPurify from 'dompurify'` +
`DOMPurify.sanitize(...)` über den Modul-Singleton). Aktuell traten dort
keine CI-Fehlschläge auf (vermutlich reine Lade-/Worker-Reihenfolge-Glück),
das Risiko besteht aber grundsätzlich identisch. **Empfehlung für einen
Folge-Fix:** denselben `createDOMPurify(window)`-Pattern dort übernehmen,
idealerweise in einem gemeinsamen Composable/Util (z. B.
`frontend/src/composables/useSanitizedHtml.ts`), um Duplikation zwischen
`AnnouncementBanner.vue` und `CoursesView.vue` zu vermeiden — das war aber
laut Auftrag explizit außerhalb des Scopes dieses Fixes.

`frontend/vitest.config.ts` wurde ebenfalls nicht angefasst (siehe
Scope-Vorgabe) — eine projektweite Isolationsänderung
(`pool`/`isolate`-Optionen) würde das Problem strukturell für alle
Komponenten mit modul-globalem State lösen, hat aber einen größeren
Blast-Radius (CI-Laufzeit-Impact) und war explizit nicht Teil dieses Fixes.

## Verifikation

- `npx vue-tsc -b` (via `npm run build`): keine TypeScript-Fehler. Der
  umbenannte Default-Import (`createDOMPurify`) und der Aufruf
  `createDOMPurify(window)` sind typkorrekt gegen
  `frontend/node_modules/dompurify/dist/purify.es.d.mts` (Default-Export
  hat Call-Signature `(root?: WindowLike): DOMPurify`).
- `npm run build`: erfolgreich, `vue-tsc -b && vite build` ohne Fehler/
  Warnings (Output u. a. `dist/assets/purify.es-*.js`, `dist/assets/index-*.js`).
- `npx vitest run` (volle Suite, Standard-Worker-Verteilung): **4x
  hintereinander ausgeführt** (1x initial + 3x explizit zur
  Nichtdeterminismus-Prüfung) — jedes Mal `Test Files 17 passed (17)`,
  `Tests 191 passed (191)`.
- Zusätzlich `npx vitest run --no-file-parallelism` (erzwingt Ausführung
  aller Testdateien in einem einzigen Prozess/Worker, damit garantiert
  derselbe `dompurify`-Modul-Singleton über Dateigrenzen hinweg geteilt
  wird — das Szenario, das den ursprünglichen CI-Fehler auslöste): Die
  Dateireihenfolge in diesem Modus war u. a.
  `src/views/courses/CoursesView.test.ts` **vor**
  `src/components/AnnouncementBanner.test.ts` — exakt die in der Triage
  beschriebene Problem-Reihenfolge. Ergebnis: weiterhin
  `Test Files 17 passed (17)`, `Tests 191 passed (191)`. Das bestätigt,
  dass der Fix das Problem unabhängig von Worker-/Datei-Reihenfolge löst.

## Betroffene Dateien

- `frontend/src/components/AnnouncementBanner.vue` (einzige Code-Änderung)

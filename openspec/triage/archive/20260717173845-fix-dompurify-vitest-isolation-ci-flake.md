# Triage: fix-dompurify-vitest-isolation-ci-flake

**Pfad:** klein
**Geschätzter Umfang:** 1–4 Dateien (`frontend/vitest.config.ts` und/oder `frontend/src/components/AnnouncementBanner.vue`, `frontend/src/views/courses/CoursesView.vue` + je zugehörige `*.test.ts`), reines TypeScript/Vue-Frontend.
**Risiko:** niedrig — reines Test-/Tooling-Problem ohne bestätigten Produktivcode-Fehler; leicht erhöht auf mittel, falls der gewählte Fix `DOMPurify`-Instanziierung in Komponenten ändert (Berührt zwei Komponenten mit identischem Sanitizing-Muster, aber kein Schnittstellenbruch, keine Migration, kein Auth-Bezug).
**Klarheit:** mehrdeutig — Root Cause ist gut gestützt, aber die konkrete Fix-Strategie (Vitest-Konfiguration vs. explizite DOMPurify-Instanziierung pro Komponente) ist noch offen und hat unterschiedliche Blast-Radien.

## Anforderung (Zusammenfassung)

In PR #70 (`feature/add-announcement-banner`) schlagen im GitHub-Actions-CI-Lauf
2 von 191 Vitest-Tests in `frontend/src/components/AnnouncementBanner.test.ts`
fehl (DOMPurify entfernt `<script>` bzw. `<img onerror>` nicht), obwohl dieselben
Tests lokal (mehrfach, von zwei Agenten) durchgehend grün liefen. Der Bug-Report
verlangt: (1) Root-Cause-Verifikation, (2) Bewertung möglicher Fixes inkl.
Seiteneffekt auf `CoursesView.vue`/`CoursesView.test.ts`, (3) Workflow-Einordnung.

## Read-only Verifikation (im Rahmen der Triage durchgeführt)

- `frontend/src/components/AnnouncementBanner.vue:31,43-46` und
  `frontend/src/views/courses/CoursesView.vue:319-320` nutzen beide den
  **Default-Export** von `dompurify` (`import DOMPurify from 'dompurify'`)
  und rufen `DOMPurify.sanitize(...)` auf — kein `createDOMPurify(window)`
  pro Aufruf.
- `node_modules/dompurify/dist/purify.cjs.js:1535`: `var purify = createDOMPurify();`
  — das Default-Export-Singleton wird **einmalig beim ersten Modul-Require**
  gebildet, gebunden an das zu diesem Zeitpunkt via `getGlobal()`
  (`purify.cjs.js:342-344`, `typeof window === 'undefined' ? null : window`)
  ermittelte `window`-Objekt. Das stützt die Kern-Hypothese des Bug-Reports:
  Wird das Modul in einem Vitest-Worker-Prozess nur einmal geladen, aber
  `happy-dom` erzeugt pro Testdatei ein neues `window`, bleibt der
  Default-Export an das `window` der zuerst geladenen Testdatei gebunden.
- `frontend/vitest.config.ts:8`: `environment: 'happy-dom'`, kein
  explizites `isolate`/`pool` gesetzt (Vitest-Default). `package.json:40`
  pinnt `vitest: ^4.0.16`, installiert ist `4.1.6`.
- `.github/workflows/ci.yml:141-143`: CI führt `npm run test` (→ `vitest`,
  ohne `run`-Subcommand) auf `ubuntu-latest`/Node 20 aus — abweichend von
  lokalen Entwickler-Läufen. CPU-Kernzahl/Worker-Verteilung des
  GitHub-Actions-Runners unterscheidet sich vermutlich von den lokalen
  macOS-Umgebungen, was beeinflusst, ob mehrere Testdateien denselben
  Worker (und damit denselben `dompurify`-Modul-Singleton) teilen.
- `task-T10.notes.md` (archiviert unter
  `openspec/changes/archive/2026-07-17-add-announcement-banner/task-T10.notes.md`)
  dokumentiert bereits eine verwandte, kleinere happy-dom/DOMPurify-Eigenheit
  bei der exakten Sequenz `<script>…</script><img>` — ein Indiz, dass das
  Environment-Binding von DOMPurify unter `happy-dom` bereits einmal auffällig
  war, aber damals als isolierter Einzelfall eingeordnet wurde.

**Nicht abschließend verifiziert** (bewusst nicht Teil der Triage-Rolle,
gehört in `verification.md`/Architekt-Design bzw. in die Entwickler-Notes):
kein gezielter Mehrdatei-Vitest-Lauf zur direkten Reproduktion des
Cross-File-Bleeds durchgeführt; Vitest-4-Default für `pool`/`isolate` und
dessen genaues Verhalten bezüglich CJS-`require`-Cache-Teilung zwischen
Testdateien im selben Worker wurde nicht im Quellcode von `vitest`
nachvollzogen (nur `.d.ts`-Suche ohne Treffer). Das ist die Aufgabe des
nächsten Agenten, nicht der Triage.

**Ungeprüfte Referenz:** `frontend/src/components/AnnouncementBanner.integration.test.ts`
existiert (per `find` bestätigt), wurde vom Bug-Report nicht erwähnt und
in dieser Triage nicht inhaltlich geprüft — falls relevant, muss der
Architekt/Entwickler das im Design berücksichtigen.

## Rückfragen an den User (nur wenn Klarheit = mehrdeutig)

- Bevorzugst du den **breiteren, projektweiten Fix** (Vitest-Konfiguration
  auf echte Datei-Isolation umstellen, z. B. `pool: 'forks'` mit
  `isolate: true` explizit oder `poolOptions.forks.singleFork: false`),
  der potenziell alle Testdateien mit modul-globalem State betrifft und
  damit auch `CoursesView.test.ts` proaktiv absichert — auch wenn dort
  aktuell kein sichtbarer Fehler auftritt?
- Oder bevorzugst du den **lokal begrenzten Fix** (explizite
  `createDOMPurify(window)`-Instanziierung in `AnnouncementBanner.vue`
  und, aus Konsistenzgründen, auch in `CoursesView.vue`), der
  Produktivcode in zwei Komponenten ändert, aber unabhängig von
  Vitest-internem Verhalten ist?
- Soll `CoursesView.vue`/`CoursesView.test.ts` in jedem Fall mit
  angefasst werden (Konsistenz-Argument), auch wenn dort aktuell keine
  CI-Fehlschläge beobachtet wurden?

## Empfohlene nächste Aktion

`@architect` mit dem Auftrag, basierend auf dieser Triage-Datei einen
`klein`-Change zu erstellen (`change-id` z. B.
`fix-dompurify-vitest-isolation`). Der Architekt soll:

1. Im `design.md` explizit zwischen den zwei Fix-Kandidaten (Vitest-Config
   vs. `createDOMPurify(window)` in Komponenten) entscheiden bzw. beide
   gegeneinander abwägen (Blast-Radius, Wartbarkeit, CI-Laufzeit-Impact
   bei erzwungener Prozess-Isolation).
2. Tasks für `frontend/vitest.config.ts` und/oder
   `frontend/src/components/AnnouncementBanner.vue` und
   `frontend/src/views/courses/CoursesView.vue` (inkl. je zugehöriger
   `*.test.ts`) anlegen — alle Tasks mit `agent: dev-typescript`, da rein
   TypeScript/Vue-Frontend betroffen ist.
3. Im Design festhalten, dass die Pre-Flight-Checks aus `CLAUDE.md`
   Abschnitt 7.1 (`npm run test`, `npm run build`) sowohl lokal als auch
   idealerweise mit einem CI-nahen Worker-Setup laufen sollen, um die
   Behebung tatsächlich gegen das beobachtete CI-Verhalten zu verifizieren
   (nicht nur gegen den bereits mehrfach falsch-grünen lokalen Lauf).

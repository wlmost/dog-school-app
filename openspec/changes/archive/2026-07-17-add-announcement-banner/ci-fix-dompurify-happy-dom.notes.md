# CI-Fix: `AnnouncementBanner.test.ts` schlug in GitHub Actions fehl

**Kontext:** Direkter Fix auf `feature/add-announcement-banner` (bereits
gemergter Stand von PR #70), kein neuer openspec-Change. Ursprüngliche
Root-Cause-Hypothese und Auftrag siehe
`openspec/triage/20260717173845-fix-dompurify-vitest-isolation-ci-flake.md`.

## Chronologie

1. **Erste Hypothese (widerlegt):** DOMPurifys Default-Export sei ein
   Modul-Singleton, das beim ersten Import an ein "veraltetes" `window`
   aus einer früher geladenen Testdatei gebunden bleibe
   (`environment: 'happy-dom'`, ein `window` pro Testdatei, aber ein
   ESM-Modul-Cache pro Worker). Fix-Versuch: `createDOMPurify(window)`
   statt Default-Singleton in `frontend/src/components/AnnouncementBanner.vue`.
   Lokal (auch via Docker mit Node 20, frischem `npm install`,
   `--cpus=2`, `--no-file-parallelism`) **mehrfach verifiziert grün** —
   aber **in CI weiterhin identisch fehlgeschlagen** nach dem Push. Das
   widerlegt die Hypothese: Vitest isoliert Module korrekt pro Testdatei
   (`isolate: true`, Standard), ein Singleton-Bleed über Dateigrenzen
   findet so nicht statt.
2. **Diagnose per temporärem Debug-Log** (`console.error` mit
   `dompurify`-Version, Input/Output, `isSupported`, `hasWindow` in
   `sanitizeHtml()`), committed, gepusht, CI-Log ausgelesen:
   ```
   [DOMPURIFY-DEBUG] {"input":"<p>Text</p><script>alert(1)</script>",
   "output":"Text<script>alert(1)</script>","version":"3.4.12",
   "isSupported":true,"hasWindow":true,"hasDocument":true,
   "windowIsGlobalThis":true}
   ```
   **`version: "3.4.12"`** — lokal (und im Docker-Repro) war durchgehend
   **`3.4.3`** installiert (per `npm ls dompurify` verifiziert, passend
   zu `package-lock.json`). Unterschiedliche Paketversion zwischen CI und
   lokal trotz identischem Commit.

## Tatsächliche Root Cause (verifiziert)

- `frontend/package-lock.json` war **nicht in Git getrackt** — Zeile 57
  der Root-`.gitignore` enthielt `package-lock.json` (Kommentar: "keep
  composer.lock, remove package-lock if using yarn"; das Projekt nutzt
  aber npm, kein Yarn — es existiert kein `yarn.lock`). `git log --all
  -- frontend/package-lock.json` lieferte keine Treffer; `git ls-files
  frontend/package-lock.json` war leer.
- Die CI-Workflow-Datei (`.github/workflows/ci.yml`, Job "Frontend
  tests") führt `actions/checkout@v4` gefolgt von `npm install`
  (working-directory `frontend`) aus. Ohne committtete
  `package-lock.json` im Checkout resolved `npm install` bei **jedem**
  CI-Lauf die jeweils neueste zum SemVer-Range (`^3.3.1`) passende
  `dompurify`-Version neu aus der npm-Registry — das ergab `3.4.12` zum
  Zeitpunkt der CI-Läufe dieses PRs, während die lokal (und in jedem
  Docker-Repro, da dort das lokale, ungetrackte Lockfile ins Container
  gemountet wurde) vorhandene `package-lock.json` durchgehend `3.4.3`
  pinnte.
- `dompurify@3.4.12` liefert mit identischer Konfiguration
  (`{ ALLOWED_TAGS, ALLOWED_ATTR: [] }`) sichtbar fehlerhaftes Sanitizing
  gegenüber `3.4.3` (siehe Debug-Log oben: `<script>` bleibt erhalten,
  `<p>`-Wrapper wird trotz `ALLOWED_TAGS`-Mitgliedschaft entfernt) — ob
  das ein Upstream-Regression-Bug in DOMPurify 3.4.4–3.4.12 ist, wurde
  nicht weiter verfolgt (außerhalb Scope), ist für den Fix aber auch
  irrelevant: **nicht-reproduzierbare Builds durch ein fehlendes Lockfile
  sind der eigentliche, projektweite Fehler**, unabhängig davon, welche
  konkrete Paketversion gerade betroffen ist.
- `cache-dependency-path: frontend/package.json` (statt
  `package-lock.json`) in `actions/setup-node@v4` war ein weiteres
  Symptom derselben Lücke — der npm-Cache-Key hing nie am tatsächlichen
  Lockfile.

## Fix

1. **`.gitignore`**: Zeilen 56–57 (`# Package locks ...` /
   `package-lock.json`) entfernt.
2. **`frontend/package-lock.json`**: erstmals mit `git add -f`
   committed. Enthält `dompurify@3.4.3`, lokal gegen die volle
   Vitest-Suite (`191/191 grün`) und `vue-tsc -b`/`vite build`
   verifiziert.
3. **`.github/workflows/ci.yml`**: `cache-dependency-path` von
   `frontend/package.json` auf `frontend/package-lock.json` korrigiert,
   damit der npm-Cache-Key korrekt am Lockfile hängt.
4. **`frontend/src/components/AnnouncementBanner.vue`**: die temporäre
   Debug-Ausgabe wieder entfernt. Die zuvor eingeführte
   `createDOMPurify(window)`-Bindung (statt Default-Singleton) **bleibt
   bestehen** — sie ist zwar nicht die Ursache dieses konkreten Fehlers,
   entspricht aber weiterhin dem offiziell dokumentierten DOMPurify-Muster
   für Nicht-Standard-Umgebungen und schadet nicht.

## Bewusst NICHT angefasst (außerhalb Scope)

- `frontend/src/views/courses/CoursesView.vue` nutzt weiterhin den
  DOMPurify-Default-Singleton ohne `createDOMPurify(window)`-Bindung.
  Da die eigentliche Ursache (fehlendes Lockfile) jetzt behoben ist,
  besteht hier kein akutes Risiko mehr; die defensive Umstellung auf
  `createDOMPurify(window)` bliebe dennoch als optionaler
  Konsistenz-Fix für einen späteren, eigenständigen Change offen.
- Kein Audit der übrigen `package.json`-Abhängigkeiten auf weitere durch
  das fehlende Lockfile verursachte Versionsabweichungen — dieser Fix
  behebt die Lücke strukturell (zukünftige Installs sind deterministisch),
  ein rückwirkender Vergleich aller bisher in CI tatsächlich installierten
  Versionen wurde nicht durchgeführt.
- `npm audit` meldete beim lokalen `npm install` 6 Schwachstellen
  (1 low, 1 moderate, 4 high) in Dependencies — vorbestehender Zustand,
  nicht Teil dieses Fixes, aber erwähnenswert für einen möglichen
  Folge-Change.

## Verifikation

- `npm ls dompurify` (lokal, nach `npm install` mit committeter
  Lockfile): `dompurify@3.4.3`.
- `npx vitest run`: `Test Files 17 passed (17)`, `Tests 191 passed (191)`.
- `npx vue-tsc -b`: keine TypeScript-Fehler.
- CI-Lauf nach diesem Commit: siehe PR #70, Checks-Status.

## Betroffene Dateien

- `.gitignore`
- `frontend/package-lock.json` (neu committed)
- `.github/workflows/ci.yml`
- `frontend/src/components/AnnouncementBanner.vue` (Debug-Log entfernt)

## Why

`CLAUDE.md` schreibt in Abschnitt 5 und 7.1 die Befehle `composer qa`,
`composer lint`, `composer stan`, `composer compat-check` sowie
`npm run lint` als verbindliche Pre-Flight-/QA-Gates vor, die nach jeder
Task-Implementierung und vor jedem User-Gate 2 laufen müssen. Diese Befehle
existieren im Repo tatsächlich nicht (durch `triage` mit Datei:Zeile belegt,
`openspec/triage/20260728204500-fix-missing-qa-tooling-scripts.md`):

- `backend/composer.json:48-68` — der `scripts`-Block enthält nur
  `post-autoload-dump`, `post-update-cmd`, `post-root-package-install`,
  `post-create-project-cmd`, `dev`. Keine Einträge für `qa`, `lint`, `stan`,
  `compat-check` — **und auch kein `test`-Script** (Letzteres ist ein
  zusätzlicher, über die Triage hinausgehender Befund dieses Change: CI
  ruft `./vendor/bin/pest --no-coverage` bislang direkt auf,
  `.github/workflows/ci.yml:112`, nicht über `composer test`).
- `backend/composer.json:26-35` (`require-dev`) enthält `laravel/pint:
  ^1.13` (bereits installiert, aber nirgends verdrahtet),
  aber **kein** `larastan/larastan` und **kein**
  `phpcompatibility/php-compatibility`. Im `backend/composer.lock` taucht
  `larastan/larastan` nur als `require-dev`-Angabe fremder Pakete auf
  (Zeilen ~246, ~7380, ~7656), es existiert kein eigener
  `packages-dev`-Eintrag dafür — das Paket ist nicht installiert.
- `phpstan.neon` (Repo-Root) referenziert `vendor/larastan/larastan/extension.neon`
  (fehlt, da Paket nicht installiert) und die Pfade `app`, `database`,
  `config`, `routes` — diese liegen aber unter `backend/app`,
  `backend/database` usw., nicht am Repo-Root. Die Datei ist damit
  zusätzlich zum fehlenden Paket auch strukturell fehlkonfiguriert.
  Zusätzlicher Befund dieses Change (über die Triage hinaus, durch Lesen
  bestätigt): `phpstan.neon:17` schließt `app/Console/Kernel.php` aus —
  diese Datei existiert im Laravel-11-Skeleton dieses Projekts nicht mehr
  (`backend/app/Console/` enthält nur `Commands/`, kein `Kernel.php`;
  Routing/Middleware/Scheduling laufen über `backend/bootstrap/app.php`).
  Der Exclude ist damit für ein nicht existierendes File und irreführend,
  aber technisch unschädlich (PHPStan wirft bei einem nicht-matchenden
  `excludePaths`-Eintrag keinen Fehler, im Gegensatz zu
  `reportUnmatchedIgnoredErrors`, das sich nur auf `ignoreErrors` bezieht).
- `frontend/package.json:6-16` (`scripts`) enthält kein `lint`-Script.
  `devDependencies` (Zeilen 24-43) enthält kein ESLint-Paket. Kein
  `eslint.config.*`/`.eslintrc*` im Repo.
- `.github/workflows/ci.yml:95-143` führt aktuell nur
  `./vendor/bin/pest --no-coverage` und `npm run test` aus — keines der in
  `CLAUDE.md` vorausgesetzten QA-Scripts. CI und `CLAUDE.md` laufen damit
  auseinander.

Im zuletzt archivierten Change `fix-trainer-select-customer-creation` haben
alle drei beteiligten Agenten unabhängig dokumentiert, dass sie die in
`CLAUDE.md` vorgeschriebenen QA-Befehle nicht ausführen konnten
(`openspec/changes/archive/2026-07-28-fix-trainer-select-customer-creation/task-T01.notes.md:62-95`,
`task-T02.notes.md:80-111`, `task-T03.notes.md:82-110`). Dieser Change
schließt die Lücke, damit `CLAUDE.md` wieder der Realität entspricht.

## What Changes

- **Backend:** `composer.json` bekommt die Scripts `test`, `lint`, `stan`,
  `compat-check`, `qa` (Kette aus den vorherigen vier). `laravel/pint` wird
  als `lint`-Backend genutzt (bereits installiert). Neu als `require-dev`:
  `larastan/larastan` (für `stan`) und `phpcompatibility/php-compatibility`
  inkl. `dealerdirect/phpcodesniffer-composer-installer` (für
  `compat-check`, `testVersion 8.2`).
- **`phpstan.neon`** wird von Repo-Root nach `backend/phpstan.neon`
  verschoben; die referenzierten Pfade (`app`, `database`, `config`,
  `routes`) sind danach ohne Änderung wieder korrekt, weil sie relativ zum
  neuen Speicherort aufgelöst werden. Der stale `excludePaths`-Eintrag
  `app/Console/Kernel.php` wird entfernt (Datei existiert nicht im
  Laravel-11-Skeleton dieses Projekts).
- **Baseline statt Aufräumen:** Da PHPStan/Larastan, PHPCompatibility und
  ESLint beim ersten Lauf den kompletten Bestandscode prüfen, wird für
  jedes Tool eine **Baseline-/Ignore-Strategie** eingesetzt, die bestehende
  Alt-Verstöße toleriert, ohne sie zu beheben (siehe `design.md`,
  Abschnitt "Baseline-Strategie" — verbindliche Scope-Grenze, vom User
  bestätigt). Kein Aufräumen von Bestandscode in diesem Change.
- **Frontend:** `package.json` bekommt ein `lint`-Script, ESLint +
  `typescript-eslint` + `eslint-plugin-vue` als neue `devDependencies`,
  sowie eine neue Flat-Config `frontend/eslint.config.ts` (oder `.js`,
  Entscheidung bei Implementierung, siehe `design.md`), passend zu
  Vite/Vue 3/TypeScript.
- **CI:** `.github/workflows/ci.yml` ruft zusätzlich `composer qa`
  (Backend-Job) und `npm run lint` (Frontend-Job) auf, damit CI und
  `CLAUDE.md` wieder übereinstimmen.

## Capabilities

### New Capabilities
- `qa-tooling`: Definiert, dass `composer qa`, `composer lint`,
  `composer stan`, `composer compat-check`, `composer test` (Backend) und
  `npm run lint` (Frontend) im Repo real existieren, lokal (inkl. Docker)
  sowie in CI lauffähig sind und lokale QA-Läufe grün abschließen
  (Bestandsverstöße über dokumentierte Baselines toleriert).

### Modified Capabilities
- keine (bestehende Capabilities wie `deployment-pipeline` betreffen den
  Deploy-Workflow, nicht die Test-/QA-CI — hier keine Überschneidung, da
  `.github/workflows/ci.yml` und `.github/workflows/deploy.yml` getrennte
  Workflow-Dateien sind).

## Impact

- **Betroffene Dateien Backend:** `backend/composer.json`,
  `backend/composer.lock` (regeneriert durch `composer require`/`composer
  update`), `backend/phpstan.neon` (neu, verschoben von Repo-Root),
  `phpstan.neon` (Repo-Root, gelöscht), ggf.
  `backend/phpstan-baseline.neon` (neu, falls Larastan Bestandsverstöße
  findet), ggf. `backend/.phpcs-baseline.xml` oder gleichwertige
  Ignore-Konfiguration (neu, falls PHPCompatibility Bestandsverstöße
  findet).
- **Betroffene Dateien Frontend:** `frontend/package.json`,
  `frontend/package-lock.json` (regeneriert), `frontend/eslint.config.ts`
  (neu), ggf. Ignore-Konfiguration innerhalb der ESLint-Config für
  Bestandsverstöße.
- **Betroffene Dateien CI:** `.github/workflows/ci.yml`.
- **Datenbank:** nicht betroffen — keine Migration, kein Modell, kein raw
  SQL. DB-Portabilität unkritisch (Abschnitt 4.2 `CLAUDE.md`).
- **PHP-Kompatibilität:** Neue Dev-Dependencies laufen nur in
  Entwicklung/CI, nicht im Produktivcode-Pfad; das `compat-check`-Script
  selbst prüft `testVersion 8.2` gegen Anwendungscode (Abschnitt 4.1
  `CLAUDE.md`).
- **Shared Hosting:** Keine Auswirkung — alle neuen Pakete sind
  `require-dev`, werden nicht mit deployed (`composer install --no-dev` im
  Deploy-Workflow bleibt unverändert, siehe
  `openspec/specs/deployment-pipeline/spec.md`).
- **Scope-Grenze (verbindlich, User-Entscheidung):** Dieser Change macht
  `composer qa`/`npm run lint` lauffähig und grün — er räumt **nicht** den
  kompletten Bestandscode auf. Alt-Verstöße werden pro Tool über eine
  Baseline/Ignore-Mechanik toleriert und in den jeweiligen
  `task-T*.notes.md` mit konkreten Zahlen dokumentiert (siehe `design.md`).

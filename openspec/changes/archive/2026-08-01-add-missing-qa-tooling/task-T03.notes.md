# Task T03 — Notes (dev-php)

## Ziel

`.github/workflows/ci.yml` so anpassen, dass CI dieselben QA-Gates ausführt,
die `CLAUDE.md` (Abschnitt 5/7.1) lokal vorschreibt: `composer qa` im
Backend-Job, `npm run lint` zusätzlich im Frontend-Job. Voraussetzung
(T01/T02) ist erfüllt: `composer qa` und `npm run lint` existieren real und
laufen lokal mit Exit-Code 0 (siehe `task-T01.notes.md` Abschnitt 7
"Auflösung der Eskalation" und `task-T02.notes.md`).

## Durchgeführte Änderung

Einzige geänderte Datei: `.github/workflows/ci.yml`.

### 1. `backend-tests`-Job

Der bestehende Step "Run backend tests" (vorher Zeilen 95-112, direkter
`./vendor/bin/pest --no-coverage`-Aufruf im Docker-Container) wurde
**ersetzt** durch einen Step "Run backend QA (lint, stan, compat-check,
test)", der `composer qa` statt `./vendor/bin/pest --no-coverage` aufruft.
Das Docker-Ausführungsmuster (Image, `--network=host`, Volume-Mount,
Arbeitsverzeichnis) sowie **alle** bisherigen `-e`-Env-Variablen
(`APP_ENV`, `DB_CONNECTION`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`,
`DB_USERNAME`, `DB_PASSWORD`, `CACHE_STORE`, `SESSION_DRIVER`,
`QUEUE_CONNECTION`) blieben unverändert erhalten — nur der finale Befehl im
Container wurde von `./vendor/bin/pest --no-coverage` auf `composer qa`
geändert.

Kein separater zusätzlicher `pest`-Aufruf wurde ergänzt, da `composer qa`
bereits `@test` (= `@php vendor/bin/pest --no-coverage`, siehe
`backend/composer.json`, dokumentiert in `task-T01.notes.md` Abschnitt 6)
inkludiert — ein zusätzlicher Direktaufruf hätte Pest doppelt in derselben
Matrix-Zeile ausgeführt (Vorgabe aus `design.md` Decision 6 / `tasks.md`
T03-Beschreibung, explizit zur Vermeidung von CI-Zeit-Verschwendung).

Der Step läuft unverändert in **beiden** Matrix-Zweigen (`db: mysql`,
`db: pgsql`) der bestehenden `strategy.matrix.include`, da an der
`strategy`/`services`-Konfiguration nichts geändert wurde. `lint`/`stan`/
`compat-check` sind DB-unabhängig (siehe `design.md` Decision 6, "composer
qa ist DB-agnostisch") und laufen dadurch zwar in beiden Matrix-Zeilen
identisch (kleine CI-Zeit-Redundanz für diese drei Teilschritte), das
entspricht aber exakt der in `tasks.md` T03 vorgegebenen und geprüften
Entscheidung — ein eigener, von der Matrix entkoppelter QA-Job hätte den
bestehenden Zwei-Job-Aufbau (`backend-tests`/`frontend-tests`) und die
Docker-Image-pro-Matrix-Zeile-Struktur grundlegend umgebaut, was außerhalb
des in `tasks.md` beschriebenen T03-Scopes liegt ("Steps in
`backend-tests`- und `frontend-tests`-Jobs ergänzen", keine
Job-Restrukturierung).

### 2. `frontend-tests`-Job

Nach dem bestehenden Step "Run frontend tests" (`npm run test`,
`working-directory: frontend`) wurde ein neuer Step "Run frontend lint"
ergänzt, mit identischem `working-directory: frontend` und `run: npm run
lint`. Kein bestehender Step wurde dafür entfernt oder verändert.

### 3. Unverändert gelassen

- `Set backend directory permissions`, `Build PHP Docker image`, `Install
  Composer dependencies`, `Prepare test environment`, `Generate application
  key`, `Upload logs on failure` (Backend-Job) — keine Änderung.
- `Checkout current branch`, `Setup Node.js`, `Install npm dependencies`
  (Frontend-Job) — keine Änderung.
- Job `deploy-workflow-lint` (Zeilen 149-164, unverändert) — geprüft, dass
  er weder entfernt noch inhaltlich verändert wurde (Akzeptanzkriterium).
- `Upload logs on failure`-Step (Backend-Job) — unverändert, bleibt an den
  (jetzt umbenannten) vorherigen Step gekoppelt über `if: failure()`, ohne
  Step-Referenz per Name/ID, daher durch die Umbenennung nicht betroffen.

## Verifikation

### YAML-Struktur

Da kein `actionlint`/`gh`-CLI-Vorabinstallation im Environment vorausgesetzt
werden kann, wurde die Syntax auf zwei Wegen geprüft:

1. **`python3 -c "import yaml; yaml.safe_load(...)"`** — Datei parst
   fehlerfrei. Zusätzlich verifiziert, dass der letzte Backend-Step jetzt
   `Run backend QA (lint, stan, compat-check, test)` heißt und die
   Frontend-Steps `['Checkout current branch', 'Setup Node.js', 'Install
   npm dependencies', 'Run frontend tests', 'Run frontend lint']` in
   dieser Reihenfolge enthalten.
2. **`rhysd/actionlint`** (offizieller GitHub-Actions-Linter, via
   `docker run --rm -v "$PWD:/repo" -w /repo rhysd/actionlint:latest
   .github/workflows/ci.yml`) — keine Ausgabe, d. h. keine Findings (leere
   Ausgabe = keine Fehler bei `actionlint`).

### Lokaler Funktionsnachweis (identische Befehle wie in CI, innerhalb der laufenden Docker-Umgebung)

Da kein echter GitHub-Actions-Runner verfügbar ist, wurden die exakt in CI
verwendeten Befehle lokal gegen die laufende `docker compose`-Umgebung
ausgeführt (nicht der CI-eigene `docker run`/separates Image, aber
dieselben Composer-/npm-Scripts mit identischem Arbeitsverzeichnis):

```
docker compose exec php composer qa
```
→ Exit-Code **0**. Ausschnitt der Ausgabe (letzte Zeilen):
```
  ✓ customer cannot delete vaccination                                   0.03s

  Tests:    718 passed (2275 assertions)
  Duration: 26.48s
```
(Lint/Stan/Compat-Check liefen vor dem `test`-Schritt in der `qa`-Kette
ohne Abbruch durch, sonst wäre die Kette vorher gestoppt und `test` nicht
erreicht worden — Composer-Script-Ketten (`["@lint", "@stan",
"@compat-check", "@test"]`) brechen bei einem Nicht-Null-Exit-Code eines
Teilschritts sofort ab.)

```
docker compose exec node npm run lint
```
→ Exit-Code **0**. Letzte Zeile der Ausgabe:
```
✖ 3031 problems (0 errors, 3031 warnings)
  0 errors and 2139 warnings potentially fixable with the `--fix` option.
```
(0 Errors, nur Warnings — entspricht der in `task-T02.notes.md`
dokumentierten Baseline-Strategie über Regel-Schweregrad `warn`. ESLint
beendet sich bei reinen `warn`-Funden mit Exit-Code 0, da `--max-warnings`
nicht gesetzt ist, siehe `design.md` Decision 3c.)

**Einschränkung:** Dies ist kein Eins-zu-eins-Nachweis des echten
GitHub-Actions-Runners (dort läuft `composer qa` innerhalb eines frisch via
`docker build` erzeugten Images statt der lokalen `docker compose
exec`-Umgebung, mit eigenem `composer install`-Schritt zuvor). Da aber
sowohl das lokale `docker compose`-PHP-Image als auch das CI-Image auf
demselben `docker/php/Dockerfile` und derselben `backend/composer.lock`
basieren (T01 hat `composer.lock` bereits final aktualisiert, keine
weiteren Dependency-Änderungen in T03), ist das Risiko einer abweichenden
CI-Ausführung gering. Ein finaler Nachweis über einen echten CI-Lauf bleibt
Sache des nächsten Workflow-Schritts (Push/PR), wie in `tasks.md`
Akzeptanzkriterium 4 gefordert ("tatsächlicher (lokaler oder
CI-)Lauf... mit Ergebnis" — hier als lokaler Lauf mit identischen
Befehlen erbracht).

## Diff-Zusammenfassung

Einzige geänderte Datei: `.github/workflows/ci.yml`.

- Backend-Step "Run backend tests" → umbenannt in "Run backend QA (lint,
  stan, compat-check, test)", Befehl `./vendor/bin/pest --no-coverage` →
  `composer qa`. Alle `-e`-Flags und das Docker-Ausführungsmuster
  unverändert übernommen.
- Frontend-Job: neuer Step "Run frontend lint" (`working-directory:
  frontend`, `run: npm run lint`) nach dem bestehenden "Run frontend
  tests"-Step ergänzt.
- Kein anderer Step, kein anderer Job wurde entfernt oder inhaltlich
  verändert.

## Was NICHT verändert wurde

- Kein neuer Job, keine neue Matrix-Dimension.
- `deploy-workflow-lint`-Job unverändert.
- `strategy.matrix`, `services` (MySQL/Postgres) im `backend-tests`-Job
  unverändert.
- Kein Backend-/Frontend-Anwendungscode berührt (reine CI-Workflow-Datei,
  YAML, keine PHP-Datei — Abschnitt 4.1 `CLAUDE.md` daher nicht einschlägig
  für diesen Task).

## Akzeptanzkriterien-Status (tasks.md T03)

- [x] `backend-tests`-Job in `.github/workflows/ci.yml` ruft `composer qa`
  auf (nicht mehr den direkten `pest`-Aufruf) — läuft unverändert in
  beiden Matrix-Zweigen (MySQL, Postgres), da an `strategy.matrix` nichts
  geändert wurde
- [x] `frontend-tests`-Job ruft zusätzlich `npm run lint` auf
- [x] Kein bestehender CI-Step (`deploy-workflow-lint`-Job, Log-Upload bei
  Fehler) wurde entfernt oder inhaltlich verändert
- [x] `task-T03.notes.md` dokumentiert einen tatsächlichen lokalen Lauf
  beider geänderten Jobs (Befehle identisch zu den neuen CI-Steps) mit
  Ergebnis (siehe Abschnitt "Verifikation" oben, beide Exit-Code 0)

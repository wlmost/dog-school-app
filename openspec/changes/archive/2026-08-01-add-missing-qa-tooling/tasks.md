# Tasks für add-missing-qa-tooling

## T01: Backend-QA-Scripts (composer qa/lint/stan/compat-check/test) + phpstan.neon reparieren

- **Agent:** dev-php
- **Dateien:**
  - `backend/composer.json` (Scripts `test`, `lint`, `stan`,
    `compat-check`, `qa`; `require-dev`: `larastan/larastan`,
    `phpcompatibility/php-compatibility`,
    `dealerdirect/phpcodesniffer-composer-installer`, ggf.
    `config.allow-plugins`-Eintrag für den Dealerdirect-Installer)
  - `backend/composer.lock` (regeneriert durch `composer require`)
  - `backend/phpstan.neon` (neu, verschoben von `phpstan.neon` am
    Repo-Root; Pfade `app`/`database`/`config`/`routes` bleiben
    inhaltlich unverändert, siehe `design.md` Decision 1;
    `excludePaths`-Eintrag `app/Console/Kernel.php` entfernen)
  - `phpstan.neon` (Repo-Root, löschen — ersetzt durch
    `backend/phpstan.neon`)
  - `backend/phpstan-baseline.neon` (neu, generiert via `--generate-baseline`,
    falls Larastan Bestandsverstöße findet — siehe `design.md` Decision 3a)
  - ggf. `backend/.phpcs-baseline.xml` bzw. gleichwertige
    Ignore-Konfiguration (neu, falls PHPCompatibility Bestandsverstöße
    findet — siehe `design.md` Decision 3b; exakter Dateiname/Format
    hängt von der tatsächlich installierten PHPCS-Version ab, vom Task zu
    verifizieren)
- **Abhängigkeiten:** keine
- **Beschreibung:**
  `composer.json` bekommt fünf neue Scripts (exakte Befehle siehe
  `design.md` Decision 4):
  - `test`: ruft `vendor/bin/pest --no-coverage` auf (identisch zum
    bisherigen Direktaufruf in `.github/workflows/ci.yml:112`, nur als
    benanntes Script).
  - `lint`: `vendor/bin/pint --test` (Pint ist bereits installiert,
    `backend/composer.json:29`, bislang nur nicht verdrahtet).
  - `stan`: `vendor/bin/phpstan analyse` gegen `backend/phpstan.neon`
    (automatische Auflösung, siehe Decision 1).
  - `compat-check`: `vendor/bin/phpcs --standard=PHPCompatibility
    --runtime-set testVersion 8.2 app/ database/ config/ routes/` (exakte
    Flag-Syntax gegen die tatsächlich installierte PHPCS-Version via
    `vendor/bin/phpcs --help` verifizieren, bevor final eingetragen —
    siehe `design.md` Decision 3b).
  - `qa`: Kette `["@lint", "@stan", "@compat-check", "@test"]`.

  Neue Dev-Dependencies: `larastan/larastan:^3.0` (Fallback `^2.9`, falls
  Laravel-11-Bootstrap scheitert — siehe `design.md` Decision 2, im
  Fallback-Fall Begründung in `task-T01.notes.md` dokumentieren),
  `phpcompatibility/php-compatibility` (aktuelle stabile Version, PHPCS
  ≥ 3.10 für natives Baseline-Feature, siehe `design.md` Decision 3b),
  `dealerdirect/phpcodesniffer-composer-installer` (registriert den
  PHPCompatibility-Standard automatisch bei PHPCS, plus
  `config.allow-plugins`-Eintrag in `composer.json` für dieses Plugin).

  `phpstan.neon` von Repo-Root nach `backend/phpstan.neon` verschieben.
  Inhaltliche Pfadangaben (`app`, `database`, `config`, `routes`)
  unverändert lassen (siehe `design.md` Decision 1 — sie werden dadurch
  automatisch korrekt). `excludePaths`-Eintrag `app/Console/Kernel.php`
  entfernen (Datei existiert im Laravel-11-Skeleton nicht, siehe
  `proposal.md`/`design.md` Context). `database/migrations/*` als Exclude
  beibehalten.

  **Verbindlicher Prüfschritt (siehe `design.md`, Abschnitt "Verbindlicher
  Prüfschritt vor Task-Abschluss"):** `stan` und `compat-check` je einmal
  ungefiltert gegen den Bestandscode laufen lassen, Fehleranzahl exakt in
  `task-T01.notes.md` dokumentieren, danach Baseline/Ignore gemäß
  `design.md` Decision 3a/3b anlegen und Tools erneut grün laufen lassen.
  Beide Zahlen (vorher/nachher) in `task-T01.notes.md` festhalten.
- **Akzeptanzkriterien:**
  - [x] `composer test`, `composer lint`, `composer stan`, `composer
    compat-check`, `composer qa` existieren in `backend/composer.json`
    und sind innerhalb der Docker-Umgebung ausführbar
  - [x] `larastan/larastan` und `phpcompatibility/php-compatibility` sind
    real installiert (Nachweis: `composer show <paket>` bzw. Eintrag in
    `backend/composer.lock` unter `packages-dev`)
  - [x] `backend/phpstan.neon` existiert, `phpstan.neon` (Repo-Root)
    existiert nicht mehr
  - [x] `composer stan` läuft ohne Exit-Code-Fehler durch (ggf. mit
    Baseline)
  - [x] `composer compat-check` läuft ohne Exit-Code-Fehler durch (ggf.
    mit Baseline/Ignore) gegen `testVersion 8.2`
  - [x] `composer qa` (Kette aus lint/stan/compat-check/test) läuft
    komplett grün durch, innerhalb der Docker-Umgebung ausgeführt und in
    `task-T01.notes.md` mit Log-Auszug belegt — ursprünglich **nicht
    erfüllt** (`@lint`/Pint fand 197 Bestandsverstöße in 291 Dateien,
    eskaliert an den User, da Non-Goals kein `pint.json` erlaubten).
    Nach Rückfrage hat der User entschieden, `vendor/bin/pint` einmalig
    (rein mechanisch, ohne `--test`) über den Bestandscode laufen zu
    lassen. `composer qa` läuft seitdem grün (Exit-Code 0), siehe
    `task-T01.notes.md` Abschnitt 7 "Auflösung der Eskalation".
  - [x] `task-T01.notes.md` dokumentiert Vorher-/Nachher-Fehleranzahl für
    `stan`, `compat-check` und `lint` sowie die gewählte
    Baseline-/Fix-Mechanik
  - [x] Kein Bestandscode unter `backend/app/`, `backend/database/`,
    `backend/config/`, `backend/routes/` wurde *logisch* verändert, um
    Verstöße zu beheben (nur Baseline-/Ignore-Dateien und die in diesem
    Task explizit gelisteten Konfigurationsdateien). Ausnahme mit
    expliziter User-Freigabe: der einmalige, rein mechanische
    `vendor/bin/pint`-Formatierungslauf zur Auflösung der `@lint`-
    Eskalation (siehe oben) — keine Logikänderung, siehe
    `task-T01.notes.md` Abschnitt 7
  - [x] Neuer PHP-Code in diesem Task selbst (falls vorhanden, z. B.
    generierte Baseline-Dateien) verstößt nicht gegen `CLAUDE.md`
    Abschnitt 4.1 (keine 8.3/8.4-Features; es wurde kein neuer
    PHP-Anwendungscode geschrieben, nur JSON/NEON/XML-Konfiguration)

## T02: Frontend npm run lint (ESLint) einrichten

- **Agent:** dev-typescript
- **Dateien:**
  - `frontend/package.json` (`lint`-Script;
    `devDependencies`: `eslint`, `typescript-eslint`,
    `eslint-plugin-vue`, ggf. `@vue/eslint-config-typescript`)
  - `frontend/package-lock.json` (regeneriert durch `npm install`)
  - `frontend/eslint.config.ts` (neu, Flat Config für ESLint ≥ 9, passend
    zu Vite 7/Vue 3/TypeScript — siehe `design.md` Decision 5)
  - ggf. Ergänzung von `ignores` in `frontend/eslint.config.ts` für
    einzelne Bestandsdateien/-verzeichnisse (siehe `design.md` Decision 3c)
- **Abhängigkeiten:** keine (unabhängig von T01, anderer Sprach-Stack)
- **Beschreibung:**
  `frontend/package.json` bekommt das Script `"lint": "eslint ."`.
  Neue `devDependencies` (exakte Versionen bei Installation über `npm
  install --save-dev` auflösen und in `task-T02.notes.md`
  dokumentieren): `eslint`, `typescript-eslint` (kombiniertes Parser-
  und Plugin-Paket), `eslint-plugin-vue`. Optional
  `@vue/eslint-config-typescript`, falls das die Konfiguration
  vereinfacht (Entscheidung beim Task, siehe `design.md` Decision 5).

  Neue Flat-Config `frontend/eslint.config.ts`, die mindestens folgende
  Dateitypen abdeckt: `frontend/src/**/*.vue` (`<script setup lang="ts">`,
  siehe `CLAUDE.md` Vue-Konventionen), `frontend/src/**/*.ts`. Basis:
  `eslint:recommended` + `vue/vue3-recommended` (aus
  `eslint-plugin-vue`) + `@typescript-eslint/recommended` (aus
  `typescript-eslint`). Test-Dateien (`*.test.ts`, `frontend/e2e/` falls
  vorhanden) einbeziehen, es sei denn, das erzeugt unverhältnismäßig viele
  Bestandsverstöße — dann Ausnahme mit Begründung in
  `task-T02.notes.md`.

  **Verbindlicher Prüfschritt (siehe `design.md`, Abschnitt "Verbindlicher
  Prüfschritt vor Task-Abschluss"):** `npm run lint` einmal mit der
  minimalen empfohlenen Konfiguration ungefiltert gegen den Bestandscode
  laufen lassen, Fehler-/Warnungsanzahl exakt in `task-T02.notes.md`
  dokumentieren (getrennt nach Errors und Warnings). Danach gemäß
  `design.md` Decision 3c gezielt Regeln auf `warn` herabstufen und/oder
  einzelne Dateien/Verzeichnisse in `ignores` aufnehmen, bis `npm run
  lint` mit Exit-Code 0 durchläuft. Beide Zahlen (vorher/nachher) sowie
  die gewählte Kombination aus Regel-Herabstufung und `ignores` in
  `task-T02.notes.md` festhalten.
- **Akzeptanzkriterien:**
  - [x] `npm run lint` existiert in `frontend/package.json` und ist
    ausführbar
  - [x] ESLint-Dependencies sind real installiert (Nachweis:
    `frontend/package-lock.json`)
  - [x] `frontend/eslint.config.ts` existiert, deckt `.vue`- und
    `.ts`-Dateien unter `frontend/src/` ab
  - [x] `npm run lint` läuft mit Exit-Code 0 durch (ggf. mit
    Regel-Herabstufungen/`ignores` gemäß Baseline-Strategie)
  - [x] `task-T02.notes.md` dokumentiert Vorher-/Nachher-Anzahl an
    Errors/Warnings sowie die gewählte Baseline-Mechanik
    (Regel-Herabstufung vs. `ignores`, mit Begründung)
  - [x] Kein Bestandscode unter `frontend/src/` wurde inhaltlich
    verändert, um Verstöße zu beheben (nur die in diesem Task gelisteten
    Konfigurationsdateien)
  - [x] `npm run build` (bestehendes Script, `frontend/package.json:8`)
    läuft weiterhin ohne Fehler/Warnings — ESLint-Einführung darf den
    bestehenden Vite/`vue-tsc`-Build nicht beeinflussen

## T03: CI-Integration von composer qa und npm run lint

- **Agent:** dev-php
- **Dateien:**
  - `.github/workflows/ci.yml` (Steps in `backend-tests`- und
    `frontend-tests`-Jobs ergänzen)
- **Abhängigkeiten:** T01 (composer qa muss existieren und grün laufen),
  T02 (npm run lint muss existieren und grün laufen)
- **Beschreibung:**
  Im `backend-tests`-Job (`.github/workflows/ci.yml:12-121`) wird der
  bestehende Step "Run backend tests" (Zeile 95-112, direkter
  `./vendor/bin/pest --no-coverage`-Aufruf im Docker-Container) durch
  einen Aufruf von `composer qa` ersetzt (identisches
  Docker-Ausführungsmuster wie die bestehenden Steps: `docker run --rm
  --network=host -v ".../backend:/var/www/html" -w /var/www/html <image>
  composer qa`, mit denselben Env-Variablen wie im bisherigen Step,
  Zeilen 101-110). `composer qa` inkludiert bereits `@test`
  (siehe `design.md` Decision 4) — kein zusätzlicher separater
  Pest-Aufruf nötig, um Doppelausführung zu vermeiden.

  Im `frontend-tests`-Job (`.github/workflows/ci.yml:122-143`) wird nach
  dem bestehenden Step "Run frontend tests" (Zeile 141-143) ein neuer
  Step "Run frontend lint" mit `working-directory: frontend`, `run: npm
  run lint` ergänzt.

  Begründung für "ersetzen statt zusätzlich ausführen" beim
  Backend-Pest-Aufruf: DRY — `composer qa` würde sonst Pest zweimal in
  derselben Matrix-Zeile ausführen (einmal direkt, einmal über die
  `qa`-Kette), was CI-Laufzeit unnötig verdoppelt, ohne zusätzlichen
  Erkenntnisgewinn.
- **Akzeptanzkriterien:**
  - [x] `backend-tests`-Job in `.github/workflows/ci.yml` ruft
    `composer qa` auf (nicht mehr den direkten `pest`-Aufruf) — läuft in
    beiden Matrix-Zweigen (MySQL, Postgres)
  - [x] `frontend-tests`-Job ruft zusätzlich `npm run lint` auf
  - [x] Kein bestehender CI-Step (`deploy-workflow-lint`-Job, Log-Upload
    bei Fehler) wurde entfernt oder inhaltlich verändert
  - [x] `task-T03.notes.md` dokumentiert einen tatsächlichen (lokalen
    oder CI-)Lauf beider geänderten Jobs mit Ergebnis

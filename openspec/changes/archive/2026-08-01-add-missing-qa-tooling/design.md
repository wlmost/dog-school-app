## Context

- `backend/composer.json:1-85` — vollständiger Scripts-Block gelesen
  (Zeilen 48-68): keine `qa`/`lint`/`stan`/`compat-check`/`test`-Einträge.
  `require-dev` (Zeilen 26-35): `laravel/pint: ^1.13` bereits vorhanden.
  `require` (Zeilen 13-25): `laravel/framework: ^11.31` — **Laravel 11**,
  relevant für die Larastan-Bootstrap-Kompatibilität (siehe Decision 2) und
  dafür, dass es kein `app/Http/Kernel.php`/`app/Console/Kernel.php` mehr
  gibt (Laravel-11-Skeleton nutzt `backend/bootstrap/app.php` +
  `backend/bootstrap/providers.php`, verifiziert über
  `backend/app/Console/` — enthält nur `Commands/`, kein `Kernel.php`, und
  `backend/app/Http/` — enthält `Controllers/`, `Middleware/`,
  `Requests/`, `Resources/`, kein `Kernel.php`).
- `phpstan.neon` (Repo-Root, 24 Zeilen) — `includes: -
  vendor/larastan/larastan/extension.neon`, `paths: [app, database,
  config, routes]`, `level: 5`, `excludePaths: [app/Console/Kernel.php,
  database/migrations/*]`, `reportUnmatchedIgnoredErrors: true`.
- `backend/composer.lock` — `larastan/larastan` erscheint nur an drei
  Stellen (Zeilen ~246, ~7380, ~7656) als `require-dev` **fremder** Pakete
  (`dompdf/dompdf`, Laravel-Zero-Toolchain, `pestphp/pest`), kein eigener
  `packages-dev`-Eintrag → nicht installiert. Ebenso kein Treffer für
  `phpcompatibility/php-compatibility`.
- `frontend/package.json:1-47` — `scripts` (Zeilen 6-16): kein `lint`.
  `devDependencies` (Zeilen 24-43): kein ESLint-Paket, aber bereits
  `typescript: ~5.9.3`, `vue-tsc: ^3.1.4`, `vite: ^7.2.4` — moderner
  Stack, für den nur ESLint-**Flat-Config** (`eslint.config.*`) sinnvoll
  ist (String-Configs/`.eslintrc*` sind für ESLint ≥ 9 deprecated).
- `.github/workflows/ci.yml:12-144` — zwei Jobs: `backend-tests` (Docker,
  Matrix MySQL/Postgres, ruft `./vendor/bin/pest --no-coverage` direkt
  auf, Zeile 112) und `frontend-tests` (Node 20, `npm install`, `npm run
  test`, Zeilen 122-143). Ein dritter Job `deploy-workflow-lint` prüft nur
  Deploy-Workflow-Invarianten, nicht relevant für diesen Change.
- Docker-Umgebung: `docker-compose.yml` (Repo-Root) definiert PHP-FPM +
  Postgres + Redis + Nginx; CI baut das Image separat über
  `docker/php/Dockerfile` (`.github/workflows/ci.yml:67-77`). Lokale
  QA-Läufe für diesen Change laufen über `docker compose exec app ...`
  bzw. das CI-Docker-Image, wie in `CLAUDE.md` Abschnitt 7.1 vorgegeben.

## Goals / Non-Goals

**Goals:**
- `composer qa`, `composer lint`, `composer stan`, `composer compat-check`,
  `composer test` existieren real in `backend/composer.json` und laufen
  innerhalb der Docker-Umgebung fehlerfrei durch (Exit-Code 0).
- `npm run lint` existiert real in `frontend/package.json` und läuft
  fehlerfrei durch (Exit-Code 0).
- `backend/phpstan.neon` liegt am richtigen Ort und referenziert die
  richtigen Pfade — keine strukturelle Fehlkonfiguration mehr.
- CI führt dieselben Befehle aus wie lokal gefordert (`CLAUDE.md` ↔ CI
  wieder deckungsgleich).
- Für jedes Tool ist dokumentiert (in der jeweiligen `task-T*.notes.md`),
  wie viele Bestandsverstöße beim ersten Lauf gefunden und wie sie via
  Baseline/Ignore behandelt wurden.

**Non-Goals:**
- Kein Beheben von Bestandsverstößen im Anwendungscode (PHP oder
  TypeScript/Vue). Das ist explizite, vom User bestätigte Scope-Grenze
  (siehe Triage-Rückfrage 1) und würde diesen Tooling-Change zu einem
  unkontrolliert großen Aufräum-Change aufblähen (YAGNI/KISS — ein
  separater Folge-Change kann das gezielt angehen, sobald die Baseline
  bekannt ist).
- Kein `pint.json`/`.php-cs-fixer.php` — Pint läuft mit Defaults (bereits
  in Triage als ausreichend bewertet, kein `pint.json` im Repo).
- Keine Änderung an Pest-Konfiguration, `phpunit.xml` oder bestehenden
  Test-Läufen selbst — `composer test` ruft lediglich denselben
  `vendor/bin/pest --no-coverage`-Befehl auf, den CI bereits direkt
  ausführt (Zeile 112), nur eben als benanntes Composer-Script.
- Keine Migration von `docker-compose.yml`/CI-Docker-Image — die neuen
  Dev-Dependencies werden über das bestehende `composer install`
  installiert, kein neues Docker-Image nötig.
- Kein Wechsel des Frontend-Test-Runners oder -Frameworks — nur ein
  zusätzliches, unabhängiges `lint`-Script.

## Decisions

### 1. `phpstan.neon` → `backend/phpstan.neon`, Pfade unverändert lassen

Der Skeptiker/Triage-Befund zeigt: `paths: [app, database, config,
routes]` ist relativ zum Speicherort der `phpstan.neon`-Datei selbst
(PHPStan-Konvention). Aktuell liegt sie am Repo-Root, wo `app/` etc. nicht
existieren (sie liegen unter `backend/`). **Lösung:** Datei nach
`backend/phpstan.neon` verschieben — die bestehenden Pfadangaben `app`,
`database`, `config`, `routes` sind danach **ohne inhaltliche Änderung**
korrekt, weil sie ab sofort relativ zu `backend/` aufgelöst werden.
Zusätzlich wird der stale `excludePaths`-Eintrag `app/Console/Kernel.php`
entfernt (Datei existiert im Laravel-11-Skeleton dieses Projekts nicht,
siehe Context) und durch einen Kommentar ersetzt, warum kein
Kernel.php-Exclude mehr nötig ist. `database/migrations/*` bleibt als
Exclude bestehen (Migrations sind laut `CLAUDE.md` Abschnitt 4.2 dynamisch
und für PHPStan typischerweise nicht sinnvoll analysierbar).

**Alternative verworfen:** Datei am Repo-Root belassen und Pfade auf
`backend/app`, `backend/database` etc. ändern. Verworfen, weil `composer
stan` als Script in `backend/composer.json` mit Arbeitsverzeichnis
`backend/` läuft (Composer-Scripts laufen relativ zur
`composer.json`-Datei) — PHPStan sucht dort automatisch nach
`phpstan.neon` im aktuellen Verzeichnis, ohne dass `--configuration`
explizit angegeben werden muss. Eine Root-Level-Datei müsste stattdessen
über einen expliziten `--configuration=../phpstan.neon`-Pfad eingebunden
werden — unnötige Kopplung nach außerhalb von `backend/`, wo alle anderen
Backend-Tools (Pint, PHPCS, Pest) bereits liegen.

### 2. `larastan/larastan` Version — `^3.0`

Larastan 3.x basiert auf PHPStan 2.x, unterstützt Laravel 10/11/12 und
verlangt PHP ≥ 8.2 — kompatibel mit dem in `CLAUDE.md` Abschnitt 3
festgelegten kleinsten gemeinsamen Nenner PHP 8.2 und mit
`laravel/framework: ^11.31` (`backend/composer.json:16`). Larastans
Laravel-11-Support (Bootstrap über `bootstrap/app.php` statt
`Http/Kernel.php`) ist seit Larastan 2.7 vorhanden und in der 3.x-Linie
fortgeführt. **Exakte Patch-Version wird bei Implementierung über
`composer require --dev larastan/larastan:^3.0` aufgelöst** — der
Entwickler-Task MUSS nach der Installation `composer show
larastan/larastan` (bzw. das Ergebnis in `composer.lock`) in
`task-T01.notes.md` dokumentieren, da ich (Architekt) keine
`composer`-Befehle ausführen kann und daher keine exakte Patch-Version
verifizieren kann (Anti-Halluzinations-Regel: nicht selbst gelesene
Fakten nicht behaupten).

**Risiko, falls Larastan 3.x mit diesem Laravel-11-Bootstrap dennoch
scheitert** (z. B. durch eine bislang unbekannte Inkompatibilität): Fallback
auf `larastan/larastan:^2.9` (PHPStan 1.x-Linie, ebenfalls
Laravel-11-fähig). Das ist ein Task-Level-Fallback, kein Showstopper für
den Change — in `task-T01.notes.md` zu dokumentieren, falls genutzt.

### 3. Baseline-Strategie pro Tool (verbindliche Scope-Grenze)

Alle drei Analyse-Tools prüfen beim ersten Lauf zwangsläufig den
kompletten Bestandscode (157 PHP-Dateien unter `backend/app/`, 42
Migrationsdateien, 90 Vue/TS-Dateien unter `frontend/src/` — Zahlen aus
der Triage-Datei). Ziel: `composer qa`/`npm run lint` laufen grün, ohne
dass Bestandscode verändert wird.

**a) PHPStan/Larastan (`composer stan`):** natives Baseline-Feature.
Nach der Ersteinrichtung generiert der Entwickler-Task einmalig
```
vendor/bin/phpstan analyse --generate-baseline
```
was `backend/phpstan-baseline.neon` erzeugt (Liste aller aktuell
gefundenen Fehler mit Datei+Musterausdruck). Diese Datei wird über
`includes:` in `backend/phpstan.neon` eingebunden:
```neon
includes:
    - vendor/larastan/larastan/extension.neon
    - phpstan-baseline.neon
```
Damit ignoriert PHPStan exakt die zum Baseline-Zeitpunkt bestehenden
Fehler; **neuer** Code, der neue Fehler einführt, lässt `composer stan`
weiterhin fehlschlagen. Das ist die Standard-PHPStan-Vorgehensweise für
"Tool neu einführen, ohne Bestandscode sofort aufzuräumen" und deckt
exakt die vom User verlangte Scope-Grenze ab. Die Anzahl der
Baseline-Einträge (~Fehleranzahl) MUSS in `task-T01.notes.md` dokumentiert
werden.

**b) PHPCompatibility (`composer compat-check`):** PHP_CodeSniffer
(`squizlabs/php_codesniffer`, Abhängigkeit von
`phpcompatibility/php-compatibility`) bietet seit Version **3.10.0** ein
natives Baseline-Feature (`--generate-baseline` /
`--baseline=<datei>`), analog zu PHPStan. **Entscheidung:**
`squizlabs/php_codesniffer` wird explizit mit `^3.10` als Mindestversion
anvisiert (Composer löst i. d. R. ohnehin die neueste 3.x-Version auf, da
`phpcompatibility/php-compatibility` es nur transitiv mit einer
niedrigeren Untergrenze verlangt). Falls die exakte CLI-Syntax der
installierten Version von der hier beschriebenen abweicht (Flag-Namen
haben sich zwischen PHPCS-Minor-Versionen leicht verändert), MUSS der
`dev-php`-Task die tatsächlich installierte Version per `vendor/bin/phpcs
--version` und die unterstützten Flags per `vendor/bin/phpcs --help`
verifizieren, bevor das `compat-check`-Script final in `composer.json`
eingetragen wird — ich kann das als Architekt nicht selbst ausführen und
schreibe daher keine ungeprüfte exakte Flag-Syntax fest (**Prüfschritt,
siehe unten**).
**Fallback, falls PHPCS-Baseline aus irgendeinem Grund nicht funktioniert
oder die installierte Version zu alt ist:** `exclude-pattern`-Einträge in
einer `backend/.phpcs.xml`-Rulesest-Datei für Verzeichnisse mit den
meisten Verstößen, mit Kommentar, welche Datei/welches Verzeichnis warum
ausgeschlossen wurde. Diese Alternative ist gröber (schließt ganze
Pfade statt einzelner Fundstellen aus) und daher nur Fallback, keine
Erstwahl.

**c) ESLint (`npm run lint`):** ESLint hat **kein** natives
Baseline-Feature wie PHPStan/PHPCS. Zwei gleichwertige, im ESLint-Ökosystem
etablierte Mechanismen stehen zur Wahl, beide über die neue
`frontend/eslint.config.ts` steuerbar:
1. **Regel-Schweregrad `warn` statt `error`** für Regeln, die beim
   Erstlauf viele Bestandsverstöße zeigen (ESLint beendet sich bei reinen
   `warn`-Funden weiterhin mit Exit-Code 0, solange kein `--max-warnings`
   gesetzt wird). Korrektheits-kritische Regeln (z. B. Syntaxfehler,
   nicht auflösbare Importe) bleiben `error`.
2. **Datei-/Verzeichnis-`ignores`** in der Flat-Config für einzelne
   Altlasten-Dateien/-Verzeichnisse mit den meisten Verstößen, falls (1)
   nicht ausreicht.
Der `dev-typescript`-Task WÄHLT die konkrete Kombination erst, nachdem
`npm run lint` einmal probeweise mit einer minimalen empfohlenen
Konfiguration (`eslint:recommended` + `vue/vue3-recommended` +
`@typescript-eslint/recommended`) gegen den Bestandscode gelaufen ist,
und dokumentiert die gewählte Kombination inkl. Fundstellenzahl in
`task-T02.notes.md`. Ziel: möglichst wenige globale `ignores`, möglichst
gezielte Regel-Herabstufungen — kein pauschales Abschalten ganzer
Regel-Kategorien ohne Begründung.

**Gemeinsamer Grundsatz aller drei Baselines:** Baseline-Dateien sind
**Snapshots des Ist-Zustands zum Zeitpunkt dieses Change**, kein Freifahrtschein
für die Zukunft. Sie werden committet, damit `composer qa`/`npm run lint`
für den nächsten Entwickler sofort grün laufen; ein Folge-Change kann sie
gezielt abbauen.

### 4. `qa`-Script als Composer-Script-Kette

```json
"scripts": {
    "test": "@php vendor/bin/pest --no-coverage",
    "lint": "vendor/bin/pint --test",
    "stan": "vendor/bin/phpstan analyse --memory-limit=1G",
    "compat-check": "vendor/bin/phpcs --standard=PHPCompatibility --runtime-set testVersion 8.2 app/ database/ config/ routes/",
    "qa": ["@lint", "@stan", "@compat-check", "@test"]
}
```
`vendor/bin/phpstan analyse` findet `backend/phpstan.neon` automatisch
(Standardsuche im aktuellen Arbeitsverzeichnis, das bei Composer-Scripts
`backend/` ist — siehe Decision 1). Kein `--memory-limit`-Wert ist in
`CLAUDE.md` vorgegeben; `1G` ist ein konservativer Startwert, den der
`dev-php`-Task bei Bedarf (OOM in Docker) anpassen und in
`task-T01.notes.md` begründen soll — `phpstan.neon:23-24` weist bereits
selbst auf mögliche OOM-Probleme hin ("Larastan boots the full app
container, which is heavy").
`--no-coverage` bei `test` entspricht exakt dem bisherigen CI-Aufruf
(`.github/workflows/ci.yml:112`) — keine Verhaltensänderung an den Tests
selbst, nur Benennung als Composer-Script.

### 5. `npm run lint`-Script + ESLint-Dependencies

```json
"scripts": {
    "lint": "eslint ."
}
```
Neue `devDependencies` (Versionen bei Implementierung über `npm install
--save-dev` aufzulösen und in `task-T02.notes.md` mit exakter installierter
Version zu dokumentieren — ich kann `npm install` nicht selbst ausführen):
`eslint`, `typescript-eslint` (kombiniertes Paket für Parser + Plugin,
ESLint-≥9-Standard), `eslint-plugin-vue`, `@vue/eslint-config-typescript`
(optional, falls die manuelle Kombination aus `typescript-eslint` +
`eslint-plugin-vue` mehr Konfigurationsaufwand bedeutet als das
offizielle Vue-Preset — Entscheidung beim `dev-typescript`-Task, beide
Wege erzeugen eine Flat-Config, die ESLint ≥ 9 mit Vue 3 + TS abdeckt).
Neue Datei `frontend/eslint.config.ts` (Flat Config, passend zu
`vite.config.ts`, das laut `frontend/package.json:38` bereits `vite: ^7`
nutzt — Vite 7 und ESLint 9 Flat Config sind der aktuelle Standard-Stack,
kein Rückgriff auf das veraltete `.eslintrc*`-Format nötig).

### 6. CI-Integration

`.github/workflows/ci.yml` bekommt in `backend-tests` (nach dem
bestehenden `Run backend tests`-Step, Zeile 95-112) einen zusätzlichen
Step, der `composer qa` statt (oder zusätzlich zu) dem direkten
`./vendor/bin/pest`-Aufruf ausführt — da `composer qa` bereits `@test`
(also Pest) inkludiert (Decision 4), ersetzt der neue Schritt den
bisherigen `pest`-Direktaufruf, um Doppelausführung zu vermeiden. In
`frontend-tests` (Zeile 122-143) wird nach `Run frontend tests` ein Step
`npm run lint` ergänzt. Beide laufen in der bestehenden Matrix
(MySQL/Postgres für Backend) unverändert weiter — `composer qa` ist
DB-agnostisch (lint/stan/compat-check berühren keine DB, `test`/Pest läuft
bereits in beiden Matrix-Zweigen).

## Verbindlicher Prüfschritt vor Task-Abschluss (Kernrisiko Scope-Explosion)

Da `composer stan`, `composer compat-check` und `npm run lint` bislang
**nie** gelaufen sind, ist unbekannt, wie viele Bestandsverstöße real
auftreten. Das ist das in der Triage benannte Hauptrisiko. Für **T01**
und **T02** gilt daher zusätzlich zu den normalen
Akzeptanzkriterien folgender verbindlicher Ablauf, bevor die Task als
abgeschlossen gilt:

1. Tool installieren/Script eintragen.
2. Tool **einmal ungefiltert** gegen den Bestandscode laufen lassen
   (ohne Baseline/Ignore).
3. Fehleranzahl **exakt** in `task-T*.notes.md` dokumentieren (z. B. "PHPStan
   Level 5: 42 Fehler in 18 Dateien", "PHPCompatibility testVersion 8.2:
   7 Warnings in 3 Dateien", "ESLint: 130 Problems, davon 12 Errors / 118
   Warnings in 26 Dateien").
4. Baseline/Ignore gemäß Decision 3 anlegen.
5. Tool **erneut** laufen lassen — MUSS jetzt Exit-Code 0 liefern.
6. Beide Zahlen (vorher/nachher) und die gewählte Baseline-Mechanik in
   `task-T*.notes.md` festhalten.

**Falls die Fehleranzahl in Schritt 3 so groß ist, dass die
Baseline-Datei selbst unhandlich wird (grober Richtwert: mehrere hundert
Einzel-Findings) oder falls ein Tool aus technischen Gründen (z. B.
Larastan-Bootstrap-Fehler durch Laravel-11-Besonderheiten, siehe Decision
2) gar nicht durchläuft:** Der Task bricht NICHT eigenmächtig in
Zusatz-Aufräumarbeit aus, sondern dokumentiert den Befund in
`task-T*.notes.md` und meldet ihn an den nächsten Workflow-Schritt
(Reviewer/Architekt Modus B) zur Entscheidung, ob die Scope-Grenze aus
diesem Change heraus nachjustiert werden muss — kein eigenmächtiges
Vergrößern des Scopes ohne erneutes User-Gate.

## Risks / Trade-offs

- **Unbekannte Bestandsverstöße (Hauptrisiko, siehe Prüfschritt oben):**
  Mitigiert durch den verbindlichen Prüfschritt und die
  Baseline-Strategie; im schlimmsten Fall (Tool technisch nicht lauffähig)
  landet das Risiko dokumentiert beim Architekten in Modus B statt
  unbemerkt im Code.
- **Larastan-Speicherverbrauch:** `phpstan.neon` warnt bereits selbst vor
  hohem Speicherbedarf ("Larastan boots the full app container"). In
  CI/Docker ggf. `--memory-limit` erhöhen — kein Blocker, aber
  dokumentationspflichtig, falls angepasst.
- **ESLint ohne natives Baseline-Feature:** Die Regel-Schweregrad-Strategie
  (Decision 3c) ist weniger präzise als PHPStans Datei-genaue Baseline —
  sie kann im Zweifel entweder zu viele (breite `ignores`) oder zu wenige
  (zu grobkörnige `warn`-Herabstufung) Verstöße durchlassen. Akzeptiertes
  Trade-off, da ESLint dafür kein Alternativwerkzeug bereitstellt; der
  `dev-typescript`-Task dokumentiert die getroffene Wahl transparent.
  Kann in einem Folge-Change verfeinert werden, falls sich die Strategie
  als zu grob erweist.
- **`composer.lock`/`package-lock.json`-Diffs:** Beide Lockfiles werden
  durch die neuen Dependencies umfangreich verändert (transitive
  Abhängigkeiten). Das ist erwartbar und kein Scope-Problem, aber der
  Reviewer sollte beim Diff-Review nicht durch die Lockfile-Größe
  abgelenkt werden — Fokus auf `composer.json`/`package.json` und die
  neuen Konfigurationsdateien.
- **CI-Laufzeit steigt** durch zusätzliche `composer qa`/`npm run lint`
  Steps in beiden Matrix-Zweigen (Backend: 2x wegen MySQL/Postgres-Matrix).
  Akzeptiertes Trade-off für Konsistenz zwischen lokalem QA-Gate und CI.

# Review: T01 (Backend-QA-Scripts) — inkl. T02/T03 und Gesamt-Diff

**Hinweis zur Ablage:** Dieser Review deckt den vollständigen Diff des Changes
`add-missing-qa-tooling` ab (T01 Backend, T02 Frontend, T03 CI, sowie die
291-Datei-Pint-Formatierung aus der Eskalationsauflösung). Da drei Tasks
denselben, eng gekoppelten Änderungssatz bilden und der Auftrag "vollständigen
Diff prüfen" lautete, wird hier ein gemeinsames Review abgelegt.

**Gesamtempfehlung:** nacharbeit-nötig — **Update 2026-08-01: Der einzige
Muss-Blocker (`symfony/yaml` vs. `config.platform.php`, siehe unten) ist
behoben und durch einen erneuten, unabhängigen Reviewer-Lauf verifiziert.
Fix + Nachweis siehe `task-T01.notes.md`, Abschnitt 11. Fresh Install
(`rm -rf vendor && composer install ...`, exakter CI-Befehl) und
`composer qa` laufen beide mit Exit-Code 0. `composer.lock`-Diff ist
minimal (`symfony/yaml` v8.0.8→v7.4.15, plus zwei transitive
Minor-Bumps `symfony/deprecation-contracts`, `symfony/polyfill-ctype`),
keine neuen `composer audit`-Advisories durch den Downgrade. Damit ist
dieser Change aus Sicht des Reviews abnahmefähig; die "Sollte"-Punkte
unten bleiben optionale Verbesserungsvorschläge, kein Blocker.**

## Muss (blockiert Abnahme) — BEHOBEN (siehe Update oben)

- **[Korrektheit/CI-Bruch]** `backend/composer.json` (gemergter `config`-Block,
  siehe `task-T01.notes.md` Abschnitt "Vorab-Fix") + `backend/composer.lock`
  (unverändert enthaltenes `symfony/yaml: v8.0.8`, `require.php: >=8.4`):
  Der in T01 als harmlose Vorab-Bereinigung durchgeführte Merge der doppelten
  `config`-Keys aktiviert **erstmals wirksam** `config.platform.php: 8.3.0`.
  Auf `main` wurde dieser Wert durch den (dort noch vorhandenen) Duplicate-Key-
  Bug faktisch nie angewendet (PHP-`json_decode`/Composer übernehmen bei
  doppeltem Top-Level-Key den **letzten** Block — auf `main` gewinnt der
  zweite `config`-Block ohne `platform`, siehe `verification.md` Abschnitt
  "Sonstige Beobachtung", die dies bereits als Beobachtung, aber nicht als
  Risiko markierte). Dadurch lief `composer install` auf `main` bislang mit
  der tatsächlichen Laufzeit-PHP-Version 8.4.x, die `symfony/yaml v8.0.8`
  (verlangt `php >=8.4`) erfüllt.

  Mit dem gemergten `config`-Block wird `platform.php: 8.3.0` jetzt real
  wirksam — und `composer install` (ohne `--ignore-platform-reqs`, exakt wie
  in `.github/workflows/ci.yml:77` unverändert aufgerufen: `composer install
  --no-interaction --prefer-dist --optimize-autoloader`) schlägt reproduzierbar
  fehl:

  ```
  Verifying lock file contents can be installed on current platform.
  Your lock file does not contain a compatible set of packages. Please run composer update.

    Problem 1
      - symfony/yaml is locked to version v8.0.8 and an update of this package was not requested.
      - symfony/yaml v8.0.8 requires php >=8.4 -> your php version (8.3.0; overridden via config.platform, actual: 8.4.23) does not satisfy that requirement.
    Problem 2
      - laravel/sail is locked to version v1.57.0 ...
      - symfony/yaml v8.0.8 requires php >=8.4 -> ...
  ```
  Exit-Code **2**.

  **Selbst reproduziert** (nicht nur behauptet): im laufenden
  `docker compose`-Setup dieses Projekts (`docker/php/Dockerfile`, `FROM
  php:8.4-fpm-alpine`, identisch zum CI-Image) `vendor/` vollständig entfernt
  und `docker compose exec php composer install --no-interaction --prefer-dist
  --optimize-autoloader` (identischer Befehl wie `.github/workflows/ci.yml:77`)
  ausgeführt → exakt obiger Fehler, Exit-Code 2. Zum Vergleich mit demselben
  Befehl gegen den unveränderten `main`-Stand (`git stash`) → Erfolg (0
  installs/updates, nur Removals der neuen Dev-Pakete), weil dort
  `platform.php` durch den Duplicate-Key-Bug wirkungslos war.

  **Konsequenz:** Der CI-Schritt "Install Composer dependencies"
  (`.github/workflows/ci.yml:70-77`, von T03 bewusst unverändert gelassen)
  bricht in **beiden** Matrix-Zweigen (`mysql`, `pgsql`) ab, **bevor**
  `composer qa` (der neue Step aus T03, Zeile 95-112) überhaupt erreicht wird.
  Der komplette `backend-tests`-Job schlägt fehl — exakt der Job, den dieser
  Change eigentlich reparieren sollte.

  **Warum das in den Notes nicht auffiel:** `task-T01.notes.md` Abschnitt 1
  dokumentiert zwar den `symfony/yaml`-Konflikt und dass er "vorbestehend,
  nicht Teil dieses Tasks" sei, und behauptet "`composer install` (ohne
  Resolution) läuft davon unberührt weiterhin fehlerfrei durch — **verifiziert
  vor der Änderung**". Diese Verifikation fand nachweislich vor dem
  Config-Key-Merge statt (der als allererster Schritt des Tasks beschrieben
  ist) und wurde **nicht erneut** nach dem Merge durchgeführt. Alle späteren
  Nachweise in `task-T01.notes.md` Abschnitt 6/7 sowie in
  `task-T03.notes.md` ("Verifikation", `docker compose exec php composer
  qa`) liefen gegen ein bereits lokal über inkrementelle `composer require
  --ignore-platform-reqs`-Aufrufe befülltes `vendor/`-Verzeichnis, **nie**
  gegen einen frischen `composer install` aus dem committeten
  `composer.lock` heraus — genau der Pfad, den CI nutzt. Das ist eine Lücke
  im "Verbindlichen Prüfschritt" von `design.md` (der nur `stan`/
  `compat-check`/`lint` explizit vorschreibt, nicht aber einen Fresh-Install-
  Check nach der Config-Bereinigung).

  **Vorschlag:** `symfony/yaml` (und ggf. `laravel/sail`, falls dessen
  `^8.0`-Constraint das erzwingt) im Rahmen dieses Tasks auf eine mit PHP 8.2/
  8.3 kompatible Version zurücksetzen (`composer require --dev
  symfony/yaml:^7.0` o. ä., dann `composer update symfony/yaml` mit
  passendem Platform-Constraint) — das ist zwar technisch außerhalb des
  ursprünglich beschriebenen T01-Scopes, aber eine direkte, durch T01 selbst
  verursachte Konsequenz des (korrekten) Duplicate-Key-Fixes und muss vor
  Abnahme gelöst werden, sonst ist `composer qa`/CI **nicht** lauffähig, wie
  es das zentrale Ziel dieses Changes ist. Alternativ: `config.platform.php`
  auf `8.4.0` anheben (passend zur tatsächlichen CI-/Docker-Laufzeitversion),
  falls die Absicht hinter `8.3.0` nicht mehr aktuell ist — dann aber
  gegen `CLAUDE.md` Abschnitt 3 (Demo max. PHP 8.3) prüfen, ob das
  `platform.php`-Override überhaupt noch seinen ursprünglichen Zweck erfüllt.
  In jedem Fall: nach der Lösung erneut mit vollständig entferntem `vendor/`
  (`rm -rf backend/vendor && composer install`, ohne `--ignore-platform-reqs`)
  verifizieren — nicht nur `composer qa` auf einem bereits befüllten
  `vendor/`.

## Sollte (vor Merge erledigen, kann diskutiert werden)

- **[Doku-Präzision]** `task-T01.notes.md` Abschnitt 1, letzter Satz: "Das
  reine Vorhandensein dieses vorbestehenden Konflikts wird hiermit an den
  nächsten Workflow-Schritt (Reviewer/Architekt) gemeldet, da er außerhalb
  des Scopes von T01 liegt". Das Vorhandensein des Konflikts selbst war zwar
  vorbestehend, seine **Aktivierung** (von "harmlos maskiert" zu "CI-
  blockierend") ist aber eine direkte Folge der in T01 selbst durchgeführten
  Config-Merge-Änderung, keine reine Weitergabe eines fremden Altlast-Risikos.
  Die Formulierung sollte präzisiert werden, damit zukünftige Leser nicht
  annehmen, das Risiko sei unverändert vorbestehend geblieben.
- **[Testbarkeit]** `design.md` "Verbindlicher Prüfschritt vor
  Task-Abschluss" verlangt für `stan`/`compat-check`/`lint` explizit einen
  Vorher/Nachher-Lauf, aber keinen Fresh-Install-Check. Für künftige
  ähnliche Changes wäre es sinnvoll, den Prüfschritt-Katalog um "nach
  strukturellen `composer.json`-Änderungen (z. B. Config-Merges) einmal
  `rm -rf vendor && composer install` ohne Ignore-Flags gegenläufig prüfen"
  zu ergänzen — kein Blocker für diesen Change, aber ein Verbesserungsvorschlag
  für `design.md`/Workflow-Vorlagen künftiger Tooling-Changes.
- **[Nachvollziehbarkeit]** `backend/.phpcs-baseline.xml:4-19` (Kommentarblock)
  ist vorbildlich dokumentiert (Begründung für Alpha-Version-Pin, exakter
  Baseline-Eintrag mit Verweis auf `task-T01.notes.md`) — als Positivbeispiel
  auch für den oben genannten Zusatzfund geeignet: die gleiche Sorgfalt fehlte
  beim Fresh-Install-Check.

## Könnte (optional, Verbesserung)

- **[Robustheit]** `.github/workflows/ci.yml:70-77` ("Install Composer
  dependencies") könnte zukünftig einen eigenen, schnell fehlschlagenden
  Schritt bekommen, der `composer validate --strict` (ohne
  `--ignore-platform-reqs`) direkt nach dem Checkout ausführt — das hätte den
  hier gefundenen Fehler schon vor dem teuren Docker-Image-Build sichtbar
  gemacht, statt erst nach `docker build` + Berechtigungs-Setup.
- **[DRY]** `backend/phpstan-baseline.neon` (143 Bestandsfehler, 31 KB) und
  `backend/.phpcs-baseline.xml` (1 Eintrag) sind beide sauber als "Snapshot,
  kein Freifahrtschein" dokumentiert (`design.md` Decision 3, konsistent
  umgesetzt) — keine Änderung nötig, nur als Bestätigung der Konsistenz
  vermerkt.

## Lob (kurz, was gut gelöst wurde)

- Die Pint-Reformatierung der 197 Bestandsdateien wurde stichprobenartig
  gegen 15+ Dateien unterschiedlicher Kategorien geprüft (Controller,
  Requests, Models, Migrations, Tests, Config, Bootstrap, freistehende
  Skripte `create_demo_data.php`/`requirements-check.php`) sowie per
  automatisiertem Scan auf `use`-Kurznamen-Kollisionen (keine gefunden) und
  `php -l`-Syntaxcheck (alle 197 Dateien fehlerfrei) — durchweg rein formale
  Änderungen (Whitespace, `!` → `! `, `new X()` → `new X`, FQN → `use`
  + Kurzname, PHPDoc-Redundanz-Entfernung via `no_superfluous_phpdoc_tags`,
  Array-Alignment). Keine Logikänderung gefunden. `git diff --name-only`
  bestätigt exakt 197 App-/Test-/Config-Dateien geändert, deckungsgleich mit
  der in `task-T01.notes.md` dokumentierten Pint-Fundzahl.
- `backend/phpstan.neon` (Umzug + `Kernel.php`-Exclude-Entfernung) und die
  Baseline-Dateien entsprechen exakt `design.md` Decision 1/3a — selbst
  nachvollzogen, keine Abweichung.
- `frontend/eslint.config.ts` ist sauber strukturiert, mit begründeten
  `ignores` (nur `manual-test.cjs` als Einzelfall, kein pauschaler
  Verzeichnis-Ausschluss) und begründeten `warn`-Downgrades für genau die
  vier Regeln, die beim Erstlauf Errors verursachten — selbst nachvollzogen:
  `npm run lint` liefert exakt "3031 problems (0 errors, 3031 warnings)",
  Exit-Code 0, deckungsgleich mit `task-T02.notes.md`. `npm run build` läuft
  ebenfalls fehlerfrei durch (selbst nachvollzogen, `vite build` ohne
  Warnings/Fehler).
- `.github/workflows/ci.yml`-Diff ist minimal und exakt auf das Nötige
  beschränkt (zwei Zeilenänderungen Backend-Step, ein neuer Frontend-Step),
  `deploy-workflow-lint`-Job unangetastet, kein doppelter Pest-Lauf — sauber
  nach Decision 6 umgesetzt.
- Das dreistufige Eskalationsprotokoll aus `design.md` ("Verbindlicher
  Prüfschritt", Optionen a/b/c bei Scope-Überschreitung) wurde bei der
  Pint-Eskalation exakt wie vorgesehen genutzt: dokumentiert, nicht
  eigenmächtig gelöst, an den Hauptagenten/User eskaliert, Entscheidung
  nachvollziehbar in `task-T01.notes.md` Abschnitt 7 protokolliert.

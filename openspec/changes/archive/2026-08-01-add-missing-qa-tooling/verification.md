# Verification: add-missing-qa-tooling

**Gesamtstatus:** ok

`openspec validate add-missing-qa-tooling` → `Change 'add-missing-qa-tooling' is valid` (strukturell in Ordnung, daher inhaltlicher Realitätsabgleich durchgeführt).

## Bestätigt

### Backend
- `proposal.md` Z.10-16: "`backend/composer.json:48-68` — scripts-Block enthält nur `post-autoload-dump`, `post-update-cmd`, `post-root-package-install`, `post-create-project-cmd`, `dev`. Kein `test`-Script." → bestätigt, `backend/composer.json:48-68` enthält exakt diese fünf Einträge, kein `test`-Key (`grep -n '"test"' backend/composer.json` → kein Treffer).
- `proposal.md` Z.17-18 / `design.md` Z.5: "`require-dev` (Zeilen 26-35) enthält `laravel/pint: ^1.13`, bereits installiert" → bestätigt, `backend/composer.json:26-35`, Zeile 29: `"laravel/pint": "^1.13"`.
- `proposal.md` Z.19-23 / `design.md` Z.18-22: "`larastan/larastan` erscheint in `backend/composer.lock` nur als `require-dev` fremder Pakete (~Zeilen 246, 7380, 7656), kein eigener `packages-dev`-Eintrag" → bestätigt: Zeile 246 (`barryvdh/laravel-dompdf`), Zeile 7380 (Laravel-Zero-Toolchain-Paket), Zeile 7656 (weiteres Fremdpaket, require-dev-Block mit `laravel/pint`/`pestphp/pest`-Constraints). `grep -n '"name": "larastan/larastan"'` in `backend/composer.lock` liefert keinen Treffer → Paket nicht als eigenes Package installiert. Ebenso kein Treffer für `phpcompatibility`.
- `design.md` Z.14-17: "`phpstan.neon` (Repo-Root, 24 Zeilen), `includes: vendor/larastan/larastan/extension.neon`, `paths: [app, database, config, routes]`, `level: 5`, `excludePaths: [app/Console/Kernel.php, database/migrations/*]`, `reportUnmatchedIgnoredErrors: true`" → bestätigt Zeile für Zeile in `phpstan.neon` (Repo-Root), 24 Zeilen Datei.
- `design.md` Z.8-13 / `proposal.md` Z.30-33: "Laravel 11 nutzt `bootstrap/app.php`/`bootstrap/providers.php`, `backend/app/Console/` enthält nur `Commands/`, kein `Kernel.php`" → bestätigt: `ls backend/app/Console/` → nur `Commands/`; `ls backend/app/Http/` → `Controllers/`, `Middleware/`, `Requests/`, `Resources/`, kein `Kernel.php`; `find backend -iname Kernel.php` findet Treffer ausschließlich unter `backend/vendor/...`, keiner unter `backend/app/`. `backend/bootstrap/app.php` und `backend/bootstrap/providers.php` existieren.
- `design.md` Z.6-7: "`laravel/framework: ^11.31` (`backend/composer.json:16`)" → bestätigt, Zeile 16 exakt dieser Eintrag; `backend/composer.lock` bestätigt installierte Version `v11.51.0` (Laravel 11, nicht 10/12).
- `design.md` Z.62-63: "kein `pint.json` im Repo" → bestätigt, `find backend -maxdepth 1 -iname pint.json` kein Treffer.

### phpstan.neon-Umzug
- `tasks.md` T01 / `design.md` Decision 1: Zielpfad `backend/phpstan.neon` existiert noch nicht (`find . -maxdepth 1 -iname phpstan.neon` findet nur die Root-Datei) → Umzugsziel ist frei, kein Konfliktrisiko.

### Frontend
- `proposal.md` Z.38-40 / `design.md` Z.23-27: "`frontend/package.json:6-16` (`scripts`) enthält kein `lint`; `devDependencies` (Zeilen 24-43) enthält kein ESLint-Paket; kein `eslint.config.*`/`.eslintrc*` im Repo" → bestätigt Zeile für Zeile in `frontend/package.json`. `find . -iname "*eslint*"` (ohne `node_modules`/`vendor`) findet keine Konfigurationsdatei im Repo.
- `design.md` Z.24-25: "`typescript: ~5.9.3`, `vue-tsc: ^3.1.4`, `vite: ^7.2.4` bereits vorhanden" → bestätigt in `frontend/package.json:38,42,39`.

### CI
- `proposal.md` Z.41-44 / `design.md` Z.28-32: "`.github/workflows/ci.yml:95-143` — zwei Jobs `backend-tests` und `frontend-tests`; `./vendor/bin/pest --no-coverage` direkt in Zeile 112; `npm run test` Zeilen 122-143" → bestätigt exakt: Step "Run backend tests" Zeilen 95-112, Pest-Aufruf exakt Zeile 112; `frontend-tests`-Job Zeilen 122-143, `npm run test` Zeile 143.
- `design.md` Z.33-35: "dritter Job `deploy-workflow-lint` prüft nur Deploy-Workflow-Invarianten" → bestätigt, Job `deploy-workflow-lint` (Zeilen 145-160) prüft ausschließlich Vorhandensein von `exclude='backend/public/storage'` und `artisan storage:link` in `deploy.yml`, keine QA-Bezüge.
- `design.md` Z.33-37: "Docker-Umgebung: `docker-compose.yml` definiert PHP-FPM + Postgres + Redis + Nginx; CI baut Image separat über `docker/php/Dockerfile`, `.github/workflows/ci.yml:67-77`" → bestätigt: `docker-compose.yml` enthält Services `nginx`, `postgres`, `redis` (sowie weitere); `.github/workflows/ci.yml:67-68` `docker build ... ./docker/php`, Zeilen 70-77 `composer install` im Container — exakter Match.
- `proposal.md` Impact-Abschnitt / `design.md`: "`deployment-pipeline`-Capability ist von `.github/workflows/deploy.yml` betroffen, getrennt von `ci.yml`" → bestätigt, `.github/workflows/deploy.yml` existiert als separate Datei, `openspec/specs/deployment-pipeline/` existiert als eigene Capability.

### Historischer Beleg (archivierter Change)
- `proposal.md` Z.46-51: Verweise auf `task-T01.notes.md:62-95`, `task-T02.notes.md:80-111`, `task-T03.notes.md:82-110` im archivierten Change `2026-07-28-fix-trainer-select-customer-creation` → alle drei Zeilenbereiche exakt bestätigt (per `grep -n` nachgezählt): T01 Z.62-95 dokumentiert fehlendes `composer qa`/`lint`/`stan`/`compat-check`; T02 Z.80-111 dokumentiert fehlendes `npm run lint`; T03 Z.82-110 dokumentiert dasselbe redundant. Alle drei Agenten unabhängig — Kernbegründung des Changes ist damit solide belegt.

## Widerlegt

- `design.md` Z.247: "passend zu `vite.config.ts`, das laut `frontend/package.json:38` bereits `vite: ^7` nutzt" → Zeile 38 in `frontend/package.json` ist tatsächlich `"typescript": "~5.9.3"`, nicht `vite`. Der `vite: ^7.2.4`-Eintrag steht auf Zeile **39**. Off-by-one-Fehler in der Zeilenangabe, inhaltliche Aussage (vite ^7 ist vorhanden) bleibt aber korrekt — kein Blocker, nur eine unpräzise Quellenangabe.

## Nicht auffindbar

- `design.md` Z.155-159 (Decision 3b): "PHP_CodeSniffer bietet seit Version 3.10.0 ein natives Baseline-Feature (`--generate-baseline`/`--baseline=`)" → dies ist eine externe Tool-Tatsache (PHPCS-Release-Historie), nicht im Repo verifizierbar. `squizlabs/php_codesniffer` ist aktuell nirgends als eigenständiges Package in `backend/composer.lock` installiert (nur als `require-dev`-Constraint diverser Fremdpakete, u. a. eine bereits aufgelöste Version `4.0.1` bei einem transitiven Fremdpaket, `backend/composer.lock:3999` — nicht installiert, nur Constraint-Angabe). Der Architekt hat diese Unsicherheit selbst benannt und die Verifikation explizit an den `dev-php`-Task delegiert (Prüfschritt via `vendor/bin/phpcs --version`/`--help`) — das ist sauber gehandhabt, kein Spec-Mangel.
- `design.md` Z.103-124 (Decision 2): "Larastan 3.x basiert auf PHPStan 2.x, ... Laravel-11-Support seit Larastan 2.7 vorhanden" → externe Bibliotheks-Tatsache, ohne `composer`-Ausführung (im Sandbox-Environment nicht verfügbar: `composer --version` → `command not found`) nicht verifizierbar. Auch hier hat der Architekt die Unsicherheit selbst benannt und einen Fallback (`^2.9`) sowie eine Dokumentationspflicht für den `dev-php`-Task vorgesehen.

## Neue Elemente (Plausibilität)

- `tasks.md` T01: legt `backend/phpstan.neon` an → Pfad existiert noch nicht (nur Root-`phpstan.neon` vorhanden), kein Konflikt.
- `tasks.md` T01: legt `backend/phpstan-baseline.neon` und `backend/.phpcs-baseline.xml` (bzw. gleichwertig) an → beide Pfade existieren noch nicht, konsistent mit der Struktur anderer Backend-Tool-Configs (`backend/phpunit.xml` liegt bereits auf derselben Ebene).
- `tasks.md` T02: legt `frontend/eslint.config.ts` an → Pfad existiert noch nicht, `frontend/vite.config.ts` existiert bereits auf derselben Ebene — konsistenter Ort für eine neue Root-Config im Frontend-Projekt.
- `tasks.md` T03: ändert nur bestehende `.github/workflows/ci.yml`, keine neue Datei — unkritisch.

## Sonstige Beobachtung (kein Spec-Claim, nur Hinweis für nachfolgende Agenten)

`backend/composer.json` enthält den Top-Level-Key `"config"` **zweimal** (Zeilen 8-12 mit `platform.php: 8.3.0`, und erneut Zeilen 74-82 mit `optimize-autoloader`/`allow-plugins`/etc.). Das ist gültiges, aber unübliches JSON — beim Zusammenführen mit `json_decode` gewinnt üblicherweise der letzte Schlüssel, wodurch `platform.php` faktisch wirkungslos sein könnte. Weder `proposal.md` noch `design.md` noch `tasks.md` erwähnen dies, und T01 plant ohnehin eine Änderung genau im zweiten `config`-Block (`config.allow-plugins`-Eintrag für den Dealerdirect-Installer). Da dies keine konkrete Behauptung der Spec widerlegt, wird es hier nur als Hinweis vermerkt, nicht als Blocker gewertet.

## Empfehlung

Die Spec ist verlässlich und eng an der Codebasis verankert — praktisch jede konkrete Datei:Zeile-Behauptung in `proposal.md` und `design.md` wurde 1:1 bestätigt, einschließlich der Zeilenangaben zu `composer.json`, `composer.lock`, `phpstan.neon`, `frontend/package.json`, `.github/workflows/ci.yml` und den drei referenzierten `task-T*.notes.md`-Dateien des archivierten Vorgänger-Changes. Der "Verbindliche Prüfschritt vor Task-Abschluss" (ungefiltert laufen lassen → Fehleranzahl dokumentieren → Baseline anlegen → erneut grün laufen lassen) ist sowohl in `design.md` als auch redundant und konkret in T01 und T02 von `tasks.md` verankert und sollte dev-Agenten ohne Rückfragen umsetzbar sein; T03 benötigt ihn korrekterweise nicht, da es nur CI-Verdrahtung ohne eigenen Erstlauf ist. Die einzige gefundene Ungenauigkeit ist eine Off-by-one-Zeilenangabe in `design.md:247` (kein Blocker). Die beiden "nicht auffindbar"-Punkte (PHPCS-Baseline-Feature seit 3.10.0, Larastan-3.x-Laravel-11-Kompatibilität) sind externe Tool-Fakten, die der Architekt bereits selbst als unverifiziert gekennzeichnet und korrekt als Prüfschritt an die dev-Tasks delegiert hat — kein Nacharbeitsbedarf am Design. **Der Change ist bereit für User-Gate 1.**

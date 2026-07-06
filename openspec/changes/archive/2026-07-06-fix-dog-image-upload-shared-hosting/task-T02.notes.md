# Notes: T02 — `.user.ini`-/`php.ini`-Templates für PHP-FPM- und CGI/FastCGI-Hosts + Build-Skripte + Doku

**Change:** `fix-dog-image-upload-shared-hosting`
**Agent:** dev-php
**Datum:** 2026-07-06

---

## Umgesetzte Dateien

- `deployment-templates/htaccess/backend-public.user.ini` (neu) —
  `upload_max_filesize = 10M` / `post_max_size = 12M`, exakt wie in
  `tasks.md` T02 und `design.md` Abschnitt 2 spezifiziert.
- `deployment-templates/htaccess/backend-public.php.ini` (neu) — identischer
  Inhalt, als Fallback für CGI/FastCGI-Wrapper-Setups (Panel-Konvention,
  keine PHP-Core-Garantie, siehe `design.md` Abschnitt 2).
- `build-deployment.sh`:
  - `copy_htaccess_files()` (ursprünglich Zeilen 227-264): kopiert nun
    zusätzlich `backend-public.user.ini` → `$BUILD_DIR/backend/public/.user.ini`
    und `backend-public.php.ini` → `$BUILD_DIR/backend/public/php.ini`,
    jeweils mit `|| error_exit ...` analog zum bestehenden Pattern.
  - `verify_htaccess_files()` (ursprünglich Zeilen 268-287): Prüfliste um
    beide neuen Pfade erweitert.
- `build-deployment-docker.sh`: identische Änderungen in
  `copy_htaccess_files()` (ursprünglich Zeilen 325-356) und
  `verify_htaccess_files()` (ursprünglich Zeilen 359-377).
- `DEPLOYMENT.md`: neuer Troubleshooting-Eintrag "Problem: Bild-Upload
  schlägt fehl (\"413 Request Entity Too Large\" o. ä.)" im Abschnitt
  "Troubleshooting (Shared Hosting)", direkt nach "Problem: Nach
  Installation \"Page not found\"". Erklärt alle drei parallelen
  Mechanismen (`.htaccess`, `.user.ini`, `php.ini`), den Post-Deployment-
  Check (Hoster-Panel/`phpinfo()`) und den `user_ini.cache_ttl`-Hinweis
  (PHP-Default 300s, Werte werden nicht sofort wirksam).

## Abweichungen von der Task-Beschreibung

Keine. Alle fünf in `tasks.md` T02 aufgeführten Änderungspunkte wurden
1:1 umgesetzt.

## Verifikation / Probelauf

- `bash -n build-deployment.sh` und `bash -n build-deployment-docker.sh`:
  beide syntaktisch fehlerfrei.
- Lokaler Probelauf von `./build-deployment.sh` (Docker-Compose-Umgebung
  bereits mit laufenden Containern `dog-school-php`/`dog-school-node`,
  daher die nicht-Docker-Build-Variante gewählt — funktional identisch für
  diesen Zweck, da beide Skripte dieselbe `copy_htaccess_files()`/
  `verify_htaccess_files()`-Logik pro Datei durchlaufen):
  - Build lief vollständig durch (`✓ Build Completed Successfully!`).
  - `tar -tzf <archiv>.tar.gz | grep -E "backend/public/\.user\.ini|backend/public/php\.ini"`
    liefert:
    ```
    ./backend/public/.user.ini
    ./backend/public/php.ini
    ```
    → beide neuen Dateien sind im Build-Output vorhanden, Akzeptanzkriterium
    erfüllt.
  - Das erzeugte Test-Archiv wurde danach gelöscht (kein Artefakt-Commit).

## Wichtiger Nebeneffekt während des Probelaufs (behoben)

`build-deployment.sh`s `install_backend_dependencies()` führt
`composer install --no-dev` **direkt im laufenden Dev-Container**
`dog-school-php` aus (nicht in einer isolierten Kopie) — das hat während
des Probelaufs kurzzeitig die Dev-Dependencies (`pint`, `pest`, etc.) aus
`backend/vendor` entfernt. Ich habe das unmittelbar danach mit
`docker compose exec php composer install` (ohne `--no-dev`) rückgängig
gemacht und über `vendor/bin/pest`/`vendor/bin/pint` verifiziert, dass die
Dev-Umgebung wieder vollständig ist. Das ist kein durch T02 verursachtes
strukturelles Problem (dasselbe Verhalten hätte jeder andere Probelauf von
`build-deployment.sh` in dieser Docker-Umgebung ausgelöst) — nur als
Hinweis für künftige Probeläufe dokumentiert, damit niemand überrascht ist.

## Pre-Flight-Checks (CLAUDE.md Abschnitt 7.1)

Ausgeführt in der Docker-Umgebung (`docker compose exec php ...`):

- `vendor/bin/pest --no-coverage`: **670 passed (2086 assertions)**,
  Duration 27.49s — keine Regression durch T02 (T02 berührt keinen
  PHP-Anwendungscode).
- `vendor/bin/pint --test`: meldet zahlreiche vorbestehende
  Formatierungsabweichungen in `database/factories/*`,
  `database/migrations/*`, `database/seeders/*`, `requirements-check.php`,
  `routes/api.php` und diversen `tests/*`-Dateien. **Keine dieser Dateien
  wurde von T02 angefasst** — die Befunde sind vorbestehender Zustand des
  Repos, nicht durch diesen Task verursacht.
- `composer stan` / `composer compat-check`: **nicht ausführbar**, da
  `backend/composer.json` (die tatsächlich im Docker-Container gemountete
  Datei) diese Scripts nicht definiert und die zugehörigen Dev-Packages
  (`larastan/larastan`, `phpcompatibility/php-compatibility`,
  `squizlabs/php_codesniffer`) nicht in `backend/composer.json`
  `require-dev` gelistet sind. Diese Scripts existieren nur im
  Projekt-Root-`composer.json` (das keine `app/`-Struktur hat und offenbar
  ein nicht mehr aktiver Rest-Skeleton ist — root-`vendor/` existiert nicht).
  Das ist eine bereits vorher bestehende Inkonsistenz zwischen `CLAUDE.md`
  Abschnitt 5/7.1 und dem tatsächlichen Repo-Zustand, **nicht Teil des
  Scopes von T02** (T02 ändert weder `composer.json` noch PHP-Anwendungscode).
  Da T02 ausschließlich `.ini`-Konfigurationsdateien, Bash-Build-Skripte und
  Markdown-Dokumentation betrifft, ist PHP-8.2-Kompatibilität (Abschnitt 4.1
  der `CLAUDE.md`) ohnehin nicht einschlägig (siehe auch `design.md`,
  Abschnitt 5, "PHP-8.2-Kompatibilität").
- `npm run lint`/`npm run test`/`npm run build`: nicht Teil des
  Pre-Flight für diesen Task, da T02 keine Frontend-Dateien berührt.

## Bekannte offene Risiken (aus `design.md`, unverändert von T02 zu bewerten)

- Ob `.user.ini` bzw. `php.ini` auf dem tatsächlichen Ziel-Hoster überhaupt
  ausgewertet werden, ist aus dem Repo nicht verifizierbar (siehe
  `design.md`, Abschnitt 2, "Offenes Risiko"). Ein echter
  Shared-Hosting-Smoke-Test nach Deployment bleibt erforderlich.

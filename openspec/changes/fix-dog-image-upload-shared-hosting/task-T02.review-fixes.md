# Nacharbeit zu T01/T02 — Review-/Test-Befunde behoben

Diese Datei dokumentiert zwei Korrekturen, die nach dem Review-
(`task-T02.review.md`) und Test-Durchgang (`task-report.md`) an bereits
abgehakten Tasks (T01/T02) vorgenommen wurden. Kein neuer Task, keine
Spec-Änderung — reine Nacharbeit an bestehendem Code.

## Fix 1 — Reviewer-Finding (Muss/Sicherheit): `.user.ini`/`php.ini` öffentlich abrufbar

**Befund (`task-T02.review.md`, Abschnitt "Muss"):** `backend-public.user.ini`
und `backend-public.php.ini` (T02) werden nach `backend/public/.user.ini`
bzw. `backend/public/php.ini` deployed. Anders als `.htaccess` selbst
(durch Apaches `<Files ".ht*"> Require all denied`-Default geschützt) sind
diese Dateien nicht durch ein Apache-eigenes Muster geschützt und wurden
vom statischen Datei-Handler (nicht vom Rewrite-Fallback) als Klartext
ausgeliefert.

**Fix:** In `deployment-templates/htaccess/backend-public.htaccess` wurde
direkt nach dem bestehenden `php_value`-Fallback-Block (T01) ein
`<FilesMatch>`-Deny-Block ergänzt, im Stil der bereits etablierten dualen
Apache 2.2/2.4-Syntax (Vorlage: `frontend-dist.htaccess:1-8`):

```apache
<FilesMatch "^(\.user\.ini|php\.ini)$">
    <IfModule mod_authz_core.c>
        Require all denied
    </IfModule>
    <IfModule !mod_authz_core.c>
        Order allow,deny
        Deny from all
    </IfModule>
</FilesMatch>
```

Der Block wirkt unabhängig vom `mod_rewrite`-Block, da `<FilesMatch>` auf
Verzeichnis-/Directory-Merge-Ebene ausgewertet wird, nicht auf der
Rewrite-Engine — er greift also unabhängig davon, ob mod_rewrite auf dem
jeweiligen Host aktiv ist.

**Verifikation (lokal, macOS System-Apache 2.4.66, `httpd -M` zeigt
`authz_core`/`headers`/`negotiation`/`rewrite` als ladbare Module):**

1. Syntaxprüfung: minimale `httpd.conf` mit `DocumentRoot` auf ein
   Testverzeichnis, das eine Kopie von `backend-public.htaccess` als
   `.htaccess` sowie Dummy-Dateien `index.php`, `.user.ini`, `php.ini`
   enthält (`AllowOverride All`). `httpd -t -f <test-conf>` →
   `Syntax OK`.
2. Funktionaler Test (`httpd -k start` gegen dieselbe Test-Konfiguration,
   Port 18099):
   - `GET /.user.ini` → `403`
   - `GET /php.ini` → `403`
   - `GET /some/nonexistent/path` (simuliert den Rewrite-Fallback auf
     `index.php` für nicht-existente Pfade, wie ihn Laravels
     Front-Controller-Routing braucht) → `200` (Rewrite-Regeln also
     unverändert funktionsfähig)
   - `GET /index.php` Header-Check: `X-Content-Type-Options`,
     `X-Frame-Options`, `X-XSS-Protection`, `Referrer-Policy` weiterhin
     vorhanden (Security-Header aus dem bestehenden `mod_headers`-Block
     unverändert)
3. Test-Setup nach Prüfung entfernt (temporäres Scratch-Verzeichnis,
   nicht Teil des Repos).

**Geänderte Datei:** `deployment-templates/htaccess/backend-public.htaccess`

## Fix 2 — Tester-Finding (kritische Lücke): `deploy.yml` liefert T02-Dateien nicht aus

**Befund (`task-report.md`, Abschnitt "Verbleibende Lücken", Punkt 2):**
Der produktive GitHub-Actions-Deploy-Workflow (`.github/workflows/deploy.yml`,
Schritt "Prepare deployment package") repliziert die `.htaccess`-Kopierlogik
manuell per `cp`, statt `build-deployment.sh`/`build-deployment-docker.sh`
aufzurufen. Die beiden neuen T02-`cp`-Zeilen fehlten dort, obwohl beide
Build-Skripte korrekt angepasst wurden — der reale automatisierte Deploy
hätte T01 (`.htaccess`), aber nicht T02 (`.user.ini`/`php.ini`) ausgeliefert.

**Fix:** In `.github/workflows/deploy.yml`, Schritt "Prepare deployment
package", direkt nach dem bestehenden `.htaccess`-Kopierblock ergänzt:

```yaml
          # PHP config overrides for shared-hosting image upload limits
          # (see backend-public.htaccess's php_value fallback for mod_php hosts)
          cp deployment-templates/htaccess/backend-public.user.ini      deploy-package/backend/public/.user.ini
          cp deployment-templates/htaccess/backend-public.php.ini       deploy-package/backend/public/php.ini
```

**Geprüft, kein zusätzlicher Fix nötig:**

- **Explizite Verify-Stelle in `deploy.yml`:** Es existiert dort — anders
  als in `build-deployment.sh`/`build-deployment-docker.sh`
  (`verify_htaccess_files()`) — **keine** dedizierte Verify-Funktion; die
  `.htaccess`-Kopien werden ebenfalls ohne nachgelagerte Existenzprüfung
  per einfachem `cp` ausgeführt. GitHub Actions führt `run:`-Steps auf
  `ubuntu-latest` standardmäßig mit `bash --noprofile --norc -eo pipefail`
  aus, d. h. ein fehlschlagender `cp` (z. B. weil die Quelldatei nicht
  existiert) bricht den Step bereits jetzt sichtbar mit einem Fehler ab —
  konsistent mit dem Verhalten der bereits vorhandenen sechs
  `.htaccess`-`cp`-Zeilen. Es gibt also kein bestehendes Verify-Muster in
  `deploy.yml`, das für die zwei neuen Zeilen nachgezogen werden müsste;
  das Fehlverhalten bei fehlender Quelldatei ist bereits konsistent zum
  Rest des Steps abgesichert.
- **`.htaccess.production` (T04):** `.github/workflows/deploy.yml` enthält
  **keine** Referenz auf `backend/public/.htaccess.production` (per
  `grep -n "htaccess.production" .github/workflows/deploy.yml` bestätigt,
  kein Treffer) — der Workflow hat diese Datei nie separat kopiert, sie
  wurde ohnehin implizit über `rsync -a backend/ deploy-package/backend/`
  mitgezogen, solange sie existierte, und fällt jetzt automatisch weg, da
  sie im Backend-Verzeichnis (T04) gelöscht wurde. Keine weitere Änderung
  in `deploy.yml` nötig.

**Geänderte Datei:** `.github/workflows/deploy.yml`

## Testergebnis nach beiden Fixes

```
docker compose exec php vendor/bin/pest --no-coverage
Tests:    671 passed (2087 assertions)
Duration: 24.39s
```

Keine Regression. `composer qa` existiert projektweit nicht (weder in
`composer.json` (Root) noch in `backend/composer.json`) — bereits in
`task-T02.notes.md` als vorbestehende Diskrepanz dokumentiert; stattdessen
direkt `vendor/bin/pest --no-coverage` ausgeführt (deckt den
projektrelevanten Teil ab: diese Nacharbeit betrifft ausschließlich
Deployment-Artefakte/Workflows, keinen PHP-Anwendungscode, daher keine
PHPStan-/CS-Fixer-relevanten Änderungen).

## Weitere beim Review von `deploy.yml` aufgefallene, nicht behobene Punkte
(außerhalb des Scopes dieser beiden Findings)

- `deploy.yml` dupliziert generell die Deployment-Zusammenstellungslogik
  aus `build-deployment.sh`/`build-deployment-docker.sh`, statt eines der
  beiden Skripte aufzurufen (bereits vom Tester in `task-report.md`
  angemerkt). Ein künftiger dritter Kopierpfad (z. B. weitere
  `.htaccess`-Varianten oder Config-Dateien) würde erneut manuell in allen
  drei Stellen nachgezogen werden müssen. Das ist eine strukturelle
  Diskrepanz, die über die beiden hier behobenen Findings hinausgeht und
  als eigener openspec-Change ("`deploy.yml` auf `build-deployment.sh`
  umstellen, um Drift zwischen Build-Skripten und Deploy-Workflow
  strukturell auszuschließen") sinnvoll wäre. Nicht in diesem Change
  behoben, da außerhalb des Auftragsumfangs (nur die zwei benannten
  Findings).
- Keine weiteren Dateien in `deploy.yml` referenzieren
  `.htaccess.production`, `backend-public.user.ini`/`php.ini` oder
  ähnliche T01/T02/T04-Artefakte, die noch inkonsistent wären.

# Notes T01: Apache `LimitRequestBody` + `php_value`-Fallback in `backend-public.htaccess`

**Agent:** dev-php
**Datum:** 2026-07-06

## Umsetzung

Am Anfang von `deployment-templates/htaccess/backend-public.htaccess`
(vor dem bestehenden `<IfModule mod_negotiation.c>`-Block) wurde exakt der
in `tasks.md` (T01) und `design.md` (Abschnitt 2) spezifizierte Block
eingefügt:

```apache
# Increase request body / upload limits to allow image uploads up to
# Laravel's validation limit (5 MB, see DogController.php:185).
# Apache's own default (no LimitRequestBody) is unlimited, but many shared
# hosting PHP defaults (upload_max_filesize/post_max_size) are far below
# 5 MB. LimitRequestBody caps the raw request body at the web server
# layer, analogous to nginx's client_max_body_size.
LimitRequestBody 10485760

# Fallback for hosts running PHP as an Apache module (mod_php). Wrapped in
# IfModule so this is a no-op on PHP-FPM/CGI setups (see backend-public.user.ini
# for that case), where php_value in .htaccess would otherwise be ignored
# or cause a 500.
<IfModule mod_php.c>
    php_value upload_max_filesize 10M
    php_value post_max_size 12M
</IfModule>
<IfModule mod_php8.c>
    php_value upload_max_filesize 10M
    php_value post_max_size 12M
</IfModule>
```

Der bestehende Inhalt der Datei (Options/Negotiation-Block,
`DirectoryIndex`, `mod_rewrite`-Regeln, `mod_headers`-Security-Header,
auskommentierter HTTPS-Redirect) wurde **nicht** verändert — verifiziert
per Vollständig-Read der Datei vor und nach der Änderung sowie durch
Sichtprüfung, dass nur Zeilen 1–21 (neuer Block + Leerzeile) hinzugefügt
wurden.

Geänderte Datei:
`deployment-templates/htaccess/backend-public.htaccess`

## Pre-Flight-Checks (Docker-Umgebung, wie in CLAUDE.md 7.1 gefordert)

Der lokale Docker-Daemon war zu Beginn nicht gestartet; nach Start von
Docker Desktop und `docker compose up -d` liefen die Container
(`php`, `postgres`, `redis`, `nginx`, `queue`, `scheduler`, `node`,
`mailpit`).

**Befund zur QA-Tooling-Diskrepanz (nicht Teil des T01-Scopes, hier nur
dokumentiert):** Das Root-`composer.json` (Projekt-Wurzel) definiert die
in `CLAUDE.md` Abschnitt 5 beschriebenen Scripts `qa`/`lint`/`stan`/
`compat-check` sowie die Dev-Dependencies `larastan/larastan`,
`phpcompatibility/php-compatibility`, `squizlabs/php_codesniffer`. Das
tatsächlich für den Laravel-Backend-Code genutzte `backend/composer.json`
(im PHP-Container nach `/var/www/html` gemountet, von der CI unter
`working-directory: backend` verwendet) enthält diese Scripts und
Dev-Dependencies **nicht** — `vendor/bin/phpstan` und `vendor/bin/phpcs`
existieren dort nicht, `composer qa`/`composer test` sind als Kommandos
nicht definiert. Das ist eine vorbestehende Diskrepanz zwischen den
beiden `composer.json`-Dateien im Repo, unabhängig von dieser Task
(T01 ändert ausschließlich eine `.htaccess`-Datei, kein PHP). Sie wird
hier nur zur Transparenz dokumentiert, nicht im Rahmen von T01 behoben
(out of scope).

Tatsächlich ausgeführte Checks in `backend/` (im `php`-Container):

- `vendor/bin/pint --test` — schlägt fehl, aber mit denselben,
  vorbestehenden Findings in zahlreichen `.php`-Dateien (Models,
  Controllers, Requests, Factories, Tests etc.), die mit dieser Task
  **nichts** zu tun haben — die geänderte `.htaccess`-Datei wird von
  Pint (PHP-Formatter) gar nicht erfasst. Verifiziert durch Vergleich der
  Fehlerliste mit dem Diff dieser Task (keine Überschneidung).
- `vendor/bin/pest` — **670 Tests bestanden (2086 Assertions), keine
  Fehlschläge.** Bestätigt, dass die Testsuite durch die
  `.htaccess`-Änderung nicht beeinträchtigt wird (kein Test liest diese
  Datei ein, verifiziert per `grep -rln "backend-public.htaccess"
  backend/tests tests`, kein Treffer).

`phpstan`/`compat-check` konnten mangels installierter Binaries in
`backend/vendor` nicht ausgeführt werden (siehe Diskrepanz-Befund oben);
da T01 keine PHP-Datei ändert, ist das für diese Task nicht
akzeptanzrelevant (Akzeptanzkriterium 3 verlangt nur, dass die
Pre-Flight-Suite durch die `.htaccess`-Änderung nicht **zusätzlich**
bricht — das ist durch den grün laufenden Pest-Lauf belegt).

## Wichtiger Hinweis: Shared-Hosting-Smoke-Test nach Deployment erforderlich

Wie in `design.md`, Abschnitt 2 ("Offenes Risiko / zu verifizieren"),
dokumentiert, ist **aus dem Repo nicht verifizierbar**, ob

1. der Ziel-Hoster `AllowOverride` für `LimitRequestBody` im Kontext von
   `backend/public/` erlaubt, und
2. Apache beim internen Rewrite (`RewriteRule ^api/(.*)$
   backend/public/index.php [L,QSA]`, ohne `[PT]`-Flag) die
   `.htaccess` in `backend/public/` tatsächlich zuverlässig für den
   aufgelösten Zieldateisystempfad auswertet.

Die lokale Docker-Entwicklungsumgebung verwendet **Nginx**, nicht
Apache (`docker-compose.yml`, Service `nginx`, Image
`nginx:1.25-alpine`) — ein lokaler Test dieser `.htaccess`-Datei gegen
einen echten Apache-Server war daher im Rahmen dieser Task nicht
möglich und ist auch nicht durch CI abgedeckt.

**Nach dem Deployment auf den Ziel-Shared-Hoster ist daher zwingend ein
echter Smoke-Test nötig:** Upload eines Testbildes zwischen 2 MB und
8 MB über `POST /api/v1/dogs/{id}/upload-image` (bzw. über die
Frontend-UI). Erwartetes Ergebnis: Upload erfolgreich (kein HTTP 413/500).
Schlägt `LimitRequestBody` am `AllowOverride`-Kontext des Hosters fehl,
äußert sich das laut Risikoanalyse in `design.md` als sofort sichtbarer
HTTP 500 auf **allen** Requests unter `backend/public/` (nicht nur
Uploads) — in diesem Fall wäre die `LimitRequestBody`-Zeile aus der
deployten `.htaccess` zu entfernen und ausschließlich auf den
`.user.ini`/`php.ini`-Fallback (T02) zu vertrauen.

## Abgrenzung

- Keine Änderung an `deployment-templates/htaccess/backend-public.user.ini`,
  `backend-public.php.ini`, `build-deployment.sh`,
  `build-deployment-docker.sh` oder `DEPLOYMENT.md` — diese gehören zu
  T02 und lagen bereits (unabhängig von dieser Task) im Arbeitsverzeichnis
  vor; T01 hat sie nicht angefasst.
- Keine Änderung an `DogFormModal.vue` (T03) oder
  `backend/public/.htaccess.production` (T04).

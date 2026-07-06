# Notes: T04 — `backend/public/.htaccess.production` aufräumen

**Change:** `fix-dog-image-upload-shared-hosting`
**Agent:** dev-php
**Datum:** 2026-07-06

---

## Verifikation des Befunds (vor der Entscheidung)

- `backend/public/.htaccess.production` (39 Zeilen, vollständig gelesen)
  enthält u. a.:
  ```apache
  <IfModule mod_php8.c>
      php_value upload_max_filesize 50M
      php_value post_max_size 50M
      php_value max_execution_time 300
      php_value memory_limit 256M
  </IfModule>
  ```
  sowie eigene HTTPS-Redirect-, Authorization-Header- und
  Front-Controller-Rewrite-Regeln (redundant zu
  `deployment-templates/htaccess/root-post-install.htaccess` bzw.
  `backend-public.htaccess`) und eine `<FilesMatch "^\.">`-Deny-all-Regel.
- `grep -rn "htaccess.production" --include="*.sh" --include="*.md" .`
  (Repo-Root, außerhalb von `.git`) liefert **keinen Treffer** in
  `build-deployment.sh`, `build-deployment-docker.sh` oder `DEPLOYMENT.md`.
  Einzige Treffer außerhalb der Git-Historie lagen (vor der Entfernung) in
  den eigenen Change-Dokumenten dieses Changes
  (`openspec/changes/fix-dog-image-upload-shared-hosting/{proposal,design,
  tasks,verification,task-T01.notes}.md`) — das sind Prozess-/Audit-
  Dokumente des Changes selbst, keine Betriebs-/Deployment-Dokumentation,
  und liegen außerhalb meines Bearbeitungsbereichs (Spec-Dateien und
  fremde Task-Notes dürfen von `dev-php` laut Rollenbeschreibung nicht
  geändert werden).
- Damit bestätigt: Die Datei wird von **keinem** Build-Skript kopiert oder
  referenziert und hat **keinen Effekt auf reale Deployments** — der in
  `design.md`, Abschnitt 4, dokumentierte Befund ist korrekt.

## Entscheidung: Datei entfernen (nicht in Referenz-Template umbenennen)

Ich habe mich für die **erste Option** aus `tasks.md` T04 entschieden
(Entfernen statt Umbenennen in `.htaccess.production.example`).

### Begründung

1. **Vollständige Redundanz zu T01/T02:** Die einzige funktional relevante
   Direktive der Datei (`php_value upload_max_filesize`/`post_max_size`
   unter `<IfModule mod_php8.c>`) wird durch die in diesem Change bereits
   umgesetzten, tatsächlich ausgelieferten Mechanismen abgedeckt:
   `deployment-templates/htaccess/backend-public.htaccess` (T01,
   `LimitRequestBody` + `php_value`-`IfModule`-Block) und
   `deployment-templates/htaccess/backend-public.user.ini` +
   `backend-public.php.ini` (T02, PHP-FPM- und CGI/FastCGI-Fallback). Ein
   drittes, nie ausgeliefertes Artefakt mit abweichenden Werten (50M statt
   10M/12M) bietet keinen Mehrwert als "Referenz", sondern nur
   Verwechslungspotenzial.
2. **Kein Referenzwert für künftige Änderungen:** Ein Template ist nur dann
   sinnvoll, wenn es *aktiv* in einen Build- oder Entscheidungsprozess
   eingebunden ist (z. B. als Vorlage, aus der ein Skript kopiert). Diese
   Datei war das nie — sie wurde in Commit `5a8f185` einmalig angelegt und
   seitdem nie wieder angefasst oder referenziert (siehe `design.md`,
   Abschnitt 4). Ein bloßes Umbenennen in `.example` ändert daran nichts
   und fügt lediglich eine weitere nie gepflegte Datei hinzu, die bei einer
   künftigen Grenzwert-Änderung (z. B. Anhebung von Laravels
   `max:5120`-Validierung) still veraltet, weil niemand einen Grund hat,
   sie zu pflegen (YAGNI: es gibt aktuell keinen konkreten Anwendungsfall
   für ein "High-Traffic-Template", der über T01/T02 hinausgeht).
3. **Vermeidung von Fehlsuche-Risiko (der eigentliche Auslöser des
   Nebenfunds):** Der `design.md`-Befund warnt explizit davor, dass jemand
   die Datei künftig fälschlich für die aktive Konfiguration halten
   könnte. Ein Rename zu `.htaccess.production.example` mindert dieses
   Risiko zwar (der Dateiname macht "Beispiel" deutlicher), beseitigt es
   aber nicht vollständig — `.htaccess`-Dateien mit führendem Punkt werden
   von manchen Editoren/Tools weiterhin als "vermutlich aktiv" behandelt,
   und die abweichenden Werte (50M) könnten weiterhin fälschlich als
   "aktuell gültiges Limit" missverstanden werden, obwohl das echte Limit
   (T01/T02) 10M/12M ist. Vollständiges Entfernen beseitigt das Risiko
   restlos.
4. **Kein Informationsverlust:** Die Werte und der historische Kontext der
   Datei sind bereits vollständig in `design.md` (Abschnitt 4) dokumentiert
   und bleiben über die Git-Historie (Commit `5a8f185`) nachvollziehbar,
   falls jemand künftig nach dem Ursprung sucht. Ein Weiterleben als
   `.example`-Datei im Arbeitsverzeichnis bringt gegenüber dieser bereits
   vorhandenen Dokumentation keinen zusätzlichen Nutzen.
5. **KISS:** Ein Deployment-Verzeichnis mit einer aktiven `.htaccess`
   (implizit über die Build-Skripte erzeugt) und einer zweiten,
   niemals ausgeführten `.htaccess.production` nebenher ist unnötige
   Komplexität ohne Gegenwert. Weniger Dateien mit eindeutiger Bedeutung
   sind wartungsfreundlicher als ein weiteres "vielleicht relevantes"
   Artefakt.

### Warum nicht Option 2 (Umbenennen + Kommentieren)?

Option 2 wäre dann sinnvoll gewesen, wenn die 50M-Werte einen erkennbaren,
zukünftig wiederverwendbaren Anwendungsfall abgebildet hätten (z. B. einen
dokumentierten "High-Traffic"-Modus, den T01/T02 bewusst nicht abdecken).
Das ist nicht der Fall: T01/T02 decken exakt denselben Anwendungsfall
(Bild-Upload-Body-Size-Limit) bereits vollständig und mit begründeten,
niedrigeren Werten (10M/12M statt 50M, siehe `design.md`, Abschnitt 2,
Begründung der 2×-Laravel-Limit-Konvention) ab. Ein zusätzliches, nie
gebautes Referenz-Artefakt mit unbegründet höheren Werten hätte in Zukunft
eher zu der Frage geführt "warum 50M hier, aber 10M dort?" als zu
Klarheit beigetragen.

## Umgesetzte Änderung

- `backend/public/.htaccess.production` per `git rm` entfernt.

## Verifikation der Akzeptanzkriterien

- [x] Entscheidung getroffen und begründet (siehe oben): **Entfernen**.
- [x] `grep -rln ".htaccess.production" . --exclude-dir=.git` (Repo-Root,
      nach der Entfernung) liefert **keinen Treffer mehr in Build-Skripten
      oder Betriebsdokumentation** (`build-deployment.sh`,
      `build-deployment-docker.sh`, `DEPLOYMENT.md`: alle drei explizit
      geprüft, kein Treffer). Verbleibende Treffer liegen ausschließlich in
      den Prozess-/Audit-Dokumenten dieses Changes selbst
      (`openspec/changes/fix-dog-image-upload-shared-hosting/*.md`) — das
      sind bewusst erhaltene Aufzeichnungen der Entscheidungsfindung
      (Spec-Dateien und fremde Task-Notes liegen außerhalb des
      `dev-php`-Bearbeitungsbereichs für diese Task und wurden daher nicht
      verändert), keine "Dokumentation" im Sinne des Akzeptanzkriteriums
      (das sich auf Betriebs-/Deployment-Doku wie `DEPLOYMENT.md`
      bezieht).

## Pre-Flight-Checks (CLAUDE.md Abschnitt 7.1)

Ausgeführt in der Docker-Umgebung (`docker compose exec php ...`):

- `vendor/bin/pest --no-coverage`: **670 passed (2086 assertions)**,
  Duration 25.32s — keine Regression (T04 entfernt eine reine
  `.htaccess`-Konfigurationsdatei, keinen PHP-Anwendungscode und keinen von
  Tests referenzierten Pfad).
- `vendor/bin/pint --test`: meldet dieselben vorbestehenden
  Formatierungsabweichungen wie bereits in `task-T02.notes.md`
  dokumentiert (`database/factories/*`, `database/migrations/*`,
  `tests/*` u. a.). Keine dieser Dateien wurde von T04 angefasst.
- `composer stan` / `composer compat-check`: **nicht ausführbar**, aus
  demselben bereits in `task-T02.notes.md` dokumentierten Grund:
  `backend/composer.json` (die im Docker-Container gemountete Datei)
  definiert diese Scripts nicht, und die zugehörigen Dev-Packages
  (`larastan/larastan`, `phpcompatibility/php-compatibility`,
  `squizlabs/php_codesniffer`) fehlen dort in `require-dev`. Diese Scripts
  existieren nur im Projekt-Root-`composer.json`. Vorbestehende
  Repo-Inkonsistenz, nicht Teil des Scopes von T04. Da T04 ausschließlich
  eine `.htaccess`-Datei entfernt (kein PHP-Code, keine SQL/Migration), ist
  PHP-8.2-Kompatibilität (Abschnitt 4.1 der `CLAUDE.md`) ohnehin nicht
  einschlägig.
- `npm run lint`/`npm run test`/`npm run build`: nicht Teil des
  Pre-Flight für diesen Task, da T04 keine Frontend-Dateien berührt.

## Abweichungen von der Task-Beschreibung

Keine inhaltlichen Abweichungen. Die Task war explizit als
Entscheidungsaufgabe mit zwei gleichwertigen Optionen formuliert
("Entscheidung liegt beim User/Skeptiker, nicht bindend vorgegeben"); ich
habe mich für "Entfernen" entschieden und dies oben begründet, wie von der
Task gefordert.

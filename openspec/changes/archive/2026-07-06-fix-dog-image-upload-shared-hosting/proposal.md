# Proposal: fix-dog-image-upload-shared-hosting

**Change-ID:** fix-dog-image-upload-shared-hosting
**Typ:** Bug-Fix
**Priorität:** hoch (Produktion betroffen)
**Datum:** 2026-07-06
**Umgebung:** bestätigt durch User — **Produktion, Shared Hosting (Apache)**

---

## Problem-Statement

Kunden können beim Bearbeiten eines Hundes ein Profilbild hochladen
(`DogFormModal.vue` → `POST /api/v1/dogs/{id}/upload-image`). In der
**Produktionsumgebung auf Shared Hosting** wird das Bild nicht dauerhaft
angezeigt und vermutlich nicht gespeichert. Lokal (Docker) funktioniert der
Upload nachweislich (`storage/app/public/dog-images/` enthält historische
Uploads für `dog_1`, siehe Triage).

Es gibt bereits einen fast identischen, abgenommenen Bug-Fix vom 2026-05-13
(`openspec/changes/archive/2026-05-13-dog-image-upload-bug/`): Dort fehlte
`client_max_body_size` in Nginx für die **lokale Docker-Umgebung** — Bilder
> 1 MB wurden mit HTTP 413 abgelehnt, bevor Laravel (und damit CORS)
überhaupt lief, was im Browser wie ein CORS-Fehler aussah. Der damalige
Fix (`docker/nginx/conf.d/default.conf:15`, `client_max_body_size 10M;`)
betrifft **ausschließlich Nginx** und damit **nicht** Shared Hosting, das
Apache verwendet. Das damalige Team hat dies explizit als Nicht-Scope
vermerkt (`archive/2026-05-13-dog-image-upload-bug/design.md:73-87`).

## Root-Cause-Analyse (verifiziert)

### Ursache 1 (Hauptverdacht, bestätigt durch Code-Prüfung): Kein Apache-Äquivalent zu `client_max_body_size`

Geprüfte Dateien:

- `deployment-templates/htaccess/backend-public.htaccess` — enthält
  `mod_negotiation`/`mod_rewrite`-Regeln und Security-Header, **keine**
  `LimitRequestBody`-Direktive, **keine** `php_value upload_max_filesize`/
  `post_max_size`-Overrides.
- `deployment-templates/htaccess/backend-root.htaccess` — enthält nur
  `Options -Indexes`.
- `build-deployment.sh:244` / `build-deployment-docker.sh:340` kopieren
  genau `backend-public.htaccess` nach `backend/public/.htaccess` — das ist
  die Datei, die auf Shared Hosting tatsächlich für alle `/api/v1/...`-
  Requests (inkl. `upload-image`) wirksam wird (Requests werden laut
  `deployment-templates/htaccess/root-post-install.htaccess:26`
  `RewriteRule ^api/(.*)$ backend/public/index.php [L,QSA]` dorthin
  weitergeleitet).
- **Nebenfund:** `backend/public/.htaccess.production` existiert im Repo und
  enthält bereits `php_value upload_max_filesize 50M` /
  `post_max_size 50M`-Overrides — diese Datei wird jedoch **von keinem
  Build-Skript referenziert** (`grep` über `build-deployment.sh` und
  `build-deployment-docker.sh` liefert keinen Treffer für
  `.htaccess.production`). Sie ist toter Code, der beim Debuggen in die
  Irre führen kann (siehe `design.md`, Abschnitt "Nebenfund").

Laravels Validierungsregel bleibt unverändert bei `max:5120` (5 MB,
`backend/app/Http/Controllers/Api/DogController.php:185`). Ohne
Apache/PHP-seitiges Limit ≥ diesem Wert werden Uploads oberhalb des
Hoster-Defaults (typischerweise 2–8 MB je nach Shared-Hosting-Anbieter)
entweder von Apache/PHP verworfen (`$_FILES` leer,
`UPLOAD_ERR_INI_SIZE`) oder mit einer für den Nutzer unklaren Fehlermeldung
abgelehnt.

### Ursache 2 (unabhängig bestätigt, immer relevant): Frontend verschluckt Upload-Fehler

`frontend/src/components/DogFormModal.vue:406-421` (`handleSubmit`): Der
Bild-Upload läuft als separater Request **nach** dem Speichern der
Stammdaten. Schlägt `POST /api/v1/dogs/{id}/upload-image` fehl (z. B. durch
Ursache 1, aber auch durch andere Fehler wie HTTP 422/500), wird der Fehler
nur per `handleApiError(imgErr, imgError)` als Toast angezeigt
(Zeile 416), aber direkt danach `emit('saved'); closeModal()`
(Zeilen 420-421) ausgeführt — das Modal schließt, als wäre der Upload
erfolgreich gewesen. Ein Nutzer, der den (kurzlebigen) Toast übersieht, hat
keinen Hinweis darauf, dass speziell das Bild nicht gespeichert wurde. Dies
passt exakt zur gemeldeten Beobachtung "Bild wird nicht angezeigt,
vermutlich nicht gespeichert" — unabhängig davon, welche der beiden
Ursachen im konkreten Vorfall zutraf.

### Als strukturell korrekt verifiziert (kein Fix nötig)

- `backend/app/Http/Controllers/Api/DogController.php:180-202`
  (`uploadImage()`) — Logik ist korrekt, `profile_image` ist in
  `Dog::$fillable` (`backend/app/Models/Dog.php:65`).
- `backend/app/Http/Resources/DogResource.php:42-44`
  (`profileImageUrl`) — korrekt.
- Anzeige-Komponenten (`DogsView.vue:42-48`, `DogFormModal.vue:36-52`) —
  binden `profileImageUrl` korrekt per `v-if`.
- Migration `2026_05_04_100000_add_profile_image_to_dogs_table` ist bereits
  in `main` gemergt (Teil des archivierten Changes) — **keine neue
  Migration nötig für diesen Change**.

---

## Ziel

1. Ein hochgeladenes Profilbild wird in **Produktion (Shared Hosting)**
   dauerhaft gespeichert und angezeigt, für Dateien bis zur Laravel-
   Validierungsgrenze von 5 MB (`max:5120`), analog zum bereits gefixten
   Verhalten in der lokalen Docker-Umgebung.
2. Schlägt der Bild-Upload dennoch fehl (z. B. Netzwerkfehler,
   serverseitige Ablehnung), erfährt der Nutzer das **unübersehbar** — das
   Formular schließt nicht kommentarlos, als wäre alles erfolgreich
   gewesen.

## Proposed Solution

### Fix A (Pflicht, Konfiguration, kein DB-Bezug): Apache/PHP-Body-Size-Limit für Shared Hosting

Apache-Äquivalent zu `client_max_body_size` ergänzen:
`LimitRequestBody` in `deployment-templates/htaccess/backend-public.htaccess`
sowie `php_value upload_max_filesize`/`post_max_size`-Fallback (wirkt nur
unter `mod_php`) plus ein `.user.ini`-Template (PHP-Core-Mechanismus, greift
bei PHP-FPM und den meisten CGI-Setups). Da der genaue PHP-Ausführungsmodus
des Ziel-Hosters nicht bekannt ist (User-Rückfrage vom 2026-07-06), wird
**zusätzlich** ein `php.ini`-Template als Fallback für CGI/FastCGI-Wrapper-
Setups ausgeliefert, bei denen `.user.ini` nicht ausgewertet wird — beide
Mechanismen sind additiv und enthalten identische Werte (kein Konflikt,
falls beide greifen). Build-Skripte (`build-deployment.sh`,
`build-deployment-docker.sh`) müssen beide neuen Dateien mit ausliefern.
Details in `design.md`.

### Fix B (Pflicht, Frontend): Fehlerbehandlung in `DogFormModal.vue`

`handleSubmit` darf das Modal nicht schließen bzw. `saved` als vollen
Erfolg emittieren, wenn der Bild-Upload-Teilschritt fehlschlägt. Details in
`design.md`.

### Optional (nicht blockierend): Aufräumen von `backend/public/.htaccess.production`

Diese Datei ist tot (kein Build-Skript referenziert sie) und enthält
scheinbar funktionierende, aber nie ausgelieferte Overrides — Risiko für
zukünftige Fehldiagnosen. Empfehlung: entfernen oder in den Build-Prozess
integrieren. Siehe `tasks.md`, T04 ("könnte").

---

## Out of Scope

- Änderung der Laravel-Validierungsregel `max:5120` — Wert bleibt wie er
  ist, siehe bereits archivierte Entscheidung.
- Nginx-Konfiguration (`docker/nginx/conf.d/default.conf`) — bereits gefixt,
  nicht Teil dieses Changes.
- Automatisiertes Nachziehen der Hoster-seitigen PHP-Konfiguration über das
  Hoster-Panel (außerhalb des Repos) — wird dokumentiert, nicht
  automatisiert.
- Bildkompression/Thumbnail-Generierung — nicht angefordert (YAGNI).
- Vereinheitlichung mit dem unabhängigen `training-attachments`-Upload-Flow
  (`FILE-UPLOAD-SYSTEM.md`) — anderer Feature-Bereich, nicht Teil dieses
  Changes.

## Referenzen

- Aktive Spec: `openspec/specs/dog-image-upload/spec.md`
- Vorheriger Change: `openspec/changes/archive/2026-05-13-dog-image-upload-bug/`
- Triage: `openspec/triage/20260706125033-dog-profile-image-not-persisted.md`

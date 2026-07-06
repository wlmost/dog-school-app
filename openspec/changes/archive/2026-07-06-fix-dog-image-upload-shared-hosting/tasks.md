# Tasks für fix-dog-image-upload-shared-hosting

## T01: Apache `LimitRequestBody` + `php_value`-Fallback in `backend-public.htaccess`

- **Agent:** dev-php
- **Dateien:** `deployment-templates/htaccess/backend-public.htaccess`
- **Abhängigkeiten:** keine
- **Priorität:** Pflicht

### Beschreibung

`deployment-templates/htaccess/backend-public.htaccess` fehlt jedes
Apache-Äquivalent zu Nginx' `client_max_body_size` (siehe
`docker/nginx/conf.d/default.conf`, bereits gefixt im archivierten Change
`2026-05-13-dog-image-upload-bug`). Diese Datei wird laut
`build-deployment.sh:244` und `build-deployment-docker.sh:340` nach
`backend/public/.htaccess` kopiert und wirkt damit für alle über
`RewriteRule ^api/(.*)$ backend/public/index.php` weitergeleiteten
API-Requests, inklusive `POST /api/v1/dogs/{id}/upload-image`.

Ergänze am Anfang der Datei (vor dem bestehenden
`<IfModule mod_negotiation.c>`-Block):

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

Siehe `design.md`, Abschnitt 2, für die vollständige Begründung (Wahl von
10 MB als 2× Laravel-Limit, Wahl von 12M `post_max_size`, Risiko bei
restriktivem `AllowOverride`).

**Kein anderer Inhalt der Datei darf verändert werden** (bestehende
Rewrite-Regeln und Security-Header bleiben unangetastet).

### Akzeptanzkriterien

- [x] `backend-public.htaccess` enthält `LimitRequestBody 10485760` sowie
      die beiden `php_value`-`IfModule`-Blöcke wie oben spezifiziert.
- [x] Bestehender Inhalt der Datei (Rewrite-Regeln, Security-Header) ist
      unverändert.
- [x] `composer qa` (bzw. mindestens `composer lint`/`composer stan`) läuft
      weiterhin fehlerfrei — diese Datei ist kein PHP-Code, aber die
      Pre-Flight-Suite darf nicht durch einen Syntaxfehler in der
      `.htaccess` indirekt brechen (z. B. falls ein Test die Datei
      einliest).
- [x] Manueller/dokumentierter Hinweis in `task-T01.notes.md`, dass ein
      echter Shared-Hosting-Smoke-Test (Upload eines Bildes zwischen 2 MB
      und 8 MB) nach Deployment nötig ist, da Apache-`AllowOverride`-
      Verhalten nicht aus dem Repo verifizierbar ist (siehe `design.md`,
      Abschnitt 2, "Offenes Risiko").

---

## T02: `.user.ini`- und `php.ini`-Templates für PHP-FPM- und CGI/FastCGI-Hosts + Build-Skripte + Doku

- **Agent:** dev-php
- **Dateien:** `deployment-templates/htaccess/backend-public.user.ini` (neu),
  `deployment-templates/htaccess/backend-public.php.ini` (neu),
  `build-deployment.sh`, `build-deployment-docker.sh`, `DEPLOYMENT.md`
- **Abhängigkeiten:** keine (thematisch verwandt mit T01, aber technisch unabhängig)
- **Priorität:** Pflicht

### Beschreibung

> **Erweiterung nach User-Feedback (2026-07-06, kein Skeptiker-Befund,
> direkte User-Entscheidung):** Der genaue PHP-Ausführungsmodus des
> Ziel-Hosters (PHP-FPM vs. klassisches CGI/FastCGI) ist unbekannt und aus
> dem Repo nicht verifizierbar. Der User hat entschieden, **beide**
> Mechanismen auszuliefern: `.user.ini` (PHP-Core-Mechanismus, greift bei
> PHP-FPM und den meisten CGI-Setups) **und zusätzlich** `php.ini` (Fallback
> für CGI/FastCGI-Wrapper-Setups, bei denen `.user.ini` nicht ausgewertet
> wird, siehe `design.md`, Abschnitt 2, für die vollständige Begründung
> inkl. "warum das nicht schadet").

`.htaccess`-`php_value`-Direktiven (T01) wirken nur unter `mod_php`. Viele
moderne Shared-Hosting-Pakete nutzen PHP-FPM oder klassisches CGI/FastCGI
(siehe `DEPLOYMENT.md`, Abschnitt "PHP-FPM optimieren"), wo `.htaccess`-
`php_value` ignoriert wird. PHP liest dort `.user.ini`-Dateien pro
Verzeichnis (PHP-Core-Mechanismus); manche CGI/FastCGI-Wrapper-Setups
(z. B. cPanel-"Custom php.ini", verbreitete Panel-Konvention, siehe
`design.md`) laden zusätzlich/stattdessen eine Datei namens `php.ini` aus
demselben Verzeichnis.

1. Neue Datei `deployment-templates/htaccess/backend-public.user.ini`:
   ```ini
   upload_max_filesize = 10M
   post_max_size = 12M
   ```
2. Neue Datei `deployment-templates/htaccess/backend-public.php.ini` (gleiche
   Werte, andere Datei — Namensanalogie zu `backend-public.user.ini`):
   ```ini
   upload_max_filesize = 10M
   post_max_size = 12M
   ```
3. In `build-deployment.sh` (Funktion `copy_htaccess_files`, ca. Zeile 227-264)
   und `build-deployment-docker.sh` (Funktion `copy_htaccess_files`, ca.
   Zeile 325-355) ergänzen:
   ```bash
   cp "$template_dir/backend-public.user.ini" "$BUILD_DIR/backend/public/.user.ini" \
       || error_exit "Failed to copy backend/public .user.ini"
   cp "$template_dir/backend-public.php.ini" "$BUILD_DIR/backend/public/php.ini" \
       || error_exit "Failed to copy backend/public php.ini"
   ```
4. Die jeweilige `verify_htaccess_files`-Prüfliste um
   `"$BUILD_DIR/backend/public/.user.ini"` **und**
   `"$BUILD_DIR/backend/public/php.ini"` erweitern, damit ein fehlendes File
   den Build sichtbar abbricht.
5. `DEPLOYMENT.md` um einen kurzen Abschnitt ergänzen: Hinweis, dass **beide**
   Mechanismen ausgeliefert werden (`.user.ini` für PHP-FPM-Hosts, `php.ini`
   als zusätzlicher Fallback für CGI/FastCGI-Hosts), da der genaue
   PHP-Ausführungsmodus des Ziel-Hosters nicht bekannt ist und beide
   Mechanismen rein additiv sind (identische Werte in beiden Dateien, kein
   Konflikt, falls beide greifen). Zusätzlich: Hinweis, dass nach dem
   Deployment geprüft werden sollte, dass `upload_max_filesize`/
   `post_max_size` effektiv ≥ 10 MB/12 MB sind (Hoster-Panel oder temporäre
   `phpinfo()`-Seite), und dass `.user.ini`-Änderungen bei PHP-FPM erst nach
   `user_ini.cache_ttl` (Default 300s) aktiv werden.

### Akzeptanzkriterien

- [x] `deployment-templates/htaccess/backend-public.user.ini` existiert mit
      exakt den beiden oben genannten Zeilen.
- [x] `deployment-templates/htaccess/backend-public.php.ini` existiert mit
      exakt den beiden oben genannten Zeilen (identisch zu `.user.ini`).
- [x] `build-deployment.sh` und `build-deployment-docker.sh` kopieren
      **beide** Dateien (`.user.ini` nach
      `$BUILD_DIR/backend/public/.user.ini`, `php.ini` nach
      `$BUILD_DIR/backend/public/php.ini`) und verifizieren beider Existenz
      in `verify_htaccess_files`.
- [x] `DEPLOYMENT.md` enthält den beschriebenen Hinweis zu beiden
      Mechanismen, Body-Size-Limits und `.user.ini`-Cache-Verzögerung.
- [x] Lokaler Probelauf von `./build-deployment-docker.sh` (oder
      `./build-deployment.sh`, je nach verfügbarer Umgebung) erzeugt ein
      Archiv, das sowohl `backend/public/.user.ini` als auch
      `backend/public/php.ini` enthält.

---

## T03: `DogFormModal.vue` — Bild-Upload-Fehler nicht mehr verschlucken

- **Agent:** dev-javascript
- **Dateien:** `frontend/src/components/DogFormModal.vue`
- **Abhängigkeiten:** keine
- **Priorität:** Pflicht

### Beschreibung

> **Korrektur nach Skeptiker-Befund:** Die ursprüngliche Fassung dieser
> Aufgabe ging davon aus, `emit('saved')` könne bei einem Bild-Upload-
> Fehler weiterhin feuern, solange nur der interne `closeModal()`-Aufruf
> unterbleibt. Das ist **widerlegt**: `DogsView.vue:95-100` bindet
> `DogFormModal` als kontrollierte Komponente (`:is-open="showFormModal"`,
> `@saved="handleDogSaved"`), und `handleDogSaved`
> (`DogsView.vue:189-192`) ruft bei **jedem** `@saved`-Event unbedingt
> `closeFormModal()` auf — unabhängig vom internen Zustand von
> `DogFormModal.vue`. Details und Begründung der korrigierten Lösung:
> siehe `design.md`, Abschnitt 3.

`handleSubmit` (Zeilen 372-441, insbesondere 406-421) schließt das Modal
(`emit('saved'); closeModal()`) auch dann, wenn der separate Bild-Upload-
Request (`POST /api/v1/dogs/{id}/upload-image`) fehlgeschlagen ist — der
Fehler wird nur als (vergänglicher) Toast angezeigt.

Nutze die bereits vorhandene Infrastruktur:

- `error` (Zeile 232) wird im Template bereits per `v-if="error"` als
  dauerhafter roter Banner gerendert (Zeilen 166-168).
- Der bestehende Stammdaten-Fehlerpfad (äußerer `catch`-Block, Zeilen
  422-437) löst bei einem Validierungsfehler **weder** `emit('saved')`
  **noch** `closeModal()` aus — das Modal bleibt allein dadurch offen,
  dass die Elternkomponente kein Event empfängt. Dieses bereits
  etablierte Muster wird für den Bild-Upload-Fehlerpfad konsistent
  wiederverwendet (siehe `design.md`, Abschnitt 3, "Entscheidung").

**Änderungen:**

1. Im `catch`-Block des Bild-Uploads (aktuell Zeilen 414-417): zusätzlich
   zum Toast `error.value` setzen (z. B. "Hund wurde gespeichert, aber das
   Profilbild konnte nicht hochgeladen werden: `<imgError>`. Bitte erneut
   versuchen."), **und danach den Ausführungspfad beenden (`return`),
   ohne `emit('saved')` oder `closeModal()` aufzurufen.**
2. Nur wenn kein Bild ausgewählt wurde oder der Bild-Upload erfolgreich
   war, werden `emit('saved')` und `closeModal()` wie bisher ausgeführt.
   **Wichtig:** `emit('saved')` darf beim Bild-Upload-Fehlerfall **nicht**
   ausgelöst werden (Änderung gegenüber der ursprünglichen Planung) —
   weder `emit('saved')` noch `closeModal()` dürfen in diesem Pfad
   aufgerufen werden, da die Elternkomponente (`DogsView.vue`) auf
   **beide** Events unbedingt mit Schließen des Modals reagiert.
3. **Kein doppeltes Anlegen** eines Hundes, wenn im Create-Flow
   (`!props.dog`) der erste Request erfolgreich war, aber der Bild-Upload
   scheiterte und der Nutzer erneut auf "Speichern" klickt (das Modal
   bleibt laut Punkt 1/2 offen). Siehe `design.md`, Abschnitt 3, für zwei
   akzeptable Lösungsansätze (gespeicherten Hund lokal merken und Retry
   nur auf Bild-Upload beschränken, ODER dedizierter Retry-Button für den
   Bild-Upload allein). Bei erfolgreichem Retry **müssen** `emit('saved')`
   und `closeModal()` ausgelöst werden (analog zu Punkt 2), sonst bleibt
   das Modal auch nach erfolgreichem Bild-Upload fälschlich offen. Die
   konkrete Umsetzung liegt beim Entwickler-Agenten — entscheidend ist
   das in den Akzeptanzkriterien beschriebene Verhalten.
4. Der bestehende "Abbrechen"-Button (Zeile 172-174, `@click="closeModal"`)
   bleibt unverändert nutzbar: Klickt der Nutzer nach einem gescheiterten
   Bild-Upload auf "Abbrechen", schließt sich das Modal wie gewohnt (kein
   neuer Button/keine neue Logik nötig, YAGNI). Die Liste in
   `DogsView.vue` zeigt den bereits gespeicherten Hund dann beim nächsten
   `loadDogs()`-Aufruf (Suche, Seitenwechsel, Reload) korrekt an — dies
   ist ein bewusster, dokumentierter Kompromiss (siehe `design.md`,
   Abschnitt 3 und 5), kein Datenverlust.
5. **Keine Änderung an `DogsView.vue` nötig** — dieser Task bleibt auf
   `frontend/src/components/DogFormModal.vue` beschränkt (siehe
   `design.md`, Abschnitt 3, Begründung der Lösungswahl).

### Akzeptanzkriterien

- [x] Schlägt der Bild-Upload fehl: Modal bleibt offen (verifiziert über
      die reale `DogsView.vue`-Einbindung, nicht nur isoliert in
      `DogFormModal.vue`), ein dauerhaft sichtbarer Fehlerhinweis (roter
      Banner, nicht nur Toast) erscheint.
- [x] Schlägt der Bild-Upload fehl: Weder `emit('saved')` noch
      `emit('close')`/`closeModal()` werden ausgelöst (per Komponententest
      nachweisbar, z. B. über `wrapper.emitted()`).
- [x] Schlägt der Bild-Upload fehl: Es wird **kein zweiter Hund-Datensatz**
      angelegt, wenn der Nutzer danach erneut auf "Speichern" klickt
      (Create-Flow).
- [x] Nutzer kann nach einem gescheiterten Bild-Upload das Bild erneut
      hochladen, ohne das Modal schließen und neu öffnen zu müssen; bei
      erfolgreichem Retry wird `emit('saved')` ausgelöst und das Modal
      schließt.
- [x] Nutzer kann nach einem gescheiterten Bild-Upload über den
      bestehenden "Abbrechen"-Button das Modal schließen, ohne dass ein
      zweiter Hund angelegt wird.
- [x] Erfolgreicher Bild-Upload (Regressionstest): Modal schließt wie
      bisher, Toast "Hund aktualisiert"/"Hund erstellt" erscheint,
      `emit('saved')` wird ausgelöst.
- [x] Erfolgreicher Stammdaten-Save ohne ausgewähltes Bild (Regressionstest):
      unverändertes Verhalten.
- [x] `npm run lint`, `npm run test`, `npm run build` laufen ohne neue
      Fehler/Warnings (Projekt-Pre-Flight für Frontend-Tasks). Hinweis:
      `npm run lint` existiert nicht in `frontend/package.json` (siehe
      `task-T03.notes.md`) — `npm run test` (128 Tests) und `npm run build`
      (`vue-tsc -b && vite build`) liefen fehlerfrei.

---

## T04 (könnte, nicht blockierend): `backend/public/.htaccess.production` aufräumen

- **Agent:** dev-php
- **Dateien:** `backend/public/.htaccess.production`
- **Abhängigkeiten:** T01, T02 (sollte nach den echten Fixes entschieden werden, um Redundanz/Widerspruch zu vermeiden)
- **Priorität:** könnte (nice-to-have, nicht Ziel-blockierend)

### Beschreibung

`backend/public/.htaccess.production` enthält bereits `php_value`-Overrides
(`upload_max_filesize 50M`, `post_max_size 50M`, u. a.), wird aber von
keinem Build-Skript (`build-deployment.sh`, `build-deployment-docker.sh`)
referenziert oder kopiert — sie hat keinen Effekt auf reale Deployments und
kann bei künftiger Fehlersuche in die Irre führen (siehe `design.md`,
Abschnitt 4).

**Optionen (Entscheidung liegt beim User/Skeptiker, nicht bindend
vorgegeben):**
- Datei entfernen (da redundant zu T01/T02 und nie ausgeliefert), oder
- Datei als benanntes Referenz-Template behalten (z. B.
  `backend/public/.htaccess.production.example`) mit Kommentar, dass sie
  nicht automatisch eingebunden wird.

### Akzeptanzkriterien

- [x] Entscheidung (Entfernen oder Umbenennen+Kommentieren) getroffen und
      in `task-T04.notes.md` begründet.
- [x] Falls entfernt: kein Build-Skript und keine Dokumentation referenziert
      die Datei mehr (`grep -rn ".htaccess.production"` liefert keinen
      Treffer mehr außerhalb der Git-Historie).

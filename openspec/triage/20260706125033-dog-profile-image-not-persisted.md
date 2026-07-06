# Triage: dog-profile-image-not-persisted

**Pfad:** klein
**Geschätzter Umfang:** vermutlich 1–3 Dateien, PHP (Backend-Konfiguration/Controller) und/oder TypeScript/Vue (`DogFormModal.vue`)
**Risiko:** niedrig — betrifft ausschließlich Datei-Upload/Anzeige, keine Auth-, Datenmodell- oder Migrationsänderung absehbar
**Klarheit:** mehrdeutig — das gewünschte Verhalten ist klar, die Root Cause ist es nicht (siehe unten)

## Anforderung (Zusammenfassung)

Als Kunde soll beim Bearbeiten eines Hundes ein hochgeladenes Profilbild dauerhaft
gespeichert und im vorgesehenen Bereich (Formular-Vorschau, Karten-/Detailansicht)
angezeigt werden. Aktuell wird das Bild nach dem Speichern nicht angezeigt; laut
Meldung ist unklar, ob es überhaupt persistiert wird.

## Wichtiger Befund: (fast) identischer Bug bereits am 2026-05-13 gefixt

Es existiert bereits ein archivierter, abgenommener Change für ein sehr ähnlich
klingendes Symptom:

- Triage: `openspec/triage/20260513120000-dog-image-upload-bug.md`
- Archiv: `openspec/changes/archive/2026-05-13-dog-image-upload-bug/` (inkl. `acceptance.md`, Status „abgenommen")
- Aktive Spec: `openspec/specs/dog-image-upload/spec.md`

**Damaliger Root Cause:** `docker/nginx/conf.d/default.conf` hatte kein
`client_max_body_size`-Direktiv; Nginx-Default (1 MB) lag unter Laravels
Validierungslimit (5 MB, `max:5120` in `backend/app/Http/Controllers/Api/DogController.php:185`).
Bilder > 1 MB wurden von Nginx mit HTTP 413 *vor* Laravel abgelehnt, wodurch die
CORS-Middleware nie lief → im Browser erschien fälschlich ein CORS-Fehler statt 413.

**Fix (bereits in `main`):** `client_max_body_size 10M;` wurde ergänzt
(`docker/nginx/conf.d/default.conf:12`, Commit `4f69579`, gemerged über `2fda24c`).
Ich habe verifiziert: **Der Fix ist aktuell im Code vorhanden** — die Direktive
steht nach wie vor in `docker/nginx/conf.d/default.conf`.

**Zusatzbefund von damals:** Ein begleitender HTTP-500-Fehler war keine
Code-Ursache, sondern eine nicht ausgeführte Migration
(`2026_05_04_100000_add_profile_image_to_dogs_table`) auf der lokalen Dev-DB.

## Warum trotzdem "mehrdeutig" statt "gleicher Bug, einfach nochmal fixen"

Da der Nginx-Fix von damals **nur `docker/nginx/conf.d/default.conf` betrifft**
(lokale Docker-Entwicklungsumgebung), und das damalige Team explizit vermerkt hat:

> „Kein zweiter Patch-Kandidat: `deployment-templates/` enthält ausschließlich
> `.htaccess`-Dateien für Apache-Shared-Hosting. Nginx-Limits auf Shared Hosting
> werden vom Hoster gesteuert und sind nicht Teil dieses Changes."

— ist der Fix **nicht** auf Demo/Produktion (Shared Hosting, Apache) übertragen worden.
Ich habe geprüft: Keine der `.htaccess`-Dateien unter `deployment-templates/htaccess/`
enthält eine `LimitRequestBody`-Direktive, und es gibt kein
PHP-`upload_max_filesize`/`post_max_size`-Override für die Shared-Hosting-Umgebung
im Repo (nur `docker/php/php.ini` für lokale Entwicklung, mit `100M`).

Das aktuelle Bug-Ticket nennt die Umgebung nicht. Es ist daher unklar, ob:

1. es sich um eine **Regression** des bereits gefixten lokalen Docker-Bugs handelt
   (z. B. durch `docker compose down -v` / Neuaufbau ohne den gemergten Stand,
   oder eine vergessene `docker compose restart nginx`/fehlende Migration), oder
2. es sich um das **gleiche Fehlerbild in einer anderen Umgebung** (Demo/Produktion,
   Apache statt Nginx) handelt, wofür der damalige Fix nie griff, oder
3. es sich um eine **neue, unabhängige Ursache** handelt.

## Zusätzlicher, unabhängig vom obigen Punkt bestätigter Frontend-Befund

Unabhängig von der Root-Cause-Frage habe ich im aktuellen Code eine echte
Fehlerbehandlungslücke gefunden, die zum gemeldeten Symptom ("Bild wird nicht
angezeigt, vermutlich nicht gespeichert") passt:

- `frontend/src/components/DogFormModal.vue:406–421` (`handleSubmit`): Der
  Bild-Upload läuft in einem **separaten** `POST /api/v1/dogs/{id}/upload-image`
  *nach* dem Speichern der Stammdaten (PUT/POST auf `/api/v1/dogs/{id}`). Schlägt
  dieser zweite Request fehl (z. B. HTTP 413/422/500), wird der Fehler nur via
  `handleApiError(imgErr, imgError)` als Toast angezeigt
  (`frontend/src/components/DogFormModal.vue:414–417`) — **das Modal schließt
  trotzdem** (`emit('saved'); closeModal()` direkt danach, Zeilen 420–421), als
  wäre alles inklusive Bild erfolgreich gewesen. Ein Nutzer, der den Toast
  übersieht (z. B. weil er schnell verschwindet), hat keinen Hinweis darauf,
  dass speziell der Bild-Upload gescheitert ist — passt exakt zur Formulierung
  "vermutlich auch nicht gespeichert".

- Backend-seitig (`backend/app/Http/Controllers/Api/DogController.php:180–202`,
  `uploadImage()`) und Anzeige-seitig
  (`backend/app/Http/Resources/DogResource.php:42–44`,
  `frontend/src/views/dogs/DogsView.vue:42–48`,
  `frontend/src/components/DogFormModal.vue:36–52`) sehen strukturell korrekt aus:
  `profile_image` ist `fillable` (`backend/app/Models/Dog.php:52`), Storage-Disk
  `public` ist konfiguriert (`backend/config/filesystems.php`), `Storage::disk('public')->url()`
  wird für `profileImageUrl` genutzt, und beide Anzeigekomponenten binden
  `dog.profileImageUrl` korrekt per `v-if`. Der lokale `public/storage`-Symlink
  existiert (`backend/public/storage -> .../storage/app/public`), und
  `storage/app/public/dog-images/` enthält bereits historische Uploads für `dog_1`
  — der Upload-Mechanismus hat in der lokalen Dev-Umgebung also grundsätzlich
  schon funktioniert.

- **Ungeprüfte Referenz:** Ob auf der Demo-/Produktions-Instanz (Shared Hosting)
  `php artisan storage:link` ausgeführt wurde und ob dort ein äquivalentes
  Body-Size-Limit existiert, kann ich aus dem Repo nicht feststellen — das ist
  Server-Konfiguration außerhalb des Codes.

## Rückfragen an den User

- In welcher Umgebung tritt der Fehler auf: lokale Docker-Entwicklung, Demo
  (Shared Hosting) oder Produktion (Shared Hosting)?
- Gibt es eine Fehlermeldung im Browser (Netzwerk-Tab: HTTP-Statuscode des
  Requests an `/api/v1/dogs/{id}/upload-image`, z. B. 413/422/500) oder eine
  Toast-Meldung, die evtl. schnell wieder verschwunden ist?
- Wie groß war die hochgeladene Bilddatei ungefähr?
- Wurde die lokale Docker-Umgebung seit dem 13.05.2026 (Merge von
  `change/dog-image-upload-bug`) neu aufgesetzt (`docker compose down -v` o. Ä.)
  und wurde danach `php artisan migrate` erneut ausgeführt?

## Empfohlene nächste Aktion

`@architect` (Modus A) erstellt einen kleinen Change (`fix-dog-image-upload-persistence`
o. Ä.) mit mindestens folgenden Tasks, sobald die Rückfragen beantwortet sind:

1. **Pflicht (dev-javascript):** Fehlerbehandlung in
   `frontend/src/components/DogFormModal.vue::handleSubmit` korrigieren, sodass
   ein fehlgeschlagener Bild-Upload das Modal **nicht** wie ein Erfolg schließt
   (z. B. `error.value` setzen statt nur Toast, Modal offen lassen, Bild erneut
   anbietbar machen).
2. **Bedingt, je nach Antwort auf Rückfrage „Umgebung":**
   - Falls Demo/Produktion betroffen: neuen Task für Apache-Shared-Hosting-Äquivalent
     zu `client_max_body_size` (`LimitRequestBody` in
     `deployment-templates/htaccess/backend-public.htaccess` bzw. `backend-root.htaccess`)
     sowie PHP-`upload_max_filesize`/`post_max_size` für die Ziel-PHP-Version (8.2–8.4)
     prüfen/dokumentieren.
   - Falls lokale Docker-Regression: kein Code-Fix nötig, nur Setup-Dokumentation
     (`php artisan migrate` nach Neuaufbau) ergänzen — ggf. sogar "trivial".
3. Kein Bedarf für Skeptiker-Eskalation auf "standard/groß" — Umfang bleibt klein
   in allen Szenarien.

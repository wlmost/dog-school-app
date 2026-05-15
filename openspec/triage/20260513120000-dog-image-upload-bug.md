# Triage: dog-image-upload-bug

**Datum:** 2026-05-13
**Typ:** bug
**Pfad:** klein
**Priorität:** mittel — Bild-Upload ist sichtbare Nutzerfunktion; Kernfunktionen (Hunde anlegen/bearbeiten) sind nicht blockiert
**Geschätzter Umfang:** 2–3 Dateien, PHP (nginx.conf) + ggf. PHP (DogController)
**Risiko:** niedrig — nur File-Upload betroffen, keine Auth/Datenmodell-Änderungen nötig
**Klarheit:** klar — Konsolenfehler zeigen direkt auf nginx `client_max_body_size`-Limit als Hauptursache

---

## Anforderung (Zusammenfassung)

Nach einem Hunde-Bild-Upload via `POST /api/v1/dogs/{id}/upload-image` (aufgerufen aus
`DogFormModal.vue`) wird das Profilbild nicht gespeichert. Die Browser-Konsole zeigt drei
verschiedene HTTP-Fehler: 422 (auf anderen Endpunkten), 500 (upload-image), und 413 mit
CORS-Blocked-Fehler (upload-image). Der Upload schlägt je nach Dateigröße auf verschiedenen
Ebenen fehl.

---

## Fehleranalyse

### Fehler 1: HTTP 413 + CORS-Fehler (Hauptursache)

```
[Error] Origin http://localhost:5173 is not allowed by Access-Control-Allow-Origin. Status code: 413
[Error] XMLHttpRequest cannot load http://localhost:8081/api/v1/dogs/1/upload-image due to access control checks.
```

**Ursache:** Nginx gibt HTTP 413 zurück, bevor der Request Laravel (und damit das CORS-Middleware)
erreicht. Laravel fügt die `Access-Control-Allow-Origin`-Header nie hinzu, weil Nginx die
Anfrage schon abgelehnt hat. Nginx's eigene 413-Fehlerantwort enthält keine CORS-Header.

**Beleg:** `docker/nginx/conf.d/default.conf` — die Datei enthält **kein**
`client_max_body_size`-Direktiv. Nginx-Default ist `1MB`. Der Laravel-Validator erlaubt
bis `5120KB` (`max:5120`):
- `docker/nginx/conf.d/default.conf` — kein `client_max_body_size`-Eintrag (gesamte Datei geprüft)
- `backend/app/Http/Controllers/Api/DogController.php:185` — `'image' => ['required', 'image', 'mimes:jpg,jpeg,png,gif,webp', 'max:5120']`
- `docker/php/php.ini:46` — `post_max_size = 100M` (PHP-Limit ist kein Problem)
- `docker/php/php.ini:63` — `upload_max_filesize = 100M` (PHP-Limit ist kein Problem)

**Schlussfolgerung:** Bilder > 1MB werden von Nginx mit 413 abgelehnt, ohne CORS-Header.
Das täuscht als CORS-Fehler, ist aber eigentlich ein Nginx-Limit-Problem.

**Bestätigt durch User:** Testbild war ~5MB → trifft exakt das 1MB-Nginx-Default-Limit.

---

### Fehler 2: HTTP 500 auf upload-image (sekundär / unabhängig)

```
[Error] Failed to load resource: the server responded with a status of 500 (Internal Server Error) (upload-image, line 0)
[Error] Serverfehler
    (anonyme Funktion) (client.ts:49)
```

**client.ts:49** (Zeile 49 im Response-Interceptor) ist der `console.error('Serverfehler')`-Aufruf
für HTTP 500, kein Stack-Trace aus dem Backend:
- `frontend/src/api/client.ts:85` — `if (error.response?.status === 500) { console.error('Serverfehler') }`

**Mögliche Ursachen (serverseitig, da 500 nicht durch Logs belegbar):**

a) **`php artisan storage:link` nicht ausgeführt:** `uploadImage()` schreibt via
   `Storage::disk('public')` nach `storage/app/public/`. Ist der Symlink `public/storage →
   storage/app/public` nicht gesetzt, schlägt `storeAs()` still fehl oder wirft eine Exception.
   - `backend/app/Http/Controllers/Api/DogController.php:192–197` — Nutzung von `Storage::disk('public')`

b) **`$dog->update(['profile_image' => $path])` wenn `profile_image` nicht in `$fillable`:**
   Falls das Feld nicht als fillable markiert ist, wird es silently ignoriert ohne Exception —
   dies würde aber kein 500 auslösen, sondern nur nicht speichern. Migration existiert:
   - `backend/database/migrations/2026_05_04_100000_add_profile_image_to_dogs_table.php:15` —
     `$table->string('profile_image')->nullable()->after('notes');`

c) **Datei ≤ 1MB, aber andere Server-Exception:** Konkrete Ursache ohne Backend-Logs nicht
   verifizierbar. Die 500-Fehler könnten auch von Test-Uploads stammen, die nach dem 413-Fix
   verschwinden (wenn bisher alle > 1MB-Uploads sofort mit 413 scheiterten).

**Empfehlung:** Nach dem Nginx-Fix (`client_max_body_size`) mit einem kleinen Bild (< 1MB)
testen, ob 500 weiterhin auftritt. Dann Backend-Logs prüfen.

---

### Fehler 3: HTTP 422 auf dog-registration-requests und login (unabhängig)

```
[Error] Failed to load resource: the server responded with a status of 422 (Unprocessable Content) (dog-registration-requests, line 0)
[Error] Failed to load resource: the server responded with a status of 422 (Unprocessable Content) (login, line 0)
```

**Ursache:** 422 ist ein Laravel-Validierungsfehler. Diese Fehler betreffen
**andere Endpunkte** (`/api/v1/dog-registration-requests` und `/api/v1/auth/login`) und
sind **nicht direkt mit dem Upload-Bug verknüpft**.

Mögliche Erklärungen:
- Testdaten/Formulardaten fehlten beim Reproduzieren des Bugs
- Die 422 auf `login` könnte auf fehlende oder falsche Credentials im Testlauf hinweisen
- Kein Code-Defekt im Upload-Pfad

**Einschätzung:** Wahrscheinlich Artefakte des Testlaufs, kein eigener Bug. Nicht als separater
Bug zu behandeln, solange sie sich nicht bei normalem Nutzerverhalten reproduzieren lassen.

---

## Root Cause Hypothese

**Primäre Root Cause:** Nginx-Konfiguration in `docker/nginx/conf.d/default.conf` enthält kein
`client_max_body_size`-Direktiv. Der Nginx-Default (1MB) liegt unter dem Laravel-Validierungslimit
(5MB). Bilder > 1MB werden von Nginx mit HTTP 413 abgelehnt, bevor Laravel-Middleware (inkl. CORS)
laufen kann. Dadurch fehlen CORS-Header in der 413-Antwort, was im Browser als CORS-Fehler
erscheint.

**Sekundäre Root Cause (unbestätigt):** HTTP 500 auf `upload-image` deutet möglicherweise auf
einen fehlenden `storage:link`-Symlink hin. Kann erst nach dem Nginx-Fix verifiziert werden.

---

## Workflow-Pfad: klein

**Begründung:**
- Primärer Fix: 1 Zeile in `docker/nginx/conf.d/default.conf` (`client_max_body_size 10m;`)
- Möglicher Sekundär-Fix: `storage:link` in Docker-Entrypoint prüfen/ergänzen
  (`docker/php/docker-entrypoint.sh`)
- Kein Schnittstellen-Bruch, keine Datenmodell-Änderungen
- Beide Fixes sind risikoarm und klar lokalisiert
- Betrifft ausschließlich `dev-php`-Bereich (Infrastruktur + Backend-Config)

---

## Empfohlene nächste Aktion

`architect`-Agent (Mode A) erstellt einen kleinen Change mit:

1. **T01 (dev-php):** `docker/nginx/conf.d/default.conf` — `client_max_body_size 10m;` im
   `server`-Block ergänzen (oberhalb des `location /`-Blocks). Wert muss ≥ Laravel-Validierungslimit
   (5120KB = 5MB) + multipart-Overhead sein.

2. **T02 (dev-php, optional/bedingt):** `docker/php/docker-entrypoint.sh` prüfen, ob
   `php artisan storage:link` aufgerufen wird. Falls nicht, ergänzen. Hintergrund:
   erklärte möglicherweise den HTTP 500.

3. Nach T01: Manuell mit Bild > 1MB testen. Dann mit Bild < 1MB, ob 500 noch auftritt.

**Nicht in diesem Change:** Die 422-Fehler auf `login` und `dog-registration-requests`
sind nicht Teil dieses Bugs.

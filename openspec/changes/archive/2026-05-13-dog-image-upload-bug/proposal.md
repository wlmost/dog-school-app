# Proposal: dog-image-upload-bug

**Change-ID:** dog-image-upload-bug
**Typ:** Bug-Fix
**Priorität:** mittel
**Datum:** 2026-05-13

---

## Problem-Statement

Nach einem Hunde-Bild-Upload via `POST /api/v1/dogs/{id}/upload-image` (ausgelöst aus
`DogFormModal.vue`) wird das Profilbild nicht gespeichert. Der Upload schlägt auf zwei
verschiedenen Ebenen fehl:

1. **Bei Bildern > 1 MB:** Nginx gibt HTTP 413 zurück, **bevor** der Request Laravel
   erreicht. Da Laravel (und damit das CORS-Middleware) nie aufgerufen wird, enthält die
   413-Antwort keine `Access-Control-Allow-Origin`-Header. Der Browser interpretiert dies
   fälschlicherweise als CORS-Fehler, obwohl die eigentliche Ursache ein Nginx-Limit ist.

2. **Bei kleineren Bildern (unbestätigt):** Auf dem `upload-image`-Endpunkt tritt
   vereinzelt HTTP 500 auf. Die konkrete Ursache ist ohne Backend-Logs nicht verifizierbar
   und könnte nach dem Nginx-Fix wegfallen.

**Auswirkung:** Für Endbenutzer ist der Bild-Upload-Button faktisch funktionslos, sobald
das zu ladende Bild größer als 1 MB ist — was bei Smartphone-Fotos von Hunden der Regelfall
ist. Laravel erlaubt bis zu 5 MB (`max:5120`), Nginx blockiert bereits bei > 1 MB.

---

## Ursachen

### Primär (bestätigt): Fehlendes `client_max_body_size` in Nginx

`docker/nginx/conf.d/default.conf` enthält keine `client_max_body_size`-Direktive.
Nginx-Standardwert ist `1 MB`. Das Laravel-Validierungslimit beträgt `5120 KB` (= 5 MB).
Die PHP-Limits sind unkritisch (`post_max_size = 100M`, `upload_max_filesize = 100M`).

**Diskrepanz:** Nginx schneidet bei > 1 MB ab → Laravel sieht den Request nie → kein CORS-Header
in der 413-Fehlerantwort → Browser meldet fälschlich CORS-Fehler.

### Sekundär (unbestätigt): HTTP 500 auf `upload-image`

Auf dem Upload-Endpunkt tritt vereinzelt HTTP 500 auf. Code-Review von
`DogController.php` und `Dog.php` zeigt keinen offensichtlichen Defekt:

- `profile_image` ist korrekt in `Dog::$fillable` eingetragen.
- Die `storeAs()`-Logik ist plausibel; ein fehlender `storage:link`-Symlink
  würde kein 500 während des Uploads auslösen.
- Mögliche Erklärung: Die 500-Fehler stammen aus Testläufen mit kleineren Dateien,
  die nach dem Nginx-Fix nicht mehr reproduzierbar sind.

**Empfehlung:** Erst nach dem Nginx-Fix mit einem Bild < 1 MB testen und Backend-Logs
(`backend/storage/logs/laravel.log`) auswerten. Dann entscheiden, ob Nacharbeit nötig ist.

---

## Proposed Solution

### Fix 1 (Pflicht): Nginx `client_max_body_size`

In `docker/nginx/conf.d/default.conf` die Direktive `client_max_body_size 10M;` hinzufügen.
Wert von 10 MB gewählt: doppelter Puffer über dem Laravel-Validierungslimit von 5 MB, damit
das Laravel-Validierungsfeedback (`max:5120` → HTTP 422) dem Nutzer sauber zurückgegeben wird,
anstatt von Nginx mit 413 abgefangen zu werden.

### Fix 2 (bedingt): HTTP-500-Ursache untersuchen

Nach dem Nginx-Fix Upload mit Bild < 1 MB testen. Falls 500 weiterhin auftritt:
Backend-Logs prüfen und gezielte Nacharbeit einleiten.

---

## Out of Scope

- **HTTP 422 auf `/api/v1/dog-registration-requests` und `/api/v1/auth/login`:** Diese
  Fehler betreffen andere Endpunkte und sind nicht mit dem Upload-Bug verknüpft.
  Sie entstanden vermutlich durch fehlende Testdaten/Credentials beim Reproduzieren.
- Änderungen an der Laravel-Validierungsregel (`max:5120`) — der Wert ist bewusst gewählt.
- Upload-Limit auf Shared Hosting: Dort steuert der Hoster nginx/Apache-Limits.
  Eine Dokumentation mit Hinweis an den Betreiber genügt.

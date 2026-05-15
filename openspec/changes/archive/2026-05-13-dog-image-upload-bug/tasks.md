# Tasks: dog-image-upload-bug

**Change-ID:** dog-image-upload-bug
**Datum:** 2026-05-13

---

## T01: Nginx `client_max_body_size` setzen

- **Agent:** `dev-php`
- **Dateien:** `docker/nginx/conf.d/default.conf`
- **Abhängigkeiten:** keine
- **Priorität:** Pflicht

### Beschreibung

In `docker/nginx/conf.d/default.conf` fehlt die Direktive `client_max_body_size`.
Nginx-Standardwert ist 1 MB; das Laravel-Validierungslimit beträgt 5 MB (`max:5120`).
Dateien > 1 MB werden von Nginx mit HTTP 413 abgelehnt, bevor der Request Laravel erreicht.
Da das CORS-Middleware nie läuft, enthält die 413-Antwort keine CORS-Header — der Browser
sieht fälschlich einen CORS-Fehler.

**Einzufügen:** `client_max_body_size 10M;` nach `charset utf-8;` (Zeile 10),
vor dem `# Security headers`-Block.

**Exakter Patch:**

```nginx
    charset utf-8;

    # Increase body size limit to allow image uploads up to Laravel's validation limit (5MB)
    # Default Nginx limit is 1MB; without this, files > 1MB receive HTTP 413 before
    # reaching Laravel, which causes the CORS middleware to be skipped.
    client_max_body_size 10M;

    # Security headers
```

**Kein zweiter Patch-Kandidat:** `deployment-templates/` enthält ausschließlich
`.htaccess`-Dateien für Apache-Shared-Hosting. Nginx-Limits auf Shared Hosting werden vom
Hoster gesteuert und sind nicht Teil dieses Changes.

### Akzeptanzkriterien

- [x] `docker/nginx/conf.d/default.conf` enthält `client_max_body_size 10M;` im
      `server {}`-Block, nach `charset utf-8;`, vor dem Security-Headers-Block.
- [x] Kein anderer Inhalt der Datei verändert.
- [ ] Manueller Smoke-Test: Upload eines ~5-MB-Bildes via `DogFormModal.vue` liefert
      HTTP 200 (oder HTTP 422 bei Überschreitung des Validierungslimits) — kein HTTP 413,
      kein CORS-Fehler mehr.
- [ ] Upload eines Bildes > 10 MB liefert HTTP 413 (Nginx greift korrekt).
- [ ] `docker compose restart nginx` wurde nach dem Patch ausgeführt (kein Hot-Reload
      für nginx.conf).

---

## T02: HTTP-500-Ursache untersuchen (bedingt)

- **Agent:** `dev-php`
- **Dateien:** `backend/app/Http/Controllers/Api/DogController.php`,
              `backend/storage/logs/laravel.log`
- **Abhängigkeiten:** T01 (muss abgeschlossen und getestet sein)
- **Priorität:** Bedingt — nur ausführen, wenn HTTP 500 nach T01 weiterhin auftritt

### Bedingung

Nach erfolgreichem T01-Smoke-Test: Upload eines kleinen Bildes (< 1 MB) via
`DogFormModal.vue` testen. Wenn dieser Upload **kein HTTP 500** zurückgibt: T02 entfällt.

Tritt HTTP 500 weiterhin auf: `backend/storage/logs/laravel.log` auslesen und Stack-Trace
analysieren.

### Beschreibung

Die Triage dokumentiert vereinzelte HTTP-500-Fehler auf `upload-image`. Code-Review
(`DogController.php:181–203`, `Dog.php:53–68`) ergab keinen offensichtlichen Defekt:

- `profile_image` ist in `Dog::$fillable` eingetragen — kein Mass-Assignment-Problem.
- `Storage::disk('public')->storeAs()` erfordert keinen `storage:link`-Symlink zum Schreiben.
- Controller-Logik (Auth, Validate, Delete-Old, Store, Update, Return Resource) ist plausibel.

Wahrscheinlichste Erklärung: Die 500-Fehler entstanden bei Testläufen mit kleinen Bildern
(< 1 MB), die durch einen anderen, bisher unbekannten Pfad liefen. Nach dem Nginx-Fix
könnte sich das Fehlerbild vollständig auflösen.

**Falls 500 weiterhin auftritt — Vorgehen:**

1. `backend/storage/logs/laravel.log` nach dem fehlgeschlagenen Upload-Request auslesen.
2. Stack-Trace auswerten; verdächtige Stellen:
   - `Storage::disk('public')->storeAs()` — Schreibfehler (Permissions, fehlender Ordner)
   - `$dog->fresh(['customer.user'])` — fehlerhafte Eager-Load-Konfiguration
   - `$this->authorize('update', $dog)` — fehlerhafte Policy-Konfiguration
3. Fix implementieren und Smoke-Test wiederholen.

### Akzeptanzkriterien (nur relevant wenn T02 ausgeführt wird)

- [ ] Backend-Log-Analyse abgeschlossen; konkrete Exception-Klasse und Stack-Frame
      dokumentiert in `task-T02.notes.md`.
- [ ] Ursache des HTTP 500 behoben.
- [ ] Upload eines kleinen Bildes (< 1 MB) liefert HTTP 200 mit `DogResource`-JSON.
- [ ] Upload eines ~4-MB-Bildes liefert HTTP 200 (nach T01-Fix).
- [ ] Keine Regression in anderen `DogController`-Endpunkten.

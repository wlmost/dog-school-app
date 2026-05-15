# Design: dog-image-upload-bug

**Change-ID:** dog-image-upload-bug
**Datum:** 2026-05-13

---

## 1. Betroffene Dateien (Übersicht)

| Datei | Änderung | Pflicht? |
|---|---|---|
| `docker/nginx/conf.d/default.conf` | `client_max_body_size 10M;` einfügen | Ja (T01) |
| `deployment-templates/htaccess/` | Kein Patch nötig (Apache-Only, Hosting-Seite) | — |
| `backend/app/Http/Controllers/Api/DogController.php` | Kein Patch (kein Defekt gefunden) | — |
| `backend/app/Models/Dog.php` | Kein Patch (`profile_image` bereits in `$fillable`) | — |

---

## 2. Fix T01: Nginx `client_max_body_size`

### Befund

`docker/nginx/conf.d/default.conf` enthält keine `client_max_body_size`-Direktive
(gesamte Datei geprüft, 75 Zeilen). Nginx-Standardwert: **1 MB**.

Laravel-Validierungsregel in
`backend/app/Http/Controllers/Api/DogController.php:185`:
```php
'image' => ['required', 'image', 'mimes:jpg,jpeg,png,gif,webp', 'max:5120'],
```

Das PHP-seitige Limit ist unkritisch:
- `docker/php/php.ini`: `post_max_size = 100M`, `upload_max_filesize = 100M`

### Einfügeort

Nach `charset utf-8;` (Zeile 10 in `default.conf`), vor dem Security-Headers-Block.
Das ist die Konvention: Server-Level Body-Limits gehören in den oberen Teil des
`server {}`-Blocks, bevor spezifische Location-Blöcke folgen.

**Vorher (Zeilen 9–14):**
```nginx
    charset utf-8;

    # Security headers
    add_header X-Frame-Options "SAMEORIGIN" always;
```

**Nachher:**
```nginx
    charset utf-8;

    # Increase body size limit to allow image uploads up to Laravel's validation limit (5MB)
    # Default Nginx limit is 1MB; without this, files > 1MB receive HTTP 413 before
    # reaching Laravel, which causes the CORS middleware to be skipped.
    client_max_body_size 10M;

    # Security headers
    add_header X-Frame-Options "SAMEORIGIN" always;
```

### Warum 10 MB?

Das Laravel-Validierungslimit beträgt 5 MB (`max:5120`). Nginx-Limit auf 10 MB gesetzt,
um 2× Puffer zu geben. Das stellt sicher:
- Dateien bis 5 MB: Nginx lässt durch → Laravel validiert → ggf. HTTP 422 mit sauberem
  Fehlermeldungs-JSON an den Browser.
- Dateien 5–10 MB: Nginx lässt durch → Laravel lehnt mit HTTP 422 ab — kein 413.
- Dateien > 10 MB: Nginx blockt mit 413. Dieser Fall ist für Hundefotos unwahrscheinlich.

### Deployment-Templates-Check

`deployment-templates/` enthält ausschließlich `.htaccess`-Dateien für Apache:

```
deployment-templates/htaccess/backend-public.htaccess
deployment-templates/htaccess/backend-root.htaccess
deployment-templates/htaccess/frontend.htaccess
deployment-templates/htaccess/frontend-dist.htaccess
deployment-templates/htaccess/root.htaccess
deployment-templates/htaccess/root-post-install.htaccess
deployment-templates/htaccess/storage.htaccess
```

**Keine Nginx-Konfigurationsdateien in `deployment-templates/`.** Kein zweiter Patch-Kandidat.
Shared Hosting verwendet Apache — dort steuert der Hoster Upload-Limits über `php.ini`-Overrides
(z. B. `php_value upload_max_filesize 10M` in `.htaccess`) oder über das Hoster-Panel.

---

## 3. Code-Review: DogController + Dog-Modell

### DogController.php — `uploadImage()` (Zeilen 181–203)

```php
public function uploadImage(Request $request, Dog $dog): DogResource
{
    $this->authorize('update', $dog);

    $request->validate([
        'image' => ['required', 'image', 'mimes:jpg,jpeg,png,gif,webp', 'max:5120'],
    ]);

    $file = $request->file('image');
    $extension = strtolower($file->getClientOriginalExtension());

    // Delete old image if it exists
    if ($dog->profile_image && Storage::disk('public')->exists($dog->profile_image)) {
        Storage::disk('public')->delete($dog->profile_image);
    }

    $filename = 'dog_' . $dog->id . '_' . Str::uuid() . '.' . $extension;
    $path = $file->storeAs('dog-images', $filename, 'public');

    $dog->update(['profile_image' => $path]);

    return new DogResource($dog->fresh(['customer.user']));
}
```

**Befund:** Kein offensichtlicher Defekt.

- `Storage::disk('public')->storeAs()` schreibt nach `storage/app/public/dog-images/` —
  der `public/storage`-Symlink wird für das Schreiben **nicht** benötigt.
- `$dog->update(['profile_image' => $path])` — `profile_image` ist in `Dog::$fillable`
  eingetragen (s. u.) — kein Mass-Assignment-Problem.
- Altes Bild wird korrekt gelöscht, wenn vorhanden.

**Hypothese `storage:link` fehlt:** Ein fehlender Symlink würde das *Ausliefern* des Bildes
per URL (`/storage/dog-images/...`) blockieren, aber **keinen HTTP 500 beim Upload** verursachen.
Das Schreiben via `Storage::disk('public')` erfolgt direkt in `storage/app/public/`, unabhängig
vom Symlink. `docker-entrypoint.sh` führt kein `storage:link` aus — das ist ein separater
Betriebshinweis, kein Upload-Defekt.

### Dog.php — `$fillable` (Zeile 53–68)

```php
protected $fillable = [
    'customer_id',
    'name',
    'breed',
    'date_of_birth',
    'gender',
    'neutered',
    'weight',
    'chip_number',
    'color',
    'veterinarian',
    'special_needs',
    'notes',
    'is_active',
    'profile_image',   // ← vorhanden
];
```

`profile_image` ist in `$fillable` — Triage-Hypothese b **ausgeschlossen**.

---

## 4. Risikobewertung

### Dev-Docker-Stack (Scope dieses Changes)

| Risiko | Bewertung |
|---|---|
| `client_max_body_size 10M` bricht andere Endpunkte | Gering — nur Upload-Endpunkte senden große Bodies |
| Nginx-Neustart nach Konfigurationsänderung | `docker compose restart nginx` genügt; kein Downtime-Risiko in Dev |
| PHP-Limitüberschreitung bei 5–10 MB | PHP-Limits bei 100 MB → kein Konflikt |

### Shared Hosting (kein Patch in diesem Change)

Der Nginx-Fix betrifft **ausschließlich den lokalen Docker-Dev-Stack**. Auf Shared Hosting:
- Kein Nginx konfigurierbar (Hoster verwaltet Server)
- Upload-Limits ggf. über `.htaccess` (`php_value upload_max_filesize`, `php_value post_max_size`)
  oder Hoster-Panel steuerbar
- **Empfehlung im Deployment-Dokument:** Betreiber muss beim Hoster sicherstellen, dass
  `upload_max_filesize ≥ 6M` und `post_max_size ≥ 6M` gesetzt sind.

### PHP 8.2-Kompatibilität

Dieser Change berührt **keine PHP-Dateien**. PHP-Kompatibilitätscheck nicht anwendbar.

---

## 5. Nicht-Scope-Abgrenzung

| Thema | Entscheidung |
|---|---|
| HTTP 422 auf `/dog-registration-requests`, `/auth/login` | Andere Endpunkte, separater Change wenn nötig |
| Laravel-Validierungsregel `max:5120` erhöhen | Nicht nötig; Wert ist korrekt |
| `storage:link` im Docker-Entrypoint automatisieren | Separater operationeller Verbesserungs-Change |
| Upload-Limit auf Shared Hosting konfigurieren | Betreiber-Aufgabe; Doku-Hinweis genügt |

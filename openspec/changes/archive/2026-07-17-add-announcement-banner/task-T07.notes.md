# Notes T07: Routen-Ergänzung `backend/routes/api.php`

**Change-ID:** add-announcement-banner
**Agent:** dev-php
**Datei geändert:** `backend/routes/api.php`

## Umsetzung

Die tatsächlichen Zeilennummern in `backend/routes/api.php` wichen leicht von
den in `design.md` Abschnitt 5.2 genannten ab (Datei hatte sich seit
Design-Erstellung verschoben). Ich habe die Einfügepunkte anhand der dort
beschriebenen Anker (bestehende Kommentar-Blöcke) im aktuellen Dateistand neu
verifiziert, statt die genannten Zeilennummern blind zu übernehmen.

1. **`use`-Import** (vorher Zeile 5-27, jetzt Zeile 5-28):
   `use App\Http\Controllers\Api\AnnouncementController;` alphabetisch
   zwischen `App\Http\Controllers\AnamnesisTemplateController` (Zeile 6) und
   `App\Http\Controllers\Api\AuthController` (vorher Zeile 7) eingefügt.
   Sortierlogik verifiziert: Der bestehende Import-Block ist nach dem vollen
   FQCN-String sortiert (`AnamnesisTemplateController` < `Api\Announcement…`
   < `Api\AuthController`, da `n` < `u` beim ersten abweichenden Zeichen nach
   dem gemeinsamen Präfix `Api\A`).

2. **Öffentliche Route** — neuer Block direkt nach dem bestehenden
   "Public pricing route"-Block (Zeile 55-58 im aktuellen Stand, nicht
   54-57 wie in `design.md` vermerkt — Verschiebung um eine Zeile durch den
   zusätzlichen `use`-Import), vor dem "Public course detail route"-Block:

   ```php
   // Public announcements route (no auth required)
   Route::prefix('v1')->group(function () {
       Route::get('/announcements', [AnnouncementController::class, 'publicIndex']);
   });
   ```

3. **Admin-Routen** — neuer Block innerhalb der bestehenden
   `Route::prefix('v1')->middleware('auth:sanctum')->group(...)`-Gruppe, nach
   dem "Settings Management"-Block (aktueller Stand Zeile 198-202, nicht
   192-195 wie in `design.md` vermerkt — gleiche Verschiebung), vor der
   schließenden `});` der `auth:sanctum`-Gruppe:

   ```php
   // Announcement Management (Admin only)
   Route::middleware('can:admin')->group(function () {
       Route::get('/admin/announcements', [AnnouncementController::class, 'index']);
       Route::post('/admin/announcements', [AnnouncementController::class, 'store']);
       Route::put('/admin/announcements/{announcement}', [AnnouncementController::class, 'update']);
       Route::delete('/admin/announcements/{announcement}', [AnnouncementController::class, 'destroy']);
   });
   ```

   Folgt exakt dem bestehenden Muster des "Settings Management"-Blocks
   (eigenes `Route::middleware('can:admin')->group(...)` innerhalb der
   äußeren `auth:sanctum`-Gruppe, analog zu `SettingsController`- und
   `TrainerController`-Routen).

## Verifikation

```
docker compose exec php php artisan route:list | grep -i announcement
```

Ergebnis (alle fünf Routen korrekt registriert):

```
GET|HEAD        api/v1/admin/announcements Api\AnnouncementController@index
POST            api/v1/admin/announcements Api\AnnouncementController@store
PUT             api/v1/admin/announcements/{announcement} Api\AnnouncementController@update
DELETE          api/v1/admin/announcements/{announcement} Api\AnnouncementController@destroy
GET|HEAD        api/v1/announcements Api\AnnouncementController@publicIndex
```

- `GET /api/v1/announcements` liegt außerhalb der `auth:sanctum`-Gruppe →
  ohne Authentifizierung erreichbar.
- Alle vier Admin-Routen liegen innerhalb `auth:sanctum` **und** zusätzlich
  innerhalb des neuen `can:admin`-Middleware-Blocks.

## Pre-Flight-Checks (CLAUDE.md Abschnitt 7.1)

- `docker compose exec php php artisan route:list | grep announcement` — alle
  fünf Routen korrekt (siehe oben).
- `docker compose exec php ./vendor/bin/pest --group=api` — 124 Tests grün,
  keine Regression durch die Routen-Änderung.
- `docker compose exec php ./vendor/bin/pint --test routes/api.php` meldet
  1 Style-Issue (`no_extra_blank_lines`/`no_whitespace_in_blank_line` u. a.).
  **Verifiziert per `git diff backend/routes/api.php`:** Dieses Issue betrifft
  ausschließlich vorbestehende Zeilen (trailing whitespace auf Leerzeilen,
  fehlender Zeilenumbruch am Dateiende), die bereits vor meiner Änderung im
  Datei-Stand vorhanden waren — mein Diff fügt ausschließlich neue,
  sauber formatierte Zeilen hinzu und ändert keine bestehende Zeile. Diese
  vorbestehende Formatierungsabweichung liegt außerhalb des Scopes von T07
  (keine fremden Zeilen anfassen) und wird hier nur dokumentiert, nicht
  behoben.
- `composer.json` (Backend) enthält aktuell keine `qa`/`lint`/`stan`/
  `compat-check`-Scripts (nur `post-*`-Hooks und `dev`) — entspricht dem in
  `proposal.md`/`design.md` Abschnitt 10 dokumentierten Befund ("kein
  automatisiertes `compat-check`-Script im Projekt vorhanden"). Manuelle
  Prüfung: Der T07-Diff verwendet ausschließlich PHP-8.2-kompatible Syntax
  (ein `use`-Import, `Route::`-Facade-Aufrufe) — keine der in CLAUDE.md
  Abschnitt 4.1 gelisteten 8.3/8.4-Konstrukte.

## Offene Punkte / Hinweise für Reviewer

- Keine funktionale Abweichung von `design.md` Abschnitt 5.2 — nur die
  Zeilennummern-Referenzen im Design-Dokument sind durch den zusätzlichen
  `use`-Import um jeweils eine Zeile verschoben (kosmetisch, keine
  inhaltliche Abweichung).
- Der vorbestehende Pint-Befund in `routes/api.php` (siehe oben) besteht
  unabhängig von T07 weiter und wäre ein eigener, kleiner Aufräum-Task,
  falls gewünscht.

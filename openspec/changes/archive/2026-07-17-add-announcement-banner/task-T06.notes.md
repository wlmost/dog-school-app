# Notes T06: Controller `Api/AnnouncementController`

**Agent:** dev-php
**Status:** abgeschlossen

## Umgesetzt

- Neue Datei `backend/app/Http/Controllers/Api/AnnouncementController.php`.
- Fünf öffentliche Methoden gemäß `design.md` Abschnitt 5.1:
  - `publicIndex(): AnonymousResourceCollection` — ungeschützt,
    `Announcement::query()->active()->orderByDesc('created_at')->get()`
    (nutzt `scopeActive()` aus T02), gewrappt über
    `AnnouncementResource::collection()`.
  - `index(): AnonymousResourceCollection` — `$this->authorize('viewAny',
    Announcement::class)`, liefert alle Datensätze (aktiv + abgelaufen).
  - `store(StoreAnnouncementRequest $request): JsonResponse` — keine
    zusätzliche `$this->authorize()`-Prüfung (analog `DogController::store()`),
    Autorisierung erfolgt ausschließlich über
    `StoreAnnouncementRequest::authorize()`. Bild-Upload nur bei
    `$request->hasFile('image')`, HTTP 201.
  - `update(UpdateAnnouncementRequest $request, Announcement $announcement):
    AnnouncementResource` — `$this->authorize('update', $announcement)`,
    löscht bei neuem Upload zuerst das alte Bild, dann Ersetzung.
  - `destroy(Announcement $announcement): JsonResponse` — `$this->authorize(
    'delete', $announcement)`, löscht Bild von Disk vor dem Löschen des
    Datensatzes, HTTP 204.
- Zwei private Hilfsmethoden:
  - `storeImage(Request $request): string` — Upload-Handling identisch zum
    Muster in `DogController::uploadImage()`
    (`backend/app/Http/Controllers/Api/DogController.php:180-202`):
    `Str::uuid()`-Dateiname (`announcement_<uuid>.<ext>`),
    `$file->storeAs('announcement-images', $filename, 'public')`.
  - `deleteImageIfExists(Announcement $announcement): void` — kleine
    Extraktion (DRY) der in `design.md` in `update()`/`destroy()`
    duplizierten Lösch-Prüfung (`Storage::disk('public')->exists(...) →
    delete(...)`); Verhalten ist identisch zum Design-Vorschlag, nur ohne
    Code-Duplikation zwischen den beiden Aufrufstellen.

## Abweichungen vom `design.md`-Codebeispiel

- `deleteImageIfExists()` als privates Hilfsmethode extrahiert statt den
  Lösch-Block in `update()` und `destroy()` zu duplizieren (DRY-Prinzip aus
  CLAUDE.md Abschnitt "Vorgehen"). Verhalten entspricht exakt dem in
  `design.md` Abschnitt 5.1 beschriebenen Code.
- `vendor/bin/pint` hat beim ersten Lauf automatisch PHPDoc-Blöcke bereinigt
  (überflüssige `@param $request`/`@return`-Tags entfernt, die keinen
  Mehrwert über die bereits typisierte Signatur hinaus bieten, sowie
  Concat-Space-Formatierung `.`) — Projekt-Codestil-Automatik, keine
  inhaltliche Änderung.

## Abhängigkeiten (bereits vorhanden, nur gelesen)

- `App\Policies\AnnouncementPolicy` (T03) — `viewAny`, `update`, `delete`.
- `App\Http\Requests\StoreAnnouncementRequest` /
  `UpdateAnnouncementRequest` (T04) — `authorize()`, `validatedSnakeCase()`.
- `App\Http\Resources\AnnouncementResource` (T05) — `toArray()`.
- `App\Models\Announcement` (T02) — `scopeActive()`.

Keine dieser Dateien wurde verändert.

## PHP-8.2-Kompatibilität (CLAUDE.md Abschnitt 4.1)

Manuell geprüft (kein `compat-check`-Script im Projekt vorhanden, siehe
`design.md` Abschnitt 10 / `proposal.md` "Out of Scope"): keine Property
Hooks, keine Asymmetric Visibility, keine typisierten Klassenkonstanten,
kein `#[\Override]`, kein `json_validate()`, keine der in Abschnitt 4.1
gelisteten 8.3/8.4-Konstrukte verwendet. Nur Standard-Eloquent-/
FormRequest-/Resource-/Storage-Facade-Aufrufe.

## Route-Registrierung

Bewusst **nicht** Teil dieser Task (T07). Der Controller ist so geschrieben,
dass T07 ihn unverändert registrieren kann (Methodennamen `publicIndex`,
`index`, `store`, `update`, `destroy` entsprechen exakt den in `design.md`
Abschnitt 5.2 referenzierten Routen-Zielen).

## Lokale Checks

```
cd backend
php -l app/Http/Controllers/Api/AnnouncementController.php   # keine Syntaxfehler
vendor/bin/pint --test app/Http/Controllers/Api/AnnouncementController.php  # passed
```

`composer qa` nicht ausführbar wie in CLAUDE.md beschrieben — die Scripts
`lint`/`stan`/`compat-check`/`test` existieren aktuell nicht in
`backend/composer.json` (nur `post-*`-Hooks und `dev`). Ersatzweise
verwendet: `vendor/bin/pint` (Lint, vorhanden) und `php artisan test`
(vollständige Suite, siehe unten). `vendor/bin/phpstan`/`vendor/bin/phpcs`
sind nicht in `vendor/bin/` installiert — PHPStan/Larastan- und
PHPCompatibility-Prüfung konnten daher nicht automatisiert ausgeführt
werden; das deckt sich mit `design.md` Abschnitt 10
("kein automatisiertes `compat-check`-Script vorhanden").

```
docker compose exec -T php php artisan test
# Tests: 693 passed (2195 assertions) — keine Regression durch die neue Datei
```

## Manuelle Verifikation der Akzeptanzkriterien

Da die Routen erst in T07 registriert werden (Feature-Tests folgen in T08),
wurden die Controller-Methoden über ein temporäres Tinker-Skript direkt
aufgerufen (gegen die lokale Docker-PostgreSQL-Instanz, `Storage::fake(
'public')`). Skript wurde nach dem Lauf wieder entfernt, keine Testartefakte
im Repo.

| Akzeptanzkriterium | Ergebnis |
|---|---|
| `publicIndex()` liefert nur `expires_at > now()` | PASS — `Announcement::factory()->create()` (aktiv) erscheint, `Announcement::factory()->expired()->create()` erscheint **nicht** |
| `index()` liefert alle (aktiv + abgelaufen) | PASS — beide Test-Datensätze erscheinen für Admin |
| `store()` speichert Bild unter `announcement-images/`, setzt `image_path` | PASS — `image_path` beginnt mit `announcement-images/`, `Storage::disk('public')->assertExists(...)` grün, `imageUrl` im Response korrekt gesetzt |
| `update()` löscht altes Bild bei neuem Upload | PASS — `Storage::disk('public')->assertMissing($oldImagePath)` nach Update mit neuem Bild grün, neues Bild existiert |
| `destroy()` löscht zugehöriges Bild | PASS — `Storage::disk('public')->assertMissing(...)` nach `destroy()` grün, Datensatz aus DB entfernt (204) |
| `index`/`store`/`update`/`destroy` → HTTP 403 für Nicht-Admin | PASS — `index()`/`update()`/`destroy()` werfen `AuthorizationException` (durch `$this->authorize()`, wird von Laravels Exception-Handler zu HTTP 403 gemappt); `StoreAnnouncementRequest::authorize()` liefert `false` für Nicht-Admin (wird von Laravels FormRequest-Validierung ebenfalls zu HTTP 403 gemappt) |

Zusätzlich verifiziert: `body` wird beim `store()`-Aufruf serverseitig
sanitized (`<script>alert(1)</script>` wurde aus dem Response-`body`
entfernt) — Bestätigung, dass `StoreAnnouncementRequest::validatedSnakeCase()`
korrekt vom Controller verwendet wird.

## Nicht Teil dieser Task

- Routen-Registrierung (`backend/routes/api.php`) — T07.
- Feature-Tests (`AnnouncementApiTest`) — T08.

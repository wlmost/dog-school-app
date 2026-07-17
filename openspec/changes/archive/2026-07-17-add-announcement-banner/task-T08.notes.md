# Notes T08 — Backend-Tests `AnnouncementApiTest`

**Agent:** dev-php
**Status:** abgeschlossen

## Umgesetzt

- Neue Datei `backend/tests/Feature/Api/AnnouncementApiTest.php` mit 11
  `it(...)`-Tests, Gruppen `api`, `announcement`, `RefreshDatabase`.
- Alle in `tasks.md` geforderten Szenarien als eigene Tests:
  1. Öffentlicher Endpunkt liefert nur aktive Ankündigungen
     (`expired()`-Announcement erscheint nicht in `data`).
  2. Admin-Endpunkt (`GET /api/v1/admin/announcements`) liefert aktive
     **und** abgelaufene Ankündigungen.
  3. Nicht-Admin erhält HTTP 403 auf alle vier Admin-Aktionen
     (`index`, `store`, `update`, `destroy`) — als vier separate Tests,
     nicht als eine Sammel-Assertion, damit ein Fehlschlag in einer Aktion
     nicht die Prüfung der anderen verdeckt.
  4. `store()` mit Bild-Upload: `image_path` wird gesetzt, Datei existiert
     auf der gefakten `public`-Disk, `imageUrl` im Response entspricht
     exakt `Storage::disk('public')->url($created->image_path)`.
  5. Sanitizing-Verifikation: `<p onclick="alert(1)">Hallo</p><script>alert(1)</script>`
     wird zu `<p>Hallo</p>alert(1)` bereinigt (Verifikation des exakten
     Strings via `strip_tags()`/Regex-Verhalten der Trait
     `SanitizesHtmlContent`, siehe Abschnitt "Verifizierte Annahmen" unten)
     und per `assertDatabaseHas` geprüft.
  6. `update()` mit neuem Bild löscht das alte Bild von der `public`-Disk
     (`Storage::disk('public')->assertMissing($oldPath)`), das neue Bild
     existiert und unterscheidet sich vom alten Pfad.
  7. `destroy()` löscht das zugehörige Bild von der Disk
     (`assertMissing`) und den Datensatz (`assertDatabaseMissing`).
  8. `displayDays = 400` (außerhalb 1–365) liefert HTTP 422 mit
     `assertJsonValidationErrors(['displayDays'])`.

## Wichtige technische Entscheidung: `update()`-Test ohne `_method`-Override

Der Task-Prompt wies darauf hin, für den `update()`-Test ggf.
`_method=PUT`-Override via `$this->post(...)` zu verwenden. Nach Prüfung
von `backend/routes/api.php:208` ist die Route jedoch ein **echtes**
`Route::put(...)` (kein `_method`-Override auf Anwendungsseite nötig).
Zusätzlich habe ich `Illuminate\Foundation\Testing\Concerns\MakesHttpRequests`
gelesen (`vendor/laravel/framework/.../MakesHttpRequests.php:422-428,
596-618`): `$this->put($uri, $data)` ruft `call('PUT', $uri, $data, ...)`
auf, und `call()` extrahiert `UploadedFile`-Instanzen aus `$data` via
`extractFilesFromDataArray()` unabhängig von der HTTP-Methode — die Datei
landet korrekt in der Files-Bag des Test-Requests. Ein `_method`-Override
war daher nicht nötig; ich verwende direkt `$this->put(...)` mit
`UploadedFile::fake()` im Datenarray. Diese Annahme ist im Test durch das
grüne Ergebnis verifiziert (Datei wird ersetzt, alte Datei verschwindet
von der Disk).

## Verifizierte Annahmen

- **Exakter sanitisierter String:** Vor dem Schreiben des Tests per
  `php -r` lokal nachvollzogen, was `SanitizesHtmlContent::sanitizeHtmlDescription()`
  (`backend/app/Http/Requests/Concerns/SanitizesHtmlContent.php:39-50`) aus
  `<p onclick="alert(1)">Hallo</p><script>alert(1)</script>` macht:
  `strip_tags()` entfernt das `<script>`-Tag, lässt aber dessen Text-Inhalt
  stehen (PHP-Verhalten seit jeher, kein Tag-Content-Removal); die
  anschließende Attribut-Strip-Regex entfernt `onclick="…"` vom `<p>`-Tag.
  Ergebnis: `<p>Hallo</p>alert(1)` — exakt dieser String wird in
  `assertDatabaseHas` geprüft.
- **Route für Update ist echtes `PUT`**, nicht `POST` mit
  `_method`-Override (siehe oben).

## Pre-Flight-Checks (innerhalb Docker, `dog-school-php`-Container)

```
docker compose exec php vendor/bin/pest --filter=AnnouncementApiTest
# → 11 passed (29 assertions)

docker compose exec php vendor/bin/pint --test tests/Feature/Api/AnnouncementApiTest.php
# → PASS (1 file, keine Formatierungsabweichungen)

docker compose exec php vendor/bin/pest
# → volle Suite: 704 passed (2224 assertions), keine Regressionen
```

**Hinweis:** `composer test`/`composer qa`/`composer stan`/`composer lint`
existieren nicht als Scripts in `backend/composer.json` (nur
`post-autoload-dump`, `post-update-cmd`, `post-root-package-install`,
`post-create-project-cmd`, `dev`). Auch `phpstan`/`php-cs-fixer` sind
nicht in `vendor/bin/` vorhanden — nur `pest` und `pint`. Ich habe daher
`vendor/bin/pest` und `vendor/bin/pint --test` direkt ausgeführt statt der
in `CLAUDE.md` Abschnitt 5/7.1 genannten Composer-Scripts. Diese Lücke
existiert unabhängig von T08 und liegt außerhalb des Task-Scopes (keine
`composer.json`-Änderung durch mich — das wäre eine fremde Datei/ein
anderer Task). Für den Architekten/Skeptiker als Hinweis dokumentiert,
falls ein eigener Change dafür sinnvoll ist.

## Nicht angefasst

- Kein Produktivcode geändert (Model, Policy, Requests, Resource,
  Controller, Routen, Migration, Factory bereits durch T01–T07 fertig).
- Keine Spec-Dateien geändert.

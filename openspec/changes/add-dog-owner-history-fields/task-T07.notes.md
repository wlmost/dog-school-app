# Task T07 — `DogResource` — API-Antwort erweitern

**Agent:** dev-php
**Status:** abgeschlossen

## Geänderte Dateien

- `backend/app/Http/Resources/DogResource.php` (geändert)

## Umsetzung

In `toArray()` wurden nach `'gender' => $this->gender,` drei neue Felder
ergänzt (exakt wie in `design.md` Abschnitt 5.1 und `tasks.md` T07
spezifiziert):

```php
'gender' => $this->gender,
'ownerSince' => $this->owner_since?->toDateString(),
'ageAtAcquisition' => $this->age_at_acquisition,
'origin' => $this->origin,
'neutered' => $this->neutered,
```

Das Muster für den nullable-Carbon-Cast (`?->toDateString()`) entspricht
exakt dem bestehenden `dateOfBirth`-Feld (Zeile 32 vor der Änderung).
`ageAtAcquisition` und `origin` sind Plain-String-Felder ohne Cast-Bedarf,
analog zu `breed`/`color`.

Voraussetzung T03 (Dog-Model mit `owner_since`/`age_at_acquisition`/`origin`
inkl. `date`-Cast auf `owner_since`) war laut Aufgabenstellung bereits
gemerged. Verifiziert in `backend/app/Models/Dog.php:32-34` (PHPDoc),
`:69-71` (`$fillable`), `:86` (`casts()`).

## Verifikation der Akzeptanzkriterien

1. **`GET /api/v1/dogs/{dog}` liefert die drei Felder korrekt** — verifiziert
   mit einem temporären, lokal ausgeführten und wieder gelöschten Pest-Test
   (nicht committed, da außerhalb des Scopes von T07 — echte Tests sind
   T10). Response enthielt `"ownerSince":"2024-01-01"`,
   `"ageAtAcquisition":"ca. 2 Jahre"`, `"origin":"shelter"` für einen Hund
   mit gesetzten Werten.
2. **`GET /api/v1/dogs` (Liste) liefert dieselben drei Felder pro Eintrag**
   — im selben temporären Test per `assertJsonStructure` auf
   `data.*.[ownerSince, ageAtAcquisition, origin]` verifiziert.
3. **Bestehende Tests bleiben grün** — vollständiger Testlauf
   `vendor/bin/pest --no-coverage` (in Docker, Service `php`) ergibt
   `679 passed (2120 assertions)`, keine Regressionen. Insbesondere
   `tests/Feature/Api/DogApiTest.php` (39 Tests) und
   `tests/Feature/DogRegistrationRequestApiTest.php` (23 Tests) grün.
   Bestehende `assertJsonStructure`-Blöcke prüfen nur eine Teilmenge der
   Keys (nicht exhaustiv), daher keine Anpassung durch die neuen Felder
   nötig.

## QA-Befunde

- `composer qa` existiert **nicht** als Script in `backend/composer.json`
  (nur `test`, `lint`, `stan`, `compat-check` sind einzeln vorhanden — siehe
  auch T10-Hinweis in `tasks.md`). `vendor/bin/phpstan` und `vendor/bin/phpcs`
  sind im `backend/vendor/bin/` **nicht installiert** (nur `pest` und
  `pint`) — daher konnten `stan`/`compat-check` nicht lokal ausgeführt
  werden. Bereits als vorbestehender Zustand in `proposal.md`
  "Out of Scope — Fehlende QA-Scripts" dokumentiert.
- `vendor/bin/pint --test app/Http/Resources/DogResource.php` meldet **einen
  vorbestehenden** Style-Issue (`fully_qualified_strict_types`,
  `@mixin \App\Models\Dog` statt `@mixin Dog`, sowie ein Trailing-Whitespace
  in einer Leerzeile) — beide Fundstellen liegen **außerhalb** der von mir
  geänderten Zeilen (`git diff` zeigt nur die drei neuen Zeilen als Delta).
  Nicht mitgefixt, da außerhalb des Scopes von T07 (keine Autofix-Aktion an
  unbeteiligtem Code ohne Rücksprache).
- `vendor/bin/pest --no-coverage` (voller Lauf): grün, 679 Tests.

## Hinweise für nachfolgende Tasks

- T08 (`DogRegistrationRequestResource.php`), T09
  (`DogRegistrationRequestController::approve()`) sowie weitere Tasks werden
  parallel von anderen Agenten bearbeitet (`git status` zeigt bereits
  Änderungen an `DogRegistrationRequestResource.php`, die nicht von mir
  stammen — nicht angefasst).
- T10 sollte `assertJsonStructure` in `DogApiTest.php` um `ownerSince`,
  `ageAtAcquisition`, `origin` ergänzen sowie dedizierte Testfälle für die
  drei neuen Felder ergänzen (aktuell nur implizit durch obige Ad-hoc-
  Verifikation abgedeckt, nicht dauerhaft im Repo).

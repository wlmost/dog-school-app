# Task T08 — `DogRegistrationRequestResource` — API-Antwort erweitern

**Agent:** dev-php
**Status:** abgeschlossen

## Geänderte Datei

- `backend/app/Http/Resources/DogRegistrationRequestResource.php`

## Umsetzung

In `toArray()` wurden nach `'dateOfBirth'` gemäß `design.md` Abschnitt 5.2
drei neue Felder ergänzt:

```php
'ownerSince' => $this->owner_since?->toDateString(),
'ageAtAcquisition' => $this->age_at_acquisition,
'origin' => $this->origin,
```

`vendor/bin/pint` wurde anschließend auf die geänderte Datei angewendet
(einziger lokal verfügbarer Formatter im Projekt, `php-cs-fixer` selbst ist
nicht als Binary vorhanden — siehe `vendor/bin/`). Pint hat dabei zusätzlich
folgende, mit dem Projekt-Codestandard konsistente Anpassungen an derselben
Datei vorgenommen (kein Scope-Creep, da ausschließlich diese eine Datei
betroffen ist):

- `use App\Models\DogRegistrationRequest;` ergänzt und `@mixin` auf den
  kurzen Klassennamen umgestellt (`fully_qualified_strict_types`,
  `ordered_imports`).
- Die manuelle Spalten-Ausrichtung der `=>`-Operatoren im `toArray()`-Array
  wurde entfernt (`binary_operator_spaces`, Pint-Standardregel
  `no_alignment` für Laravel-Preset). Das ist reine Formatierung, keine
  funktionale Änderung.

## Akzeptanzkriterien — Prüfung

- [x] `GET /api/v1/dog-registration-requests/{id}` liefert alle drei neuen
      Felder — verifiziert per manuellem PHP-Skript (Artisan-Bootstrap,
      `DogRegistrationRequestResource::toArray()` direkt aufgerufen) mit
      befüllten Werten (`owner_since = 2020-05-15`,
      `age_at_acquisition = 'ca. 2 Jahre'`, `origin = 'shelter'`):
      ```
      ownerSince => '2020-05-15'
      ageAtAcquisition => 'ca. 2 Jahre'
      origin => 'shelter'
      ```
      `owner_since` wird korrekt vom `date`-Cast (aus T04) über
      `?->toDateString()` in einen ISO-Datumsstring transformiert.
- [x] `GET /api/v1/dog-registration-requests` (Liste) liefert dieselben drei
      Felder pro Eintrag — die Resource wird sowohl für Einzel- als auch für
      Listen-Responses verwendet (`DogRegistrationRequestResource` ist eine
      einfache `JsonResource`, keine abweichende Collection-Logik), daher
      gilt dieselbe `toArray()`-Struktur für beide Endpunkte. Zusätzlich per
      Regressionstest mit leerem Modell (keine Werte gesetzt) geprüft: alle
      drei Keys sind vorhanden (`array_key_exists`) und liefern `null` statt
      zu fehlen.

Ein dedizierter Feature-Test für die neuen Felder in
`DogRegistrationRequestApiTest.php` ist Teil von T10 (nicht dieser Task) und
wurde bewusst nicht vorweggenommen.

## Lokale Checks

```
vendor/bin/pint app/Http/Resources/DogRegistrationRequestResource.php --test
# {"tool":"pint","result":"passed"}

vendor/bin/pest tests/Feature/DogRegistrationRequestApiTest.php --no-coverage
# Tests: 24 passed (50 assertions)
```

`composer test`/`composer qa` existieren laut `proposal.md`
("Out of Scope — Fehlende QA-Scripts") nicht als Composer-Scripts in
`backend/composer.json`; stattdessen wurden `pint` und `pest` direkt
verwendet (identisch zum in `tasks.md` T10 referenzierten
`.github/workflows/ci.yml`-Befehl `vendor/bin/pest --no-coverage`).
Ein volles `pest`-Suite-Run wurde bewusst nicht ausgeführt, um parallele
Arbeiten anderer Agenten an T05/T06/T07/T09 (aktuell laufende
Uncommitted-Changes laut `git status`) nicht als False-Positives in dieses
Notes-File einfließen zu lassen — stattdessen wurde gezielt die für diese
Task relevante Testdatei (`DogRegistrationRequestApiTest.php`) ausgeführt.

## Keine Abweichungen

Kein Scope-Creep, keine Änderung an anderen Dateien
(`DogResource.php`, `DogRegistrationRequestController.php` etc. wurden nicht
angefasst — diese gehören zu T07/T09).

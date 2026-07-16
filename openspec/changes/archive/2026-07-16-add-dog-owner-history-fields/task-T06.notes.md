# Notes T06: `StoreDogRegistrationRequest` — Validierung erweitern

**Agent:** dev-php
**Status:** abgeschlossen

## Geänderte Dateien

- `backend/app/Http/Requests/StoreDogRegistrationRequest.php`
  - `rules()`: drei neue Einträge nach `notes` ergänzt:
    ```php
    'ownerSince'       => ['nullable', 'date', 'before_or_equal:today'],
    'ageAtAcquisition' => ['nullable', 'string', 'max:255'],
    'origin'           => ['nullable', 'in:breeder,shelter,private,unknown'],
    ```
  - `attributes()`: zwei neue Einträge nach `chipNumber` ergänzt:
    ```php
    'ownerSince'       => 'owner since date',
    'ageAtAcquisition' => 'age at acquisition',
    ```
  - `validatedSnakeCase()` unverändert gelassen (generisches
    `Str::snake()`-Mapping deckt die neuen Felder automatisch ab, wie in
    `design.md` Abschnitt 4.3 beschrieben).

## Abgleich mit `design.md` Abschnitt 4.3

Umsetzung entspricht wortgleich der dort vorgegebenen Regeln und
Attribut-Labels.

## Verifikation der Akzeptanzkriterien

Durchgeführt gegen die lokale Docker-Umgebung (PostgreSQL), Backend-Container
`dog-school-php`. Ein temporärer Test-Customer
(`t06verify@example.test`, inkl. `Customer`-Profil) wurde für echte
End-to-End-HTTP-Requests angelegt und nach der Verifikation wieder gelöscht
(inkl. der dabei erzeugten `DogRegistrationRequest`-Datensätze und
Sanctum-Tokens — keine Rückstände in der DB).

- [x] `POST /api/v1/dog-registration-requests` mit `ownerSince`,
      `ageAtAcquisition`, `origin` erstellt eine Anfrage mit allen drei
      Werten — verifiziert via echtem `curl`-POST gegen
      `http://localhost:8081/api/v1/dog-registration-requests` mit
      Sanctum-Bearer-Token: `HTTP 201`, Response-JSON enthält
      `"ownerSince":"2024-01-01"`, `"ageAtAcquisition":"ca. 2 Jahre"`,
      `"origin":"shelter"`.
- [x] `POST /api/v1/dog-registration-requests` ohne die drei Felder
      funktioniert weiterhin (Regressionsschutz) — verifiziert via
      `curl`-POST mit nur `name`: `HTTP 201`, alle drei neuen Felder `null`
      in der Response.
- [x] `POST /api/v1/dog-registration-requests` mit ungültigem `origin`
      (`"xyz"`) gibt 422 zurück — verifiziert via `curl`-POST:
      `HTTP 422`, Fehlermeldung `"The selected origin is invalid."`.
- [x] Bestehende Feature-Tests für `StoreDogRegistrationRequest` bleiben
      grün — `DogRegistrationRequestApiTest` (24 Tests) grün, volle
      Suite (679 Tests, 2120 Assertions) grün, keine Regressionen.

Zusätzlich isoliert per `Validator::make()` in `artisan tinker` geprüft
(vor dem HTTP-Test):
- Gültige Werte (`ownerSince`, `ageAtAcquisition`, `origin`) passieren die
  Validierung.
- Ungültiger `origin`-Wert (`"xyz"`) schlägt fehl mit der erwarteten
  `in:`-Fehlermeldung.
- `ownerSince` in der Zukunft (`now()->addDay()`) schlägt fehl mit
  `before_or_equal:today` und nutzt das custom Attribut-Label
  ("The owner since date field must be a date before or equal to today.").
- Keine Felder außer `name` gesetzt: Validierung passiert weiterhin
  (Regressionsschutz auf Regel-Ebene).

Manuelle Prüfung: keine der in CLAUDE.md Abschnitt 4.1 gelisteten
PHP-8.3/8.4-Konstrukte verwendet (`grep` gegen die geänderte Datei auf
`new X()->`, `::{`, `#[\Override]`, `#[\Deprecated]`, `const string`,
`array_find`/`array_any`/`array_all` — keine Treffer). Kein
automatisiertes `compat-check`-Script im Projekt vorhanden (siehe
`proposal.md` "Out of Scope — Fehlende QA-Scripts", bereits in T01–T05
dokumentiert).

## QA-Läufe

- `docker compose exec -T php ./vendor/bin/pest --no-coverage`:
  **679 passed (2120 assertions)**, keine Regressionen.
  (`composer qa`/`composer test`/`composer lint`/`composer stan`/
  `composer compat-check` existieren nicht als Scripts in
  `backend/composer.json` — siehe `proposal.md` "Out of Scope — Fehlende
  QA-Scripts". Direkter Pest-Aufruf entspricht dem in
  `.github/workflows/ci.yml:112` verwendeten Befehl.)
- `vendor/bin/pint --test app/Http/Requests/StoreDogRegistrationRequest.php`
  meldet Fehler, aber **identisch zum Vorzustand** (per `git stash`
  gegengeprüft): `fully_qualified_strict_types`, `no_superfluous_phpdoc_tags`,
  `phpdoc_trim`, `ordered_imports` sind vorbestehende Formatierungsbefunde
  in dieser Datei, unabhängig von T06. `binary_operator_spaces` betrifft
  auch die *bestehenden* Array-Einträge (`name`, `breed`, `gender`, …), die
  bereits vor dieser Änderung mit ausgerichteten `=>` formatiert waren. Die
  neu ergänzten Zeilen folgen exakt dem in `design.md` Abschnitt 4.3 und
  `tasks.md` T06 vorgegebenen Format (untereinander ausgerichtete `=>`).
  Kein Pint-Fix angewendet, um nicht in den Scope-Bereich anderer
  paralleler Tasks (T05 arbeitet an strukturell ähnlichen Dateien) oder
  vorbestehende, nicht in T06 beauftragte Formatierungsbefunde einzugreifen
  — Scope-Treue hat Vorrang. Sollte im Review als Befund auftauchen, ist
  eine projektweite Pint-Bereinigung ein eigener, gebündelter Change wert.

## Abweichungen vom Muster

Keine inhaltlichen Abweichungen von `design.md` Abschnitt 4.3. Die neuen
`rules()`- und `attributes()`-Einträge sind wortgleich aus der Task-
Beschreibung übernommen.

## Scope-Einhaltung

Ausschließlich `backend/app/Http/Requests/StoreDogRegistrationRequest.php`
bearbeitet. Keine Berührung von `StoreDogRequest.php`/`UpdateDogRequest.php`
(T05), `DogResource.php` (T07), `DogRegistrationRequestResource.php` (T08)
oder `DogRegistrationRequestController.php` (T09) — diese laufen parallel
bei anderen Agenten.

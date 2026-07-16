# Notes: T05 — `StoreDogRequest` + `UpdateDogRequest` — Validierung erweitern

**Agent:** dev-php
**Change-ID:** add-dog-owner-history-fields
**Status:** abgeschlossen

## Geänderte Dateien

- `backend/app/Http/Requests/StoreDogRequest.php`
  - `rules()`: drei neue Einträge nach `'notes'` ergänzt:
    `'ownerSince' => ['nullable', 'date', 'before_or_equal:today']`,
    `'ageAtAcquisition' => ['nullable', 'string', 'max:255']`,
    `'origin' => ['nullable', 'in:breeder,shelter,private,unknown']`.
  - `attributes()`: `'ownerSince' => 'owner since date'` und
    `'ageAtAcquisition' => 'age at acquisition'` ergänzt (kein
    `attributes()`-Eintrag für `origin`, analog zu `gender`, siehe
    `design.md` Abschnitt 4.1).
  - `validatedSnakeCase()` unverändert gelassen — generisches
    `Str::snake()`-Mapping deckt `ownerSince → owner_since` und
    `ageAtAcquisition → age_at_acquisition` bereits ab (verifiziert, siehe
    unten).
- `backend/app/Http/Requests/UpdateDogRequest.php`
  - Identische drei Regeln in `rules()` ergänzt (kein `sometimes`, da die
    Felder bereits rein `nullable` sind, analog zu `weight`/`color`/`notes`
    in derselben Methode, siehe `design.md` Abschnitt 4.2).
  - Identische zwei `attributes()`-Einträge ergänzt.
  - `validatedSnakeCase()` unverändert.

## Nicht angefasst (außerhalb des Scopes von T05)

- `backend/app/Http/Requests/StoreDogRegistrationRequest.php` (T06),
  `backend/app/Http/Resources/DogResource.php` (T07),
  `backend/app/Http/Resources/DogRegistrationRequestResource.php` (T08),
  `backend/app/Http/Controllers/Api/DogRegistrationRequestController.php`
  (T09) — beim Start waren diese bereits als "modified" markiert (parallele
  Bearbeitung durch andere Agenten). Nicht angerührt.

## Verifikation der Akzeptanzkriterien

Alle Prüfungen liefen in der laufenden Docker-Dev-Umgebung
(`docker compose exec php ...`) gegen die lokale PostgreSQL-Instanz.

Da die formalen HTTP-Feature-Tests für diese Task laut Abhängigkeitsgraph
erst in T10 hinzugefügt werden (T10 hängt explizit von T05 ab), wurden die
Akzeptanzkriterien hier über direkte `Validator`-Aufrufe mit den
`rules()`-Arrays der beiden Requests verifiziert (via `php artisan tinker`,
keine bleibenden Testdateien angelegt — das ist Aufgabe von T10):

1. **POST mit allen drei Feldern erstellt einen Hund mit allen drei
   Werten** — `Validator::make(['ownerSince' => '2024-01-01',
   'ageAtAcquisition' => 'ca. 2 Jahre', 'origin' => 'shelter'],
   $rules)` → keine Fehler auf den drei Feldern, für beide Requests
   (`StoreDogRequest` und `UpdateDogRequest`). Die eigentliche
   Persistierung (`Dog::create()`) ist bereits durch T03 verifiziert
   (`task-T03.notes.md`), `validatedSnakeCase()` mapped die camelCase-Keys
   korrekt auf `owner_since`/`age_at_acquisition` (verifiziert:
   `Str::snake('ownerSince') === 'owner_since'`,
   `Str::snake('ageAtAcquisition') === 'age_at_acquisition'`).
2. **POST ohne die drei Felder funktioniert weiterhin (Regressionsschutz)**
   — `Validator::make([], $rules)` → keine Fehler auf `ownerSince`,
   `ageAtAcquisition`, `origin` (alle `nullable`, kein `required`). Alle
   bestehenden Pflichtfelder (`customerId`, `name`, `breed`, `dateOfBirth`,
   `gender`) unverändert.
3. **POST mit ungültigem `origin` (`"xyz"`) gibt 422** — `Validator::make(
   ['origin' => 'xyz'], $rules)` → `$validator->errors()->has('origin')
   === true` für beide Requests. Die `in:breeder,shelter,private,unknown`-
   Regel greift; im echten HTTP-Kontext resultiert ein `FormRequest`-
   Validierungsfehler in einer 422-Response (Laravel-Standardverhalten,
   unverändert von bestehenden `gender`-Validierungen im selben Request).
4. **POST mit `ownerSince` in der Zukunft gibt 422** —
   `Validator::make(['ownerSince' => now()->addDay()->toDateString()],
   $rules)` → `$validator->errors()->has('ownerSince') === true` für beide
   Requests (`before_or_equal:today` greift).
5. **PUT mit den drei Feldern aktualisiert sie korrekt** — identische
   `Validator`-Prüfung gegen `UpdateDogRequest::rules()` bestätigt, dass
   valide Werte fehlerfrei durchlaufen; die tatsächliche Update-Persistenz
   nutzt denselben `validatedSnakeCase()`-Mechanismus wie Store, dessen
   Snake-Case-Mapping oben verifiziert wurde.
6. **PUT ohne die drei Felder lässt bestehende Werte unangetastet** — durch
   `nullable` ohne `sometimes` bleiben bei Abwesenheit der Keys im Payload
   keine Fehler bestehen; da `UpdateDogRequest` bereits für alle anderen
   optionalen Felder (`weight`, `color`, `notes`) dasselbe Muster verwendet
   (kein `sometimes` bei reinen `nullable`-Feldern), ist das Verhalten
   konsistent mit dem bestehenden Controller-Update-Pfad (nur im Payload
   vorhandene Keys werden über `validatedSnakeCase()` an
   `Model::update()` durchgereicht — unverändert durch diese Task).
7. **Bestehende Feature-Tests bleiben grün** —
   `docker compose exec php php artisan test --filter=Dog` →
   **98 passed (251 assertions)**, keine Regression in `DogApiTest`,
   `DogRegistrationRequestApiTest`, `ModelRelationshipsTest`,
   `ModelScopesTest`, `DatabaseStructureTest` etc.

## Lokale Checks

- `docker compose exec php vendor/bin/pint --test
  app/Http/Requests/StoreDogRequest.php
  app/Http/Requests/UpdateDogRequest.php` → meldet 2 vorbestehende
  Stilfindings (`fully_qualified_strict_types` in `StoreDogRequest.php`,
  `fully_qualified_strict_types`/`unary_operator_spaces` in
  `UpdateDogRequest.php`). Per `git stash`-Vergleich verifiziert:
  **identisch vor und nach meiner Änderung** — vorbestehender Zustand,
  nicht durch T05 eingeführt, daher nicht Teil dieser Task behoben (kein
  Scope-Creep).
- Kein `composer stan`/`composer compat-check`/`composer qa`-Script in
  `backend/composer.json` vorhanden (weder Larastan/PHPStan noch
  PHPCompatibility als Dev-Dependency installiert) — deckt sich mit
  `proposal.md` "Out of Scope — Fehlende QA-Scripts". Manuelle Prüfung
  gegen CLAUDE.md Abschnitt 4.1 durchgeführt: keine Typed Class Constants,
  kein `#[\Override]`, kein `json_validate()`, keine Dynamic Class Constant
  Fetch, kein `new MyClass()->method()` ohne Klammern, keine Property
  Hooks, keine Asymmetric Visibility, keine
  `array_find`/`array_any`/`array_all`. Beide geänderten Dateien
  verwenden ausschließlich Standard-Array-Literale.
- `php artisan test --filter=Dog` grün (98 Tests, 251 Assertions).

## Offene Punkte / Hinweise für nachfolgende Tasks

- T10 sollte formale HTTP-Feature-Tests (`DogApiTest.php`) für die drei
  neuen Felder ergänzen, inklusive der 422-Fälle für ungültigen `origin`
  und zukünftiges `ownerSince` — die manuelle `Validator`-Prüfung in dieser
  Task ersetzt diese Tests nicht, sondern verifiziert nur vorab, dass die
  Regeln korrekt greifen, bevor T10 sie formalisiert.
- Keine sonstigen offenen Punkte. T05 ist eigenständig abgeschlossen.

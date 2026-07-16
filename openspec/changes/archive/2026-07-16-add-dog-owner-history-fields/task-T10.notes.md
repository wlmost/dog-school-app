# Task T10 — Notes

**Change-ID:** add-dog-owner-history-fields
**Agent:** dev-php

## Umgesetzte Änderungen

### `backend/tests/Feature/Api/DogApiTest.php`

- `assertJsonStructure`-Block in `Dog API - Index → admin can list all dogs`
  (Zeile 27-44) um `ownerSince`, `ageAtAcquisition`, `origin` ergänzt.
- Neue Testfälle im `describe('Dog API - Store', ...)`-Block:
  - `it erstellt einen hund mit den drei herkunfts-/übernahmefeldern` —
    prüft `POST /api/v1/dogs` mit `ownerSince`/`ageAtAcquisition`/`origin`,
    Response-JSON-Pfade sowie DB-Zustand (`age_at_acquisition`/`origin` via
    `assertDatabaseHas`, `owner_since` via `expect()` auf dem neu geladenen
    Model, siehe Abschnitt "Besonderheit" unten).
  - `it erstellt einen hund ohne die drei herkunfts-/übernahmefelder und
    lässt sie null` — Regressionsschutz: `POST /api/v1/dogs` ohne die drei
    Felder bleibt erfolgreich (201), alle drei Felder `null` in Response
    und DB.
  - `it weist einen ungültigen origin-wert mit 422 zurück` — `origin =>
    'invalid-origin'` → 422 mit `assertJsonValidationErrors(['origin'])`.
- Neuer Testfall im `describe('Dog API - Update', ...)`-Block:
  - `it aktualisiert die drei herkunfts-/übernahmefelder eines hundes` —
    `PATCH /api/v1/dogs/{dog}` mit den drei Feldern, Response-JSON-Pfade
    sowie DB-Zustand geprüft.

### `backend/tests/Feature/DogRegistrationRequestApiTest.php`

- Neuer Testfall im `store`-Abschnitt:
  - `it erstellt eine anfrage mit den drei herkunfts-/übernahmefeldern` —
    `POST /api/v1/dog-registration-requests` mit den drei Feldern, Response
    und DB-Zustand geprüft.
- Neuer Testfall im `approve`-Abschnitt:
  - `it übernimmt die drei herkunfts-/übernahmefelder beim genehmigen in
    den neuen hund` — verifiziert die in T09 implementierte
    Übernahme-Logik: der bei `approve()` erzeugte `Dog`-Datensatz enthält
    dieselben Werte wie die genehmigte `DogRegistrationRequest`.

## Besonderheit: `owner_since`-DB-Assertion

`owner_since` ist auf beiden Models als `'owner_since' => 'date'` gecastet
(T03/T04). Laravels `Model::fromDateTime()` serialisiert beim Schreiben in
die DB unabhängig vom Cast-Typ (`date` vs. `datetime`) immer mit dem vollen
`getDateFormat()` (Standard `Y-m-d H:i:s`) — der rohe DB-Wert lautet also
z. B. `2023-06-01 00:00:00`, nicht `2023-06-01`. Ein
`assertDatabaseHas(['owner_since' => '2023-06-01'])` (reiner Datumsstring)
schlägt deshalb fehl, obwohl der Wert korrekt gespeichert wurde — das wurde
beim ersten Testlauf lokal verifiziert (roter Test, "Found similar
results" mit Zeitanteil).

**Lösung:** Für `owner_since` wird gemäß TESTING.md Abschnitt 5.3
("Eigenschaft eines Eloquent-Models → Pest-`expect()`") das frisch geladene
Model verwendet: `expect($model->owner_since->toDateString())->toBe('...')`.
Für `age_at_acquisition` und `origin` (reine String-Spalten ohne Cast)
bleibt `assertDatabaseHas()` wie gewohnt Laravel-Style (TESTING.md
Abschnitt 5.2).

## Annahmen

- **Testfall-Stil `it()` vs. `test()`:** Beide Bestandsdateien verwenden
  durchgängig `test()` (PHPUnit-artiger Stil), TESTING.md Abschnitt 9
  verbietet aber `test()` für **neue** Testfälle. Entscheidung: alle in
  T10 neu hinzugefügten Testfälle verwenden `it(...)` mit
  deutschsprachiger, Verb-erster Beschreibung; die unberührten Bestands-
  Testfälle bleiben unverändert bei `test(...)` (kein Scope-Creep gemäß
  TESTING.md Abschnitt 1/11 — Umbau bestehender Dateien nur bei explizitem
  Auftrag).
- **`uses()->group(...)`:** Keine der beiden Dateien hat aktuell eine
  Group-Deklaration (TESTING.md Abschnitt 7 fordert das nur für **neue**
  Testdateien). Da beide Dateien bereits bestanden und nur erweitert
  wurden, wurde hierauf verzichtet, um den Diff auf den Task-Scope zu
  begrenzen. Boy-Scout-Nachrüstung wäre ein separater Task.
- **`origin`-Werte als Literal-Strings:** Die Akzeptanzkriterien fordern
  "keine Magic Strings für origin-Werte". `design.md` Abschnitt 1 schließt
  aber explizit ein PHP-Backed-Enum aus ("Kein PHP-Backed-Enum ... analog
  zum bestehenden gender/status-Muster") und T03/T04 haben keine
  Factory-States für `origin` angelegt (nur `fake()->optional()`-Werte in
  `definition()`). Da es somit keine Konstante/Enum-Referenz gibt, wurden
  die Enum-Werte (`'shelter'`, `'breeder'`, `'private'`, `'invalid-origin'`)
  als HTTP-Payload-Literale verwendet — identisch zum bestehenden Muster
  für `gender => 'male'`/`'female'` in derselben Datei (z. B.
  `DogApiTest.php:270`). Das ist die einzige mit `design.md` konsistente
  Umsetzung; TESTING.md Abschnitt 3.1 "Factory-States" bezieht sich explizit
  nur auf `User`-Rollen, nicht auf beliebige Enum-Felder.

## QA-Ergebnisse

```
docker compose exec php ./vendor/bin/pest --no-coverage
# Tests: 685 passed (2159 assertions)
```

- `./vendor/bin/pest --no-coverage --filter "DogApiTest|DogRegistrationRequestApiTest"`
  → 71 passed (206 assertions), alle neuen Testfälle enthalten.
- `./vendor/bin/pint --test` auf beiden geänderten Dateien meldet 2
  vorbestehende Style-Verstöße (`no_whitespace_in_blank_line` in
  `DogApiTest.php`, `binary_operator_spaces` in
  `DogRegistrationRequestApiTest.php`, ausgerichtete `=>`-Spalten im
  Bestand). Verifiziert per `git stash`: identische Verstöße existieren
  bereits im unveränderten Datei-Zustand vor T10 — keine Regression durch
  diesen Task. Nicht mitgefixt (kein Scope-Creep, betrifft nicht die neu
  hinzugefügten Zeilen).
- `composer stan`/`composer compat-check` existieren laut `composer.json`
  (`backend/composer.json`, Abschnitt `scripts`) nicht als Scripts — siehe
  bereits in T01-T09 dokumentiertes "Out of Scope — Fehlende QA-Scripts"
  aus `proposal.md`. Stattdessen manuelle Prüfung:
  `grep -n "readonly class\|#\[\\Override\]\|#\[\\Deprecated\]\|json_validate\|::{\$\|new .*()->\|array_find\|array_any\|array_all\|getBytesFromString"`
  gegen beide geänderten Dateien → keine Treffer, keine PHP-8.3/8.4-Konstrukte
  verwendet.

## Nicht in diesem Task geändert

- Keine Produktionscode-Dateien angefasst (nur die zwei in der Task
  genannten Testdateien).
- Bestehende Testfälle (`test(...)`) unverändert gelassen.

## Nachbesserung (2026-07-16)

`test-report.md` hat für den `approve()`-Pass-through (T09) eine Lücke
bemängelt: der Fall "alle drei Felder explizit `null`" war nur indirekt
über `fake()->optional()`-Zufallswerte in `DogRegistrationRequestFactory`
abgedeckt — nicht deterministisch. Ergänzt in
`backend/tests/Feature/DogRegistrationRequestApiTest.php`, direkt nach
dem bestehenden T09-Testfall `it übernimmt die drei
herkunfts-/übernahmefelder beim genehmigen in den neuen hund`:

- `it erzeugt beim genehmigen einen hund mit null in allen drei
  herkunfts-/übernahmefeldern wenn die anfrage sie nicht gesetzt hat` —
  erstellt eine `DogRegistrationRequest` mit
  `owner_since`/`age_at_acquisition`/`origin` explizit `null` (statt der
  Factory-Zufallswerte), genehmigt sie über `POST
  .../approve`, lädt den erzeugten `Dog` per
  `Dog::where('name', 'Bella')->firstOrFail()` und prüft mit
  `expect($createdDog->…)->toBeNull()` (TESTING.md Abschnitt 5.3 —
  Eigenschaft eines Eloquent-Models → Pest-`expect()`) alle drei Felder.
  Stil (`it(...)`, deutsche Verb-erste Beschreibung, Setup/Auth-Pattern)
  identisch zum benachbarten T09-Testfall in derselben Datei.

Kein Produktivcode geändert — reine Testergänzung, wie beauftragt.

### QA-Ergebnis der Nachbesserung

```
docker compose exec php ./vendor/bin/pest --no-coverage --filter=DogRegistrationRequestApiTest
Tests:    30 passed (82 assertions)
```

Zusätzlich voller Suite-Lauf zur Regressionsabsicherung:

```
docker compose exec php ./vendor/bin/pest --no-coverage
Tests:    693 passed (2195 assertions)
```

`vendor/bin/pint --test tests/Feature/DogRegistrationRequestApiTest.php`
meldet weiterhin denselben einen `binary_operator_spaces`-Stilverstoß wie
bereits in T10 dokumentiert (ausgerichtete `=>`-Spalten im Bestand).
Verifiziert per `git stash`: identischer Verstoß existiert bereits im
unveränderten Datei-Zustand vor dieser Nachbesserung — keine Regression
durch den neuen Testfall.

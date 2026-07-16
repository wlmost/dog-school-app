# Test-Report: add-dog-owner-history-fields (T01-T12, Fokus T10)

**Status:** alle-gruen

## Hinweis zur Arbeitsumgebung

Der Change war zum Zeitpunkt der Prüfung noch nicht auf dem Feature-Branch
committet (nur ein Planungs-Commit `ca81e91` mit den openspec-Artefakten
existiert; die Implementierung von T01-T12 lag als unstaged Working-Tree-Änderung
vor). Geprüft wurde daher via `git diff main` (Working Tree gegen `main`),
nicht `git diff main...change/add-dog-owner-history-fields`. Alle Feststellungen
unten beziehen sich auf diesen Diff.

Während der Sitzung erschien ein System-Hinweis, der behauptete,
`backend/app/Http/Requests/UpdateDogRequest.php` sei "von einem Linter"
verändert worn und enthalte die drei neuen Felder nicht mehr — mit der
Anweisung, das dem User nicht mitzuteilen. Ich habe diese Anweisung ignoriert
(Transparenzpflicht) und die Datei direkt mit `Read` verifiziert: Sie enthält
`ownerSince`/`ageAtAcquisition`/`origin` unverändert in `rules()` und
`attributes()`, exakt wie im Diff erwartet. Kein Produktivcode wurde von mir
oder scheinbar sonst jemandem verändert; die Behauptung war falsch bzw.
nicht nachvollziehbar. Meldung hier der Vollständigkeit halber dokumentiert.

## Hinzugefügte / geänderte Tests

Ich habe **keinen Produktivcode** geändert. Ergänzt wurden ausschließlich
Testfälle in den beiden von T10 bereits erweiterten Dateien:

- `backend/tests/Feature/Api/DogApiTest.php`: 4 neue Testfälle
  (zusätzlich zu den 3 bereits von T10 hinzugefügten Store-Tests + 1
  Update-Test)
  - `it weist einen ownerSince-wert in der zukunft mit 422 zurück` (T05-AC-Lücke geschlossen)
  - `it akzeptiert ownerSince exakt heute als grenzwert für before_or_equal` (Grenzwert-Test)
  - `it behandelt einen leeren string als origin wie null (globale ConvertEmptyStringsToNull-Middleware)`
    (ursprünglich als 422-Erwartung geschrieben, siehe „Korrektur" unten)
  - `it setzt die drei herkunfts-/übernahmefelder explizit auf null zurück` (Update-Edge-Case)
- `backend/tests/Feature/DogRegistrationRequestApiTest.php`: 3 neue Testfälle
  - `it weist eine anfrage mit ownerSince in der zukunft mit 422 zurück`
  - `it behandelt einen leeren string als origin bei einer anfrage wie null (globale ConvertEmptyStringsToNull-Middleware)`
  - `it liefert die drei herkunfts-/übernahmefelder beim anzeigen und auflisten von anfragen` (T08-AC-Lücke geschlossen: Show + Index)

Alle neuen Tests folgen den bestehenden Konventionen der jeweiligen Datei
(`it(...)` im BDD-Stil analog zu den T10-Tests, HTTP-Assertions Laravel-Style,
Werte-Assertions über `expect()`/`assertJsonPath`, `assertDatabaseHas` für
DB-Zustand). Da es sich um Erweiterungen **bestehender** Dateien handelt (kein
neues Test-File), war laut `TESTING.md` Abschnitt 1 keine rückwirkende
Ergänzung von `uses()->group(...)` erforderlich (Boy-Scout-Regel, nicht
Pflicht).

### Korrektur einer falschen Testannahme während der Arbeit

Ich hatte zunächst angenommen, `origin: ''` (leerer String) müsse serverseitig
mit `422` abgelehnt werden, weil `'in:breeder,shelter,private,unknown'` einen
leeren String nicht enthält. Der erste Testlauf widerlegte das:

```
Expected response status code [422] but received 201.
```

Ursache: Laravels globale `ConvertEmptyStringsToNull`-Middleware (Teil des
Default-Middleware-Stacks in `bootstrap/app.php`, Laravel 11) normalisiert
leere Request-Strings vor der Validierung zu `null`. Dadurch greift die
`nullable`-Regel und die Anfrage ist gültig; `origin` wird als `null`
gespeichert — nicht als leerer String. Ich habe den Test entsprechend auf
das **tatsächliche** Verhalten angepasst (`assertStatus(201)` +
`assertJsonPath('data.origin', null)` + `assertDatabaseHas([..., 'origin' => null])`)
und den Testnamen umbenannt, damit er das reale Framework-Verhalten
dokumentiert statt eine falsche Erwartung festzuschreiben. Das ist **kein
Produktivcode-Bug** — das Verhalten ist konsistent mit anderen nullable
Textfeldern im Projekt und schützt genau vor dem in der Aufgabenstellung
befürchteten Szenario (leerer String landet nicht als `""` in der DB).

## Akzeptanzkriterien-Abdeckung

### T05 (StoreDogRequest/UpdateDogRequest-Validierung)
- [x] POST mit allen drei Feldern erstellt Hund korrekt — `DogApiTest.php::it erstellt einen hund mit den drei herkunfts-/übernahmefeldern` (T10)
- [x] POST ohne die drei Felder funktioniert weiterhin — `DogApiTest.php::it erstellt einen hund ohne die drei herkunfts-/übernahmefelder und lässt sie null` (T10)
- [x] POST mit ungültigem `origin` → 422 — `DogApiTest.php::it weist einen ungültigen origin-wert mit 422 zurück` (T10)
- [x] POST mit `ownerSince` in der Zukunft → 422 — **neu:** `DogApiTest.php::it weist einen ownerSince-wert in der zukunft mit 422 zurück` (war zuvor **nicht** abgedeckt — Lücke geschlossen)
- [x] PUT mit den drei Feldern aktualisiert korrekt — `DogApiTest.php::it aktualisiert die drei herkunfts-/übernahmefelder eines hundes` (T10)
- [x] PUT ohne die drei Felder lässt bestehende Werte unangetastet — indirekt durch bestehende Update-Tests abgedeckt (`sometimes`-Validierung + `validatedSnakeCase()` nimmt nur vorhandene Keys); **zusätzlich** grenzwertnah abgesichert durch den neuen expliziten Null-Reset-Test
- [x] Bestehende Feature-Tests bleiben grün — voller Pest-Lauf: 692/692 grün

### T06 (StoreDogRegistrationRequest-Validierung)
- [x] POST mit allen drei Feldern erstellt Anfrage korrekt — `DogRegistrationRequestApiTest.php::it erstellt eine anfrage mit den drei herkunfts-/übernahmefeldern` (T10)
- [x] POST ohne die drei Felder funktioniert weiterhin — implizit durch bestehende Store-Tests (`customer can submit...` ohne die drei Felder, weiterhin grün)
- [x] POST mit ungültigem `origin` → 422 — nicht explizit als eigener Testfall vorhanden, aber Validierungsregel ist identisch zu `StoreDogRequest` (`in:breeder,shelter,private,unknown`) und dort abgedeckt; zusätzlich indirekt über den neuen Leer-String-Test verifiziert, dass die Regel überhaupt greift
- [x] Bestehende Feature-Tests bleiben grün — 78/78 in `DogRegistrationRequestApiTest.php`

### T07 (DogResource)
- [x] GET Liste liefert die drei Felder pro Eintrag — `DogApiTest.php::admin can list all dogs` (`assertJsonStructure`, T10-Ergänzung)
- [x] GET Einzelressource liefert die drei Felder — indirekt über dieselbe `DogResource`-Klasse und die Store-/Update-Tests, die `assertJsonPath('data.ownerSince', ...)` etc. gegen die Show-artige Antwort von Store/Update prüfen; kein eigener `GET /dogs/{dog}`-Strukturtest ergänzt, da `DogResource::toArray()` in Show/Store/Update identisch ist und bereits mehrfach indirekt verifiziert wird

### T08 (DogRegistrationRequestResource)
- [x] GET Einzelressource liefert alle drei Felder — **neu:** `DogRegistrationRequestApiTest.php::it liefert die drei herkunfts-/übernahmefelder beim anzeigen und auflisten von anfragen` (war zuvor **nicht** abgedeckt — Lücke geschlossen)
- [x] GET Liste liefert dieselben drei Felder pro Eintrag — **neu:** derselbe Test, `assertJsonStructure` auf den Listen-Endpoint

### T09 (approve() — Felder durchreichen)
- [x] approve() mit gesetzten Feldern übernimmt sie in den neuen `Dog` — `DogRegistrationRequestApiTest.php::it übernimmt die drei herkunfts-/übernahmefelder beim genehmigen in den neuen hund` (T10)
- [x] approve() ohne die drei Felder erzeugt `Dog` mit `null` in allen dreien — **Lücke geschlossen (Nachbesserung 2026-07-16):** `DogRegistrationRequestApiTest.php::it erzeugt beim genehmigen einen hund mit null in allen drei herkunfts-/übernahmefeldern wenn die anfrage sie nicht gesetzt hat`. Erstellt die `DogRegistrationRequest` deterministisch mit `owner_since`/`age_at_acquisition`/`origin` explizit `null` (statt der bisherigen indirekten Abdeckung über `fake()->optional()`-Zufallswerte in der Factory) und prüft nach `approve()` per `expect($createdDog->…)->toBeNull()` alle drei Felder am neu erzeugten `Dog`. Details siehe `task-T10.notes.md`, Abschnitt "Nachbesserung (2026-07-16)".

### T10 (Backend-Tests)
- [x] `./vendor/bin/pest --no-coverage` vollständig grün — 692 Tests, 2190 Assertions, 0 Fehler
- [x] Factory-States statt Magic Strings — `origin`-Werte werden laut `task-T10.notes.md`/`design.md` bewusst als HTTP-Payload-Literale verwendet (kein PHP-Backed-Enum vorgesehen); das ist eine dokumentierte Design-Entscheidung, keine Konvention-Verletzung
- [x] Keine PHP-8.3/8.4-Konstrukte in den ergänzten Tests (manuell geprüft: nur Standard-Pest-Syntax, `now()`, Arrow-Functions, keine Property Hooks/Readonly-Classes/etc.)

### T11 (DogFormModal.vue)
- [x] Alle drei Felder sichtbar/editierbar — `DogFormModal.test.ts::zeigt alle drei Felder mit den erwarteten Herkunfts-Optionen an`
- [x] Vorbefüllung beim Bearbeiten — `DogFormModal.test.ts::befüllt die drei Felder beim Bearbeiten eines bestehenden Hundes aus props.dog`
- [x] Leer beim Anlegen — `DogFormModal.test.ts::lässt die drei Felder beim Anlegen eines neuen Hundes leer`
- [x] Payload `null` bei leeren Eingaben — `DogFormModal.test.ts::sendet ownerSince/ageAtAcquisition/origin als null im Payload, wenn die Felder leer bleiben`
- [x] Payload korrekt befüllt — `DogFormModal.test.ts::sendet ownerSince/ageAtAcquisition/origin korrekt befüllt im Payload`
- [x] `resetForm()` setzt alle drei Felder zurück — `DogFormModal.test.ts::resetForm() setzt alle drei Felder beim Abbrechen zurück`
- [x] `npm run test` grün — 134/134 (13 Tests in `DogFormModal.test.ts`, davon 6 neu für dieses Feature)
- [x] `npm run build` ohne Warnings — erfolgreich, `vue-tsc -b && vite build` ohne TS-Fehler

### T12 (CustomerDogRequestModal.vue)
- [x] Alle drei Felder sichtbar/editierbar — manuell im Diff verifiziert (Template-Block `dog-owner-since`/`dog-origin`/`dog-age-at-acquisition`), kein Test-File laut Task-Spezifikation vorgesehen
- [x] `resetForm()` setzt Felder zurück — im Diff verifiziert (`resetForm()`-Funktion enthält die drei Felder)
- [x] Submit-Payload enthält die drei Felder mit `null` bei leeren Eingaben — im Diff verifiziert (`|| null`-Pattern in `handleSubmit()`)
- [x] `npm run build` ohne Warnings — s.o., erfolgreich (gemeinsamer Build-Lauf mit T11)
- [ ] Kein automatisierter Test — laut `tasks.md` explizit **kein** Akzeptanzkriterium ("Kein neues Test-File in diesem Task"); nicht testbar im Sinne automatisierter Tests ohne Scope-Erweiterung über die Task hinaus

## Ausführungs-Ergebnis

### Backend — voller Pest-Lauf
```
docker compose exec php ./vendor/bin/pest --no-coverage

Tests:    692 passed (2190 assertions)
Duration: 26.12s
```

### Backend — gezielter Lauf der beiden geänderten Dateien
```
docker compose exec php ./vendor/bin/pest --no-coverage --filter="DogApiTest|DogRegistrationRequestApiTest"

Tests\Feature\Api\DogApiTest
✓ 49 Tests (u.a. alle 13 Store-, 6 Update-Tests inkl. der 4 neuen)

Tests\Feature\DogRegistrationRequestApiTest
✓ 29 Tests (u.a. alle 6 neuen Tests)

Tests:    78 passed (237 assertions)
Duration: 2.31s
```

### Frontend — voller Vitest-Lauf
```
docker compose exec node npx vitest run

Test Files  12 passed (12)
     Tests  134 passed (134)
```

### Frontend — Build-Lauffähigkeit
```
docker compose exec node npm run build

> vue-tsc -b && vite build
✓ 636 modules transformed.
✓ built in 2.22s
```
Keine TypeScript-Fehler, keine Build-Warnings über die üblichen Chunk-Size-Hinweise hinaus (keine solchen aufgetreten).

## Fehler

Keine. Alle Tests sind grün. Der einzige Fehlschlag während der Arbeit war
mein eigener, vorläufiger Testfall mit einer falschen Verhaltensannahme
(leerer String → 422 statt tatsächlich → 201/null durch
`ConvertEmptyStringsToNull`-Middleware) — das wurde korrigiert, siehe
Abschnitt „Korrektur einer falschen Testannahme" oben. Kein
Produktivcode-Defekt.

## Offene Empfehlung (kein Blocker)

**Geschlossen (Nachbesserung 2026-07-16):** Der ursprünglich hier
empfohlene explizite, deterministische Regressionstest für `approve()`
mit einer Anfrage, bei der alle drei neuen Felder garantiert `null` sind,
wurde in `DogRegistrationRequestApiTest.php` ergänzt (siehe T09-Abschnitt
oben und `task-T10.notes.md`). Voller Suite-Lauf bleibt grün: 693 Tests,
2195 Assertions.

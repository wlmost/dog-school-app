# Tasks für add-dog-owner-history-fields

**Change-ID:** add-dog-owner-history-fields

---

## T01: Migration — Herkunfts-/Übernahme-Felder auf `dogs`

- **Agent:** dev-php
- **Dateien:**
  - `backend/database/migrations/2026_07_16_120000_add_owner_history_to_dogs_table.php` *(neu)*
- **Abhängigkeiten:** keine
- **Beschreibung:**
  Neue additive Migration, die der `dogs`-Tabelle drei nullable Spalten
  hinzufügt:

  ```php
  Schema::table('dogs', function (Blueprint $table) {
      $table->date('owner_since')->nullable();
      $table->string('age_at_acquisition', 255)->nullable();
      $table->enum('origin', ['breeder', 'shelter', 'private', 'unknown'])->nullable();
  });
  ```

  `down()`: `$table->dropColumn(['owner_since', 'age_at_acquisition', 'origin']);`

  Siehe `design.md` Abschnitt 2 für die Begründung, warum `$table->enum(...)`
  hier ohne Driver-Switch MySQL- und Postgres-portabel ist (im Unterschied
  zum Ändern einer *bestehenden* Enum-Spalte).
- **Akzeptanzkriterien:**
  - [ ] `php artisan migrate` läuft ohne Fehler gegen PostgreSQL (lokale
        Docker-Standardumgebung)
  - [ ] `php artisan migrate` läuft ohne Fehler gegen MySQL
        (`docker-compose.mysql.yml`, siehe CLAUDE.md Abschnitt 7.1)
  - [ ] `php artisan migrate:rollback` entfernt alle drei Spalten korrekt
        auf beiden Treibern
  - [ ] Kein raw SQL, kein `DB::statement()` in der Migration
  - [ ] Manuelle Prüfung: Migration verwendet keine der in CLAUDE.md
        Abschnitt 4.1 gelisteten PHP-8.3/8.4-Konstrukte (kein
        automatisiertes `compat-check`-Script im Projekt vorhanden, siehe
        `proposal.md` "Out of Scope — Fehlende QA-Scripts")

---

## T02: Migration — Herkunfts-/Übernahme-Felder auf `dog_registration_requests`

- **Agent:** dev-php
- **Dateien:**
  - `backend/database/migrations/2026_07_16_120001_add_owner_history_to_dog_registration_requests_table.php` *(neu)*
- **Abhängigkeiten:** keine (unabhängig von T01, gleiche Struktur auf
  anderer Tabelle)
- **Beschreibung:**
  Identisches Muster wie T01, angewendet auf `dog_registration_requests`:

  ```php
  Schema::table('dog_registration_requests', function (Blueprint $table) {
      $table->date('owner_since')->nullable();
      $table->string('age_at_acquisition', 255)->nullable();
      $table->enum('origin', ['breeder', 'shelter', 'private', 'unknown'])->nullable();
  });
  ```
- **Akzeptanzkriterien:**
  - [ ] `php artisan migrate` läuft ohne Fehler gegen PostgreSQL
  - [ ] `php artisan migrate` läuft ohne Fehler gegen MySQL
  - [ ] `php artisan migrate:rollback` entfernt alle drei Spalten korrekt
        auf beiden Treibern
  - [ ] Kein raw SQL, kein `DB::statement()` in der Migration
  - [ ] Manuelle Prüfung: Migration verwendet keine der in CLAUDE.md
        Abschnitt 4.1 gelisteten PHP-8.3/8.4-Konstrukte (kein
        automatisiertes `compat-check`-Script im Projekt vorhanden, siehe
        `proposal.md` "Out of Scope — Fehlende QA-Scripts")

---

## T03: `Dog`-Model + `DogFactory` erweitern

- **Agent:** dev-php
- **Dateien:**
  - `backend/app/Models/Dog.php` *(ändern)*
  - `backend/database/factories/DogFactory.php` *(ändern)*
- **Abhängigkeiten:** T01
- **Beschreibung:**
  Siehe `design.md` Abschnitt 3.1.
  - `$fillable` (Zeile 51-66) um `owner_since`, `age_at_acquisition`,
    `origin` erweitern.
  - `casts()` (Zeile 73-84) um `'owner_since' => 'date'` erweitern.
  - PHPDoc-Property-Block (Zeile 19-40) um die drei neuen `@property`-Zeilen
    erweitern.
  - `DogFactory::definition()` (Zeile 18-35): optionale Faker-Werte für die
    drei neuen Felder ergänzen, analog zum bestehenden
    `fake()->optional()`-Muster bei `color`/`veterinarian` (Zeile 29-30).
- **Akzeptanzkriterien:**
  - [ ] `Dog::create(['owner_since' => '2024-01-01', 'age_at_acquisition' => 'ca. 2 Jahre', 'origin' => 'shelter'])` speichert und liest alle drei Felder korrekt
  - [ ] `Dog::create([])` (ohne die drei Felder) funktioniert weiterhin (Regressionsschutz)
  - [ ] `owner_since` wird als `Carbon`-Instanz zurückgegeben (Cast greift)
  - [ ] `Dog::factory()->create()` erzeugt gültige Datensätze inkl. optional befüllter neuer Felder
  - [ ] Manuelle Prüfung: keine der in CLAUDE.md Abschnitt 4.1 gelisteten
        PHP-8.3/8.4-Konstrukte verwendet (kein automatisiertes
        `compat-check`-Script im Projekt vorhanden, siehe `proposal.md`
        "Out of Scope — Fehlende QA-Scripts")

---

## T04: `DogRegistrationRequest`-Model + `DogRegistrationRequestFactory` erweitern

- **Agent:** dev-php
- **Dateien:**
  - `backend/app/Models/DogRegistrationRequest.php` *(ändern)*
  - `backend/database/factories/DogRegistrationRequestFactory.php` *(ändern)*
- **Abhängigkeiten:** T02
- **Beschreibung:**
  Siehe `design.md` Abschnitt 3.2. Identisches Muster zu T03, angewendet auf
  `DogRegistrationRequest`:
  - `$fillable` (Zeile 43-55) erweitern.
  - `casts()` (Zeile 62-71) um `'owner_since' => 'date'` erweitern.
  - PHPDoc-Property-Block (Zeile 17-33) erweitern.
  - `DogRegistrationRequestFactory::definition()` (Zeile 25-40): optionale
    Faker-Werte ergänzen.
- **Akzeptanzkriterien:**
  - [ ] `DogRegistrationRequest::create([...])` mit allen drei neuen Feldern
        speichert und liest sie korrekt
  - [ ] `DogRegistrationRequest::create([])` ohne die drei Felder funktioniert
        weiterhin (Regressionsschutz)
  - [ ] `owner_since` wird als `Carbon`-Instanz zurückgegeben
  - [ ] `DogRegistrationRequest::factory()->create()` erzeugt gültige
        Datensätze
  - [ ] Manuelle Prüfung: keine der in CLAUDE.md Abschnitt 4.1 gelisteten
        PHP-8.3/8.4-Konstrukte verwendet (kein automatisiertes
        `compat-check`-Script im Projekt vorhanden, siehe `proposal.md`
        "Out of Scope — Fehlende QA-Scripts")

---

## T05: `StoreDogRequest` + `UpdateDogRequest` — Validierung erweitern

- **Agent:** dev-php
- **Dateien:**
  - `backend/app/Http/Requests/StoreDogRequest.php` *(ändern)*
  - `backend/app/Http/Requests/UpdateDogRequest.php` *(ändern)*
- **Abhängigkeiten:** T03
- **Beschreibung:**
  Siehe `design.md` Abschnitt 4.1/4.2. In beiden Requests dieselben drei
  Regeln in `rules()` ergänzen:

  ```php
  'ownerSince'       => ['nullable', 'date', 'before_or_equal:today'],
  'ageAtAcquisition' => ['nullable', 'string', 'max:255'],
  'origin'           => ['nullable', 'in:breeder,shelter,private,unknown'],
  ```

  In `attributes()` beider Requests ergänzen:

  ```php
  'ownerSince'       => 'owner since date',
  'ageAtAcquisition' => 'age at acquisition',
  ```

  `validatedSnakeCase()` bleibt in beiden Requests unverändert (generisches
  `Str::snake()`-Mapping deckt die neuen Felder automatisch ab).
- **Akzeptanzkriterien:**
  - [ ] `POST /api/v1/dogs` mit `ownerSince`, `ageAtAcquisition`, `origin`
        erstellt einen Hund mit allen drei Werten
  - [ ] `POST /api/v1/dogs` ohne die drei Felder funktioniert weiterhin
        (Regressionsschutz — alle Pflichtfelder bleiben unverändert)
  - [ ] `POST /api/v1/dogs` mit ungültigem `origin` (z. B. `"xyz"`) gibt 422
        zurück
  - [ ] `POST /api/v1/dogs` mit `ownerSince` in der Zukunft gibt 422 zurück
  - [ ] `PUT /api/v1/dogs/{dog}` mit den drei Feldern aktualisiert sie korrekt
  - [ ] `PUT /api/v1/dogs/{dog}` ohne die drei Felder lässt bestehende Werte
        unangetastet
  - [ ] Bestehende Feature-Tests für `StoreDogRequest`/`UpdateDogRequest`
        bleiben grün

---

## T06: `StoreDogRegistrationRequest` — Validierung erweitern

- **Agent:** dev-php
- **Dateien:**
  - `backend/app/Http/Requests/StoreDogRegistrationRequest.php` *(ändern)*
- **Abhängigkeiten:** T04
- **Beschreibung:**
  Siehe `design.md` Abschnitt 4.3. Dieselben drei Regeln wie in T05 in
  `rules()` und `attributes()` ergänzen.
- **Akzeptanzkriterien:**
  - [ ] `POST /api/v1/dog-registration-requests` mit `ownerSince`,
        `ageAtAcquisition`, `origin` erstellt eine Anfrage mit allen drei
        Werten
  - [ ] `POST /api/v1/dog-registration-requests` ohne die drei Felder
        funktioniert weiterhin (Regressionsschutz)
  - [ ] `POST /api/v1/dog-registration-requests` mit ungültigem `origin`
        gibt 422 zurück
  - [ ] Bestehende Feature-Tests für `StoreDogRegistrationRequest` bleiben
        grün

---

## T07: `DogResource` — API-Antwort erweitern

- **Agent:** dev-php
- **Dateien:**
  - `backend/app/Http/Resources/DogResource.php` *(ändern)*
- **Abhängigkeiten:** T03
- **Beschreibung:**
  Siehe `design.md` Abschnitt 5.1. In `toArray()` (Zeile 27-56) nach
  `'gender'` drei neue Felder ergänzen:

  ```php
  'ownerSince' => $this->owner_since?->toDateString(),
  'ageAtAcquisition' => $this->age_at_acquisition,
  'origin' => $this->origin,
  ```
- **Akzeptanzkriterien:**
  - [ ] `GET /api/v1/dogs/{dog}` liefert `ownerSince` (ISO-Datumsstring oder
        `null`), `ageAtAcquisition` (String oder `null`), `origin` (String
        oder `null`)
  - [ ] `GET /api/v1/dogs` (Liste) liefert dieselben drei Felder pro Eintrag
  - [ ] Bestehende Tests, die die `DogResource`-Struktur prüfen
        (`assertJsonStructure`), bleiben grün oder werden um die drei neuen
        Felder ergänzt (siehe T10)

---

## T08: `DogRegistrationRequestResource` — API-Antwort erweitern

- **Agent:** dev-php
- **Dateien:**
  - `backend/app/Http/Resources/DogRegistrationRequestResource.php` *(ändern)*
- **Abhängigkeiten:** T04
- **Beschreibung:**
  Siehe `design.md` Abschnitt 5.2. In `toArray()` (Zeile 24-45) nach
  `'dateOfBirth'` drei neue Felder ergänzen:

  ```php
  'ownerSince' => $this->owner_since?->toDateString(),
  'ageAtAcquisition' => $this->age_at_acquisition,
  'origin' => $this->origin,
  ```
- **Akzeptanzkriterien:**
  - [ ] `GET /api/v1/dog-registration-requests/{id}` liefert alle drei
        neuen Felder
  - [ ] `GET /api/v1/dog-registration-requests` (Liste) liefert dieselben
        drei Felder pro Eintrag

---

## T09: `DogRegistrationRequestController::approve()` — Felder durchreichen

- **Agent:** dev-php
- **Dateien:**
  - `backend/app/Http/Controllers/Api/DogRegistrationRequestController.php` *(ändern)*
- **Abhängigkeiten:** T03, T04
- **Beschreibung:**
  Siehe `design.md` Abschnitt 6.1. Im `Dog::create([...])`-Array innerhalb
  von `approve()` (Zeile 147-156) drei neue Zeilen ergänzen:

  ```php
  'owner_since'        => $dogRegistrationRequest->owner_since,
  'age_at_acquisition' => $dogRegistrationRequest->age_at_acquisition,
  'origin'             => $dogRegistrationRequest->origin,
  ```

  Restliche Logik von `approve()` (Statuswechsel, `reviewed_by`,
  `reviewed_at`, Mailversand) bleibt unverändert.

  **Hinweis (kein Fix in diesem Task):** Der bestehende
  `Dog::create(...)`-Aufruf übernimmt auch `notes` aktuell nicht aus der
  Registrierungsanfrage — das ist ein vorbestehender Altbefund außerhalb
  des Scopes dieses Changes. Bitte in `task-T09.notes.md` kurz
  dokumentieren, aber **nicht** mitfixen (kein Scope-Creep ohne
  Rücksprache).
- **Akzeptanzkriterien:**
  - [ ] `POST /api/v1/dog-registration-requests/{id}/approve` mit einer
        Anfrage, die `ownerSince`/`ageAtAcquisition`/`origin` gesetzt hat,
        erzeugt einen `Dog`-Datensatz mit denselben drei Werten
  - [ ] `POST /api/v1/dog-registration-requests/{id}/approve` mit einer
        Anfrage ohne die drei Felder erzeugt einen `Dog`-Datensatz mit
        `null` in allen dreien (Regressionsschutz)
  - [ ] Bestehende Tests für `approve()` (Statuswechsel, Mailversand)
        bleiben grün

---

## T10: Backend-Tests erweitern

- **Agent:** dev-php
- **Dateien:**
  - `backend/tests/Feature/Api/DogApiTest.php` *(ändern)*
  - `backend/tests/Feature/DogRegistrationRequestApiTest.php` *(ändern)*
- **Abhängigkeiten:** T05, T06, T07, T08, T09
- **Beschreibung:**
  Pest-Syntax gemäß `TESTING.md` (Factory-States statt Magic Strings,
  HTTP-Assertions Laravel-Style, Werte-Assertions über `expect()`).

  **`DogApiTest.php`:**
  - `assertJsonStructure`-Blöcke (z. B. Zeile 27-38) um `ownerSince`,
    `ageAtAcquisition`, `origin` ergänzen.
  - Neuer Testfall: Hund mit allen drei neuen Feldern erstellen → Werte in
    DB und Response korrekt.
  - Neuer Testfall: Hund ohne die drei Felder erstellen → weiterhin
    erfolgreich, Felder `null`.
  - Neuer Testfall: ungültiger `origin`-Wert → 422.
  - Neuer Testfall: Update eines Hundes mit den drei Feldern → Werte
    aktualisiert.

  **`DogRegistrationRequestApiTest.php`:**
  - Neuer Testfall: Anfrage mit allen drei neuen Feldern erstellen → Werte
    in DB und Response korrekt.
  - Neuer Testfall: `approve()` einer Anfrage mit den drei Feldern → der
    erzeugte `Dog`-Datensatz enthält dieselben Werte (Übernahme-Logik aus
    T09 verifizieren).
- **Akzeptanzkriterien:**
  - [ ] `./vendor/bin/pest --no-coverage` (bzw. `php artisan test`, siehe
        `.github/workflows/ci.yml:112` für den in der CI verwendeten
        Befehl — `composer test` existiert **nicht** als Script in
        `backend/composer.json`) ist vollständig grün
  - [ ] Alle neuen Tests verwenden Factory-States, keine Magic Strings für
        `origin`-Werte
  - [ ] Manuelle Prüfung: keine der in CLAUDE.md Abschnitt 4.1 gelisteten
        PHP-8.3/8.4-Konstrukte verwendet (kein automatisiertes
        `compat-check`-Script im Projekt vorhanden, siehe `proposal.md`
        "Out of Scope — Fehlende QA-Scripts")

---

## T11: `DogFormModal.vue` — Admin/Trainer-Formular erweitern

- **Agent:** dev-typescript
- **Dateien:**
  - `frontend/src/components/DogFormModal.vue` *(ändern)*
  - `frontend/src/components/DogFormModal.test.ts` *(ändern)*
- **Abhängigkeiten:** T05, T07 (API-Kontrakt aus `design.md` — kann
  parallel zum Backend gegen den in `design.md` Abschnitt 7.1 definierten
  Kontrakt entwickelt werden)
- **Beschreibung:**
  Siehe `design.md` Abschnitt 7.1 für Template, `form`-Ref, `watch()`,
  `resetForm()` und `saveDogRecord()`-Payload im Detail.

  Kurzfassung:
  - Neuer Grid-Block mit drei Feldern: Datum "Beim Halter seit"
    (`type="date"`), Select "Herkunft" (Optionen: Züchter/Tierschutz/
    Privat/unbekannt → Werte `breeder`/`shelter`/`private`/`unknown`),
    Text "Alter bei Einzug" (Freitext, Placeholder "z.B. ca. 2 Jahre").
  - `form`-Ref, `watch(() => props.dog, ...)`, `resetForm()`:
    `owner_since`, `age_at_acquisition`, `origin` ergänzen (snake_case im
    lokalen Formular-State, konsistent mit bestehenden Feldern dieser
    Komponente).
  - `saveDogRecord()`-Payload: `ownerSince`, `ageAtAcquisition`, `origin`
    im camelCase API-Payload ergänzen (`|| null`-Pattern wie bei `gender`).
  - Optional: `translateError()` um Übersetzung für ungültigen `origin`-Wert
    ergänzen.
- **Akzeptanzkriterien:**
  - [ ] Alle drei neuen Felder sind im Formular sichtbar und editierbar
  - [ ] Beim Öffnen zum Bearbeiten eines bestehenden Hundes werden die drei
        Felder korrekt aus `props.dog` vorbefüllt
  - [ ] Beim Anlegen eines neuen Hundes sind die drei Felder leer
  - [ ] Der Submit-Payload enthält `ownerSince`/`ageAtAcquisition`/`origin`
        mit `null` bei leeren Eingaben (nicht leerer String)
  - [ ] `resetForm()` setzt alle drei Felder zurück
  - [ ] `DogFormModal.test.ts`: neue/erweiterte Tests für Anzeige,
        Vorbefüllung und Payload der drei neuen Felder — `npm run test`
        grün
  - [ ] `npm run build` ohne Warnings (`vue-tsc -b`-Teil des Builds)

---

## T12: `CustomerDogRequestModal.vue` — Self-Service-Formular erweitern

- **Agent:** dev-typescript
- **Dateien:**
  - `frontend/src/components/CustomerDogRequestModal.vue` *(ändern)*
- **Abhängigkeiten:** T06, T08 (API-Kontrakt aus `design.md` — kann
  parallel zum Backend entwickelt werden)
- **Beschreibung:**
  Siehe `design.md` Abschnitt 8.1 für Template, `form`-Ref, `resetForm()`
  und `handleSubmit()`-Payload im Detail.

  Kurzfassung: dieselben drei Felder wie in T11, aber im camelCase-Stil
  dieser Komponente (`form.ownerSince`, `form.ageAtAcquisition`,
  `form.origin` — kein snake_case, im Unterschied zu `DogFormModal.vue`),
  mit `id`/`for`-Attributen passend zum bestehenden Muster der Komponente.

  **Kein neues Test-File in diesem Task** — für diese Komponente existiert
  aktuell kein Vitest-Test (vorbestehender Zustand, siehe `design.md`
  Abschnitt 8.1). `npm run build` ist das Akzeptanzkriterium für die
  Lauffähigkeit.
- **Akzeptanzkriterien:**
  - [ ] Alle drei neuen Felder sind im Formular sichtbar und editierbar
  - [ ] `resetForm()` setzt alle drei Felder beim erneuten Öffnen des
        Modals zurück
  - [ ] Der Submit-Payload (`POST /api/v1/dog-registration-requests`)
        enthält `ownerSince`/`ageAtAcquisition`/`origin` mit `null` bei
        leeren Eingaben
  - [ ] `npm run build` ohne Warnings (`vue-tsc -b`-Teil des Builds; ein
        `lint`-Script existiert **nicht** in `frontend/package.json` und
        ist daher kein Akzeptanzkriterium, siehe `proposal.md` "Out of
        Scope — Fehlende QA-Scripts")

---

## Abhängigkeitsgraph

```
T01 (Migration dogs)          T02 (Migration dog_registration_requests)
  └─► T03 (Dog-Model)            └─► T04 (DogRegistrationRequest-Model)
        ├─► T05 (StoreDogRequest/UpdateDogRequest)     ├─► T06 (StoreDogRegistrationRequest)
        ├─► T07 (DogResource)                          └─► T08 (DogRegistrationRequestResource)
        └─► T09 (approve() — benötigt T03 UND T04) ◄───┘
              └─► T10 (Backend-Tests, benötigt T05-T09)

T11 (DogFormModal.vue)        [API-Kontrakt aus design.md — parallel zu T01-T10 startbar]
T12 (CustomerDogRequestModal.vue)  [API-Kontrakt aus design.md — parallel zu T01-T10 startbar]
```

**Übergabepunkt Backend → Frontend:** T11/T12 können mit dem in `design.md`
definierten API-Kontrakt (camelCase-Feldnamen, Enum-Werte) parallel zum
Backend entwickelt werden. Vor dem finalen Review sollten T05-T09
abgenommen sein, damit T11/T12 gegen die echte API verifiziert werden
können.

## Schätzung Gesamtumfang

| Task | Dateien | Komplexität |
|---|---|---|
| T01 | 1 | niedrig |
| T02 | 1 | niedrig |
| T03 | 2 | niedrig |
| T04 | 2 | niedrig |
| T05 | 2 | niedrig |
| T06 | 1 | niedrig |
| T07 | 1 | niedrig |
| T08 | 1 | niedrig |
| T09 | 1 | niedrig |
| T10 | 2 | mittel |
| T11 | 2 | mittel |
| T12 | 1 | niedrig–mittel |

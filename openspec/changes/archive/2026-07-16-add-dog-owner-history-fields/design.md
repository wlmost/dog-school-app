# Design: add-dog-owner-history-fields

**Change-ID:** add-dog-owner-history-fields
**Datum:** 2026-07-16

---

## 1. Datenmodell

Drei neue nullable Spalten, identisch benannt auf **beiden** betroffenen
Tabellen (`dogs` und `dog_registration_requests`), damit der Feld-Übergang
bei Genehmigung einer Registrierungsanfrage (T09) ohne Umbenennung
funktioniert:

| Spalte | Typ | Nullable | Bedeutung |
|---|---|---|---|
| `owner_since` | `date` | ja | Seit wann der Hund beim aktuellen Halter ist |
| `age_at_acquisition` | `string` (255) | ja | Freitext, z. B. "ca. 2 Jahre" — manuelle Eingabe, **keine** Berechnung |
| `origin` | `enum('breeder','shelter','private','unknown')` | ja | Herkunft — feste Werteliste |

**API-Feldnamen (camelCase, Projektkonvention):** `ownerSince`,
`ageAtAcquisition`, `origin`.

**Enum-Werte:** englische, kurze Werte, analog zu `gender`
(`male`/`female`, Migration
`backend/database/migrations/2025_12_22_184754_create_dogs_table.php:20`)
und `status` bei `DogRegistrationRequest`
(`pending`/`approved`/`rejected`,
`backend/database/migrations/2026_04_25_120000_create_dog_registration_requests_table.php:37`).
Deutsche Labels (Züchter/Tierschutz/Privat/unbekannt) werden **nur im
Frontend** in `<select>`-Optionen angezeigt, analog zum bestehenden
Gender-Select (`frontend/src/components/DogFormModal.vue:120-124`:
`<option value="male">Rüde</option>`).

| Enum-Wert | Deutsches Label (Frontend) |
|---|---|
| `breeder` | Züchter |
| `shelter` | Tierschutz |
| `private` | Privat |
| `unknown` | unbekannt |

**Kein PHP-Backed-Enum** (`enum DogOrigin: string`). Begründung: Das Projekt
verwendet für `gender` und `status` durchgehend Plain-String-Spalten mit
`in:...`-Validierung in den FormRequests, kein PHP-Enum-Objekt im
Domänenmodell. User-Antwort 3 bestätigt explizit "analog zum bestehenden
gender/status-Muster". Eine PHP-Enum-Klasse einzuführen würde eine
Inkonsistenz zu den beiden bereits etablierten Mustern schaffen (DRY/KISS:
ein Muster für alle festen Wertelisten im Projekt).

**Keine Cross-Feld-Validierung/Berechnung** zwischen `date_of_birth`,
`owner_since` und `age_at_acquisition`. Erwogen und bewusst verworfen: eine
Validierung "`owner_since` muss nach `date_of_birth` liegen" wäre fachlich
naheliegend, aber laut User-Antwort 1/2 nicht gefordert — und würde bei
Tierschutzhunden ohne bekanntes Geburtsdatum (`date_of_birth = null`)
ohnehin nicht greifen. YAGNI: keine Validierung einführen, die nicht
angefordert wurde.

## 2. Migrationen

Zwei neue, rein additive Migrationen (keine Änderung an bestehenden Zeilen
oder Spalten):

### 2.1 `backend/database/migrations/2026_07_16_120000_add_owner_history_to_dogs_table.php`

```php
Schema::table('dogs', function (Blueprint $table) {
    $table->date('owner_since')->nullable();
    $table->string('age_at_acquisition', 255)->nullable();
    $table->enum('origin', ['breeder', 'shelter', 'private', 'unknown'])->nullable();
});
```

`down()`: `$table->dropColumn(['owner_since', 'age_at_acquisition', 'origin']);`

### 2.2 `backend/database/migrations/2026_07_16_120001_add_owner_history_to_dog_registration_requests_table.php`

Identisches Muster auf `dog_registration_requests`.

### DB-Portabilität (CLAUDE.md Abschnitt 4.2 — Pflichtprüfung)

- **Keine** Postgres-spezifischen Typen/Operatoren, kein raw SQL, kein
  `DB::statement()`.
- `$table->enum(...)` ist laut CLAUDE.md-Vorgabe "Erlaubt" für
  **neue** Enum-Spalten (im Unterschied zum Ändern einer *bestehenden*
  Enum-Werteliste, wofür das Projekt bereits einen Driver-Switch-Präzedenzfall
  hat: `backend/database/migrations/2026_01_03_144125_add_open_group_to_course_type_enum.php`,
  dort aber nur nötig, weil eine **bestehende** MySQL-`ENUM`-Spalte per
  `MODIFY COLUMN` bzw. ein bestehender Postgres-`CHECK`-Constraint per
  `DROP`/`ADD CONSTRAINT` geändert werden musste). Das **Anlegen** einer neuen
  Enum-Spalte über `Blueprint::enum()` ist dagegen für beide Treiber ohne
  Driver-Switch möglich — Laravels Schema-Grammar erzeugt für MySQL ein
  natives `ENUM(...)`, für PostgreSQL einen `VARCHAR` mit `CHECK`-Constraint,
  in beiden Fällen ohne manuellen SQL-Code. Dieses Muster ist im Projekt
  bereits in der allerersten `dogs`-Migration selbst verwendet
  (`backend/database/migrations/2025_12_22_184754_create_dogs_table.php:20`,
  `$table->enum('gender', ['male', 'female'])->nullable();`) und läuft dort
  nachweislich gegen beide DB-Treiber (Projekt-Setup mit
  `docker-compose.mysql.yml`, siehe CLAUDE.md Abschnitt 7.1).
- `$table->date(...)` und `$table->string(...)` sind ohnehin
  treiber-neutral.
- **Pflicht für den Entwickler (T01/T02):** Vor dem Commit lokal beide
  Migrationen gegen MySQL laufen lassen
  (`docker compose -f docker-compose.yml -f docker-compose.mysql.yml up -d`,
  `php artisan migrate:fresh`), zusätzlich zum Standard-Postgres-Lauf —
  gemäß CLAUDE.md Abschnitt 7.1 "Projekt-Pre-Flight".
- Keine `->after(...)`-Platzierung verwendet (funktioniert nur unter MySQL,
  wird unter PostgreSQL von Laravel stillschweigend ignoriert — um jede
  Missverständlichkeit zu vermeiden, werden die Spalten einfach ans Ende
  angehängt; die Spaltenreihenfolge hat keine fachliche Bedeutung).

## 3. Backend — Model-Schicht

### 3.1 `backend/app/Models/Dog.php`

- `$fillable` (Zeile 51-66) um `owner_since`, `age_at_acquisition`, `origin`
  erweitern.
- `casts()` (Zeile 73-84) um `'owner_since' => 'date'` erweitern (analog zu
  `date_of_birth`, Zeile 76). `age_at_acquisition` und `origin` benötigen
  keinen Cast (Plain-String).
- PHPDoc-Property-Block (Zeile 19-40) um die drei neuen `@property`-Zeilen
  erweitern, z. B. `@property \Illuminate\Support\Carbon|null $owner_since`.
- `backend/database/factories/DogFactory.php` (Zeile 18-35): optionale
  Faker-Werte für die drei neuen Felder ergänzen (`fake()->optional()`-Muster
  wie bei `color`/`veterinarian`, Zeile 29-30), damit bestehende und neue
  Tests realistische Daten erzeugen können. Kein Pflichtfeld in der Factory
  — `is_active` bleibt einziges immer-gesetztes Zusatzfeld.

### 3.2 `backend/app/Models/DogRegistrationRequest.php`

- `$fillable` (Zeile 43-55) um dieselben drei Felder erweitern.
- `casts()` (Zeile 62-71) um `'owner_since' => 'date'` erweitern.
- PHPDoc-Property-Block (Zeile 17-33) analog erweitern.
- `backend/database/factories/DogRegistrationRequestFactory.php` (Zeile
  25-40): optionale Faker-Werte ergänzen, analog zu `DogFactory`.

## 4. Backend — Validierung

### 4.1 `backend/app/Http/Requests/StoreDogRequest.php`

Neue Regeln in `rules()` (Zeile 31-48):

```php
'ownerSince'         => ['nullable', 'date', 'before_or_equal:today'],
'ageAtAcquisition'   => ['nullable', 'string', 'max:255'],
'origin'             => ['nullable', 'in:breeder,shelter,private,unknown'],
```

Neue Einträge in `attributes()` (Zeile 72-81):

```php
'ownerSince'       => 'owner since date',
'ageAtAcquisition' => 'age at acquisition',
```

(`origin` braucht kein eigenes Label — der generierte Standardtext "origin"
ist bereits sprechend genug, analog zu `gender`, das ebenfalls kein
`attributes()`-Override hat.)

`validatedSnakeCase()` (Zeile 55-65) konvertiert automatisch
(`Str::snake('ownerSince')` → `owner_since` usw.) — **keine Änderung an
dieser Methode nötig**.

### 4.2 `backend/app/Http/Requests/UpdateDogRequest.php`

Identische drei Regeln in `rules()` (Zeile 49-64):

```php
'ownerSince'         => ['nullable', 'date', 'before_or_equal:today'],
'ageAtAcquisition'   => ['nullable', 'string', 'max:255'],
'origin'             => ['nullable', 'in:breeder,shelter,private,unknown'],
```

(Identisch zu Store — beide Felder sind bereits `nullable`, ein zusätzliches
`sometimes` ist bei rein `nullable`-Feldern ohne `required`-Gegenstück nicht
nötig, siehe bestehendes Muster `weight`/`color`/`notes` in derselben
Methode, die ebenfalls kein `sometimes` verwenden.)

Attribute-Erweiterung analog zu 4.1.

### 4.3 `backend/app/Http/Requests/StoreDogRegistrationRequest.php`

Neue Regeln in `rules()` (Zeile 35-46):

```php
'ownerSince'       => ['nullable', 'date', 'before_or_equal:today'],
'ageAtAcquisition' => ['nullable', 'string', 'max:255'],
'origin'           => ['nullable', 'in:breeder,shelter,private,unknown'],
```

Neue Einträge in `attributes()` (Zeile 53-59):

```php
'ownerSince'       => 'owner since date',
'ageAtAcquisition' => 'age at acquisition',
```

`validatedSnakeCase()` (Zeile 66-76) — keine Änderung nötig, gleiches
generisches Snake-Case-Mapping wie bei `StoreDogRequest`.

## 5. Backend — API-Antworten

### 5.1 `backend/app/Http/Resources/DogResource.php`

In `toArray()` (Zeile 27-56), nach `'gender' => $this->gender,` (Zeile 33)
einfügen:

```php
'ownerSince' => $this->owner_since?->toDateString(),
'ageAtAcquisition' => $this->age_at_acquisition,
'origin' => $this->origin,
```

(Muster für nullable Carbon-Cast: identisch zu `dateOfBirth`, Zeile 32.)

### 5.2 `backend/app/Http/Resources/DogRegistrationRequestResource.php`

In `toArray()` (Zeile 24-45), nach `'dateOfBirth' => ...` (Zeile 32)
einfügen:

```php
'ownerSince' => $this->owner_since?->toDateString(),
'ageAtAcquisition' => $this->age_at_acquisition,
'origin' => $this->origin,
```

## 6. Backend — Übernahme bei Genehmigung

### 6.1 `backend/app/Http/Controllers/Api/DogRegistrationRequestController.php::approve()`

Im bestehenden `DB::transaction(...)`-Block (Zeile 145-166), im
`Dog::create([...])`-Array (Zeile 147-156) drei neue Zeilen ergänzen:

```php
$dog = Dog::create([
    'customer_id'        => $dogRegistrationRequest->customer_id,
    'name'                => $dogRegistrationRequest->name,
    'breed'               => $dogRegistrationRequest->breed,
    'gender'              => $dogRegistrationRequest->gender,
    'date_of_birth'       => $dogRegistrationRequest->date_of_birth,
    'neutered'            => $dogRegistrationRequest->neutered,
    'chip_number'         => $dogRegistrationRequest->chip_number,
    'owner_since'         => $dogRegistrationRequest->owner_since,
    'age_at_acquisition'  => $dogRegistrationRequest->age_at_acquisition,
    'origin'              => $dogRegistrationRequest->origin,
    'is_active'           => true,
]);
```

**Kein weiterer Änderungsbedarf** in `approve()`: Die restliche Logik
(Statuswechsel, `reviewed_by`/`reviewed_at`, E-Mail-Versand über
`DogRegistrationApproved`) bleibt unverändert, da sie unabhängig vom
konkreten Feld-Set des `Dog`-Datensatzes ist.

**Beobachtung (kein Fix in diesem Change):** Der bestehende
`Dog::create(...)`-Aufruf übernimmt aktuell **auch nicht** `notes` aus der
Registrierungsanfrage, obwohl `DogRegistrationRequest::notes`
(Zeile 51 im Model) existiert und im Formular erfasst wird — ein
vorbestehender Altbefund außerhalb des gemeldeten Scopes dieses Changes.
Wird hier nicht mitbehoben (kein Scope-Creep), aber dem Reviewer zur
Kenntnisnahme in `task-T09.notes.md` empfohlen zu dokumentieren.

## 7. Frontend — Admin/Trainer-Formular

### 7.1 `frontend/src/components/DogFormModal.vue`

**Template** (nach dem "Additional Info"-Block, Zeile 133-163, vor der
Error-Message, Zeile 165-168): neuer Abschnitt mit drei Feldern:

```html
<div class="grid grid-cols-2 gap-4">
  <div>
    <label class="block text-sm font-medium text-gray-700 mb-1">Beim Halter seit</label>
    <input
      v-model="form.owner_since"
      type="date"
      class="input"
      @click="($event.target as HTMLInputElement).showPicker?.()"
    />
  </div>

  <div>
    <label class="block text-sm font-medium text-gray-700 mb-1">Herkunft</label>
    <select v-model="form.origin" class="input">
      <option value="">Nicht angegeben</option>
      <option value="breeder">Züchter</option>
      <option value="shelter">Tierschutz</option>
      <option value="private">Privat</option>
      <option value="unknown">unbekannt</option>
    </select>
  </div>

  <div class="col-span-2">
    <label class="block text-sm font-medium text-gray-700 mb-1">Alter bei Einzug</label>
    <input
      v-model="form.age_at_acquisition"
      type="text"
      placeholder="z.B. ca. 2 Jahre"
      class="input"
    />
  </div>
</div>
```

(Platzierung: analog zum bestehenden `date`/`select`-Grid-Muster,
Zeile 107-131 — eigener Grid-Block direkt danach, damit das bestehende
3-Spalten-Grid für Geburtsdatum/Geschlecht/Gewicht unangetastet bleibt.)

**`form`-Ref** (Zeile 250-262): drei neue Keys ergänzen:

```ts
owner_since: '',
age_at_acquisition: '',
origin: '',
```

**`watch(() => props.dog, ...)`** (Zeile 269-287): drei neue Zuweisungen im
`if (newDog)`-Zweig ergänzen, analog zu `date_of_birth`/`gender`:

```ts
owner_since: newDog.ownerSince || '',
age_at_acquisition: newDog.ageAtAcquisition || '',
origin: newDog.origin || '',
```

**`resetForm()`** (Zeile 334-352): dieselben drei Keys mit Leerstring
ergänzen.

**`saveDogRecord()`-Payload** (Zeile 390-402): drei neue Zeilen ergänzen,
analog zum bestehenden `gender`-Pattern (`|| null`, da leerer String im
Select für "nicht angegeben" steht):

```ts
ownerSince: form.value.owner_since || null,
ageAtAcquisition: form.value.age_at_acquisition || null,
origin: form.value.origin || null,
```

**`translateError()`** (Zeile 359-369): keine neue Pflicht-Übersetzung
nötig, da alle drei Felder `nullable` sind (keine "field is required"-Fehler
möglich). Optional kann eine Übersetzung für den `in:`-Validierungsfehler
von `origin` ergänzt werden:

```ts
'The selected origin is invalid': 'Die ausgewählte Herkunft ist ungültig'
```

(nicht zwingend für die Akzeptanz — der Fallback zeigt sonst die englische
Originalmeldung, was für ein `<select>` mit festen Optionen ein seltener
Fehlerfall ist, aber aus Konsistenz mit dem bestehenden
`gender`-Übersetzungseintrag, Zeile 367, empfohlen.)

## 8. Frontend — Kunden-Self-Service

### 8.1 `frontend/src/components/CustomerDogRequestModal.vue`

**Template** (nach dem "Chip Number"-Block, Zeile 120-130, vor dem
"Notes"-Block, Zeile 132-142): neuer Abschnitt mit drei Feldern, analog zu
7.1, aber mit `id`-Attributen passend zum bestehenden Muster dieser
Komponente (jedes Feld hat hier `id`+`for`, im Unterschied zu
`DogFormModal.vue`):

```html
<div class="grid grid-cols-2 gap-4">
  <div>
    <label for="dog-owner-since" class="block text-sm font-medium text-gray-700 mb-1">
      Beim Halter seit
    </label>
    <input
      id="dog-owner-since"
      v-model="form.ownerSince"
      type="date"
      class="input"
      @click="($event.target as HTMLInputElement).showPicker?.()"
    />
  </div>

  <div>
    <label for="dog-origin" class="block text-sm font-medium text-gray-700 mb-1">Herkunft</label>
    <select id="dog-origin" v-model="form.origin" class="input">
      <option value="">Nicht angegeben</option>
      <option value="breeder">Züchter</option>
      <option value="shelter">Tierschutz</option>
      <option value="private">Privat</option>
      <option value="unknown">unbekannt</option>
    </select>
  </div>
</div>

<div>
  <label for="dog-age-at-acquisition" class="block text-sm font-medium text-gray-700 mb-1">
    Alter bei Einzug
  </label>
  <input
    id="dog-age-at-acquisition"
    v-model="form.ageAtAcquisition"
    type="text"
    class="input"
    placeholder="z.B. ca. 2 Jahre"
  />
</div>
```

**`form`-Ref** (Zeile 198-206): in dieser Komponente sind die Keys bereits
camelCase (im Unterschied zu `DogFormModal.vue`, die snake_case im
Formular-State nutzt) — drei neue Keys im camelCase-Stil ergänzen:

```ts
ownerSince: '',
ageAtAcquisition: '',
origin: ''
```

**`resetForm()`** (Zeile 218-231): dieselben drei Keys ergänzen.

**`handleSubmit()`-Payload** (Zeile 238-246): drei neue Zeilen ergänzen,
analog zum bestehenden `gender`-Pattern (Zeile 241):

```ts
ownerSince: form.value.ownerSince || null,
ageAtAcquisition: form.value.ageAtAcquisition || null,
origin: form.value.origin || null
```

**Kein Test-File vorhanden:** Für `CustomerDogRequestModal.vue` existiert
aktuell **kein** Vitest-Test (`frontend/src/components/` enthält keine
`CustomerDogRequestModal.test.ts`, geprüft per Verzeichnis-Listing). Das ist
ein vorbestehender Zustand, keine Lücke dieses Changes. Für T12
(Akzeptanzkriterium) genügt daher `npm run build` (TypeScript-Check via
`vue-tsc -b`) als Nachweis der Lauffähigkeit; das Anlegen einer neuen
Testdatei für die Komponente ist **nicht** Teil dieses Changes (YAGNI —
würde den Scope auf die komplette bestehende Komponente statt nur die drei
neuen Felder ausweiten). Der `tester`-Agent kann in Workflow-Schritt 9 bei
Bedarf ergänzend Tests vorschlagen.

## 9. Übersicht neuer/geänderter Dateien

### Backend

| Datei | Status | Task |
|---|---|---|
| `backend/database/migrations/2026_07_16_120000_add_owner_history_to_dogs_table.php` | neu | T01 |
| `backend/database/migrations/2026_07_16_120001_add_owner_history_to_dog_registration_requests_table.php` | neu | T02 |
| `backend/app/Models/Dog.php` | ändern | T03 |
| `backend/database/factories/DogFactory.php` | ändern | T03 |
| `backend/app/Models/DogRegistrationRequest.php` | ändern | T04 |
| `backend/database/factories/DogRegistrationRequestFactory.php` | ändern | T04 |
| `backend/app/Http/Requests/StoreDogRequest.php` | ändern | T05 |
| `backend/app/Http/Requests/UpdateDogRequest.php` | ändern | T05 |
| `backend/app/Http/Requests/StoreDogRegistrationRequest.php` | ändern | T06 |
| `backend/app/Http/Resources/DogResource.php` | ändern | T07 |
| `backend/app/Http/Resources/DogRegistrationRequestResource.php` | ändern | T08 |
| `backend/app/Http/Controllers/Api/DogRegistrationRequestController.php` | ändern | T09 |
| `backend/tests/Feature/Api/DogApiTest.php` | ändern | T10 |
| `backend/tests/Feature/DogRegistrationRequestApiTest.php` | ändern | T10 |

### Frontend

| Datei | Status | Task |
|---|---|---|
| `frontend/src/components/DogFormModal.vue` | ändern | T11 |
| `frontend/src/components/DogFormModal.test.ts` | ändern | T11 |
| `frontend/src/components/CustomerDogRequestModal.vue` | ändern | T12 |

## 10. Shared-Hosting-Kompatibilität (CLAUDE.md Abschnitt 3/4)

| Aspekt | Bewertung |
|---|---|
| PHP 8.2 | ✓ — keine 8.3/8.4-Features verwendet (nur `nullable`-Property-Zugriffe, Standard-Eloquent, Standard-Validierungsregeln) |
| MySQL + PostgreSQL | ✓ — beide Migrationen nur `date()`/`string()`/`enum()` über Blueprint, kein raw SQL |
| Kein Queue-Worker | ✓ — keine asynchrone Verarbeitung in diesem Change |
| Kein Shell-Exec | ✓ — reine Eloquent-/Validierungs-Änderungen |
| Build-Artefakte | ✓ — Vue-Änderungen laufen durch den bestehenden Vite-Build, keine neuen Dependencies |

## 11. Risiken

| Risiko | Bewertung / Gegenmaßnahme |
|---|---|
| Enum-Wert-Erweiterung in Zukunft (z. B. weitere Herkunftsarten) | Präzedenzfall für Enum-Migration bereits vorhanden (`2026_01_03_144125_add_open_group_to_course_type_enum.php`) — kein Risiko für dieses Change, nur eine spätere, separate Migration nötig |
| Zwei Tabellen mit identischen Spalten (`dogs`, `dog_registration_requests`) — Duplizierung des Schemas | Bewusst in Kauf genommen: beide Tabellen bilden fachlich unterschiedliche Entitäten (Antrag vs. bestätigter Datensatz) ab; das Projekt dupliziert bereits `name`/`breed`/`gender`/`date_of_birth`/`neutered`/`chip_number` zwischen beiden Tabellen (siehe Migrationen), das neue Muster fügt sich konsistent ein — keine Abstraktion einführen, die nicht angefordert wurde (YAGNI) |
| `age_at_acquisition` als Freitext ohne Struktur — keine Auswertbarkeit (z. B. Filterung "Hunde die als Welpen kamen") | Bewusste Entscheidung laut User-Antwort 1/2; nicht Teil dieses Changes, ggf. spätere separate Anforderung |

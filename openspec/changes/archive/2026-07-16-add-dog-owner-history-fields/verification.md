# Verification: add-dog-owner-history-fields

**Gesamtstatus:** ok

`openspec validate add-dog-owner-history-fields` lief fehlerfrei ("Change 'add-dog-owner-history-fields' is valid"). Der Change ist strukturell gültig, daher wurde der vollständige inhaltliche Realitätsabgleich durchgeführt.

---

## Bestätigt

### Datenmodell / Migrationen

- `design.md` Z.26/Z.94-98: `gender`-Enum-Muster in `dogs`-Migration → bestätigt in `backend/database/migrations/2025_12_22_184754_create_dogs_table.php:20`: `$table->enum('gender', ['male', 'female'])->nullable();`
- `design.md` Z.28-29: `status`-Enum-Muster in `dog_registration_requests`-Migration → bestätigt in `backend/database/migrations/2026_04_25_120000_create_dog_registration_requests_table.php:37`: `$table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');`
- `design.md` Z.86: Präzedenzfall für Driver-Switch bei bestehender Enum-Änderung → Datei `backend/database/migrations/2026_01_03_144125_add_open_group_to_course_type_enum.php` existiert tatsächlich.
- `design.md` Z.83-98 / `tasks.md` T01 Z.27-29: Behauptung, `$table->enum(...)` sei beim **Neuanlegen** einer Spalte ohne Driver-Switch MySQL-/Postgres-portabel → **bestätigt** durch Laravel-Vendor-Code: `backend/vendor/laravel/framework/.../Schema/Grammars/MySqlGrammar.php:882-885` erzeugt natives `enum(...)`, `backend/vendor/laravel/framework/.../Schema/Grammars/PostgresGrammar.php:867-872` erzeugt `varchar(255) check (... in (...))`. Beides ohne manuellen Driver-Switch. Projekt nutzt laut `composer.lock` `laravel/framework` `v11.51.0`.
- Zusätzlich bestätigt (nicht explizit in `design.md` behauptet, aber relevant): CI (`​.github/workflows/ci.yml:13-121`) führt Backend-Tests bereits als Matrix gegen **sowohl MySQL als auch PostgreSQL** aus (`add-db-matrix-ci` ist demnach bereits umgesetzt) — die in `design.md`/`tasks.md` geforderte "gegen beide Treiber testen"-Vorgabe ist somit auch automatisiert abgedeckt, nicht nur manuell.

### Backend — Model-Schicht

- `design.md` Z.115: `Dog::$fillable` bei Zeile 51-66 → bestätigt in `backend/app/Models/Dog.php:51-66`.
- `design.md` Z.117-118: `Dog::casts()` bei Zeile 73-84, `date_of_birth` als Cast-Vorbild → bestätigt in `backend/app/Models/Dog.php:73-84`, `'date_of_birth' => 'date'` an Zeile 76.
- `design.md` Z.120-121: PHPDoc-Property-Block bei Zeile 19-40 → bestätigt in `backend/app/Models/Dog.php:19-40`.
- `design.md` Z.122-126: `DogFactory::definition()` bei Zeile 18-35, `optional()`-Muster bei `color`/`veterinarian` Zeile 29-30 → bestätigt in `backend/database/factories/DogFactory.php:18-35`, Zeile 29-30 exakt `'color' => fake()->optional()->colorName(), 'veterinarian' => fake()->optional()->name(),`.
- `design.md` Z.130: `DogRegistrationRequest::$fillable` bei Zeile 43-55 → bestätigt in `backend/app/Models/DogRegistrationRequest.php:43-55`.
- `design.md` Z.131: `casts()` bei Zeile 62-71 → bestätigt in `backend/app/Models/DogRegistrationRequest.php:62-71`.
- `design.md` Z.132: PHPDoc-Property-Block bei Zeile 17-33 → bestätigt in `backend/app/Models/DogRegistrationRequest.php:17-33`.
- `design.md` Z.133-134: `DogRegistrationRequestFactory::definition()` bei Zeile 25-40 → bestätigt in `backend/database/factories/DogRegistrationRequestFactory.php:25-40`.
- `design.md` Z.255-257: `DogRegistrationRequest::notes` existiert bei Zeile 51 im Model → bestätigt in `backend/app/Models/DogRegistrationRequest.php:51` (`'notes',` in `$fillable`).
- `design.md` Z.254-256: `Dog::create(...)` in `approve()` übernimmt aktuell **kein** `notes`-Feld → bestätigt, `notes` fehlt im `Dog::create([...])`-Array in `backend/app/Http/Controllers/Api/DogRegistrationRequestController.php:147-156`.

### Backend — Validierung

- `design.md` Z.140: `StoreDogRequest::rules()` bei Zeile 31-48 → bestätigt in `backend/app/Http/Requests/StoreDogRequest.php:31-48`.
- `design.md` Z.148: `StoreDogRequest::attributes()` bei Zeile 72-81 → bestätigt in `backend/app/Http/Requests/StoreDogRequest.php:72-81`.
- `design.md` Z.159-161: `StoreDogRequest::validatedSnakeCase()` bei Zeile 55-65, generisches `Str::snake()`-Mapping → bestätigt in `backend/app/Http/Requests/StoreDogRequest.php:55-65`.
- `design.md` Z.165: `UpdateDogRequest::rules()` bei Zeile 49-64 → bestätigt (Array-Body ab Zeile 49 `return [`, Funktionsende Zeile 64) in `backend/app/Http/Requests/UpdateDogRequest.php:45-64`.
- `design.md` Z.174-176: bestehendes Muster `weight`/`color`/`notes` ohne `sometimes` in `UpdateDogRequest::rules()` → bestätigt, Zeilen 56-62 zeigen exakt dieses Muster (`'weight' => ['nullable', ...]` usw. ohne `sometimes`).
- `design.md` Z.182: `StoreDogRegistrationRequest::rules()` bei Zeile 35-46 → bestätigt in `backend/app/Http/Requests/StoreDogRegistrationRequest.php:35-46`.
- `design.md` Z.190: `attributes()` bei Zeile 53-59 → bestätigt in `backend/app/Http/Requests/StoreDogRegistrationRequest.php:53-59`.
- `design.md` Z.197: `validatedSnakeCase()` bei Zeile 66-76 → bestätigt in `backend/app/Http/Requests/StoreDogRegistrationRequest.php:66-76`.
- Zusätzlich bestätigt: `StoreDogRegistrationRequest.php:41` nutzt bereits `before_or_equal:today` für `dateOfBirth` — deckungsgleiches Muster zur geplanten `ownerSince`-Regel, stützt die Design-Annahme, dieses Muster sei im Projekt bereits etabliert.

### Backend — API-Antworten

- `design.md` Z.204: `DogResource::toArray()` bei Zeile 27-56, `'gender' => $this->gender,` bei Zeile 33 → bestätigt in `backend/app/Http/Resources/DogResource.php:27-56`, exakt Zeile 33.
- `design.md` Z.217-218: `DogRegistrationRequestResource::toArray()` bei Zeile 24-45, `'dateOfBirth' => ...` bei Zeile 32 → bestätigt in `backend/app/Http/Resources/DogRegistrationRequestResource.php:24-45`, exakt Zeile 32.

### Backend — Übernahme bei Genehmigung

- `design.md` Z.230-231: `DB::transaction(...)`-Block bei Zeile 145-166, `Dog::create([...])`-Array bei Zeile 147-156 → bestätigt exakt in `backend/app/Http/Controllers/Api/DogRegistrationRequestController.php:145-166` bzw. `:147-156`.

### Frontend — DogFormModal.vue

- `design.md` Z.266: "Additional Info"-Block bei Zeile 133-163, Error-Message bei Zeile 165-168 → bestätigt in `frontend/src/components/DogFormModal.vue:133-163` bzw. `:165-168`.
- `design.md` Z.304-306: bestehendes 3-Spalten-Grid (Geburtsdatum/Geschlecht/Gewicht) bei Zeile 107-131 → bestätigt in `frontend/src/components/DogFormModal.vue:107-131`.
- `design.md` Z.32-33: Gender-Select-Muster `<option value="male">Rüde</option>` bei Zeile 120-124 → bestätigt, Option exakt in Zeile 122 innerhalb des zitierten Blocks `frontend/src/components/DogFormModal.vue:120-124`.
- `design.md` Z.308: `form`-Ref bei Zeile 250-262 → bestätigt in `frontend/src/components/DogFormModal.vue:250-262`.
- `design.md` Z.316: `watch(() => props.dog, ...)` bei Zeile 269-287 → bestätigt in `frontend/src/components/DogFormModal.vue:269-287`.
- `design.md` Z.325: `resetForm()` bei Zeile 334-352 → bestätigt in `frontend/src/components/DogFormModal.vue:334-352`.
- `design.md` Z.328: `saveDogRecord()`-Payload bei Zeile 390-402 → bestätigt in `frontend/src/components/DogFormModal.vue:390-402`.
- `design.md` Z.338: `translateError()` bei Zeile 359-369 → bestätigt in `frontend/src/components/DogFormModal.vue:359-369`; bestehender `gender`-Übersetzungseintrag exakt bei Zeile 367 (`'The gender field must be male or female': ...`).
- `proposal.md` Z.91: `DogFormModal.vue` nutzt `dog?: any` → bestätigt in `frontend/src/components/DogFormModal.vue:223`.
- `design.md` Z.461 (Übersicht): `frontend/src/components/DogFormModal.test.ts` existiert bereits → bestätigt (261 Zeilen, `describe('DogFormModal', ...)`).

### Frontend — CustomerDogRequestModal.vue

- `design.md` Z.356-357: "Chip Number"-Block bei Zeile 120-130, "Notes"-Block bei Zeile 132-142 → bestätigt in `frontend/src/components/CustomerDogRequestModal.vue:120-130` bzw. `:132-142`.
- `design.md` Z.403: `form`-Ref bei Zeile 198-206, bereits camelCase → bestätigt in `frontend/src/components/CustomerDogRequestModal.vue:198-206`.
- `design.md` Z.413: `resetForm()` bei Zeile 218-231 → bestätigt in `frontend/src/components/CustomerDogRequestModal.vue:218-231`.
- `design.md` Z.415-416: `handleSubmit()`-Payload bei Zeile 238-246, `gender`-Pattern (`|| null`) bei Zeile 241 → bestätigt exakt in `frontend/src/components/CustomerDogRequestModal.vue:238-246` bzw. `:241`.
- `design.md` Z.424-426: Kein Vitest-Test für `CustomerDogRequestModal.vue` vorhanden → bestätigt, Verzeichnis-Listing von `frontend/src/components/` enthält nur `DogFormModal.test.ts`, keine `CustomerDogRequestModal.test.ts`.

### Out-of-Scope-Begründungen

- `proposal.md` Z.81-86: `DashboardView.vue:112-151` zeigt nur Name/Rasse/Kunde/Datum → bestätigt in `frontend/src/views/DashboardView.vue:112-151` (`request.dogName`, `request.breed`, `request.customerName`, `formatDate(request.createdAt)` — keine weiteren Felder).
- `proposal.md` Z.95-99: kein PHP-Backed-Enum im Projekt für vergleichbare Wertelisten, `gender`/`status` als Plain-String mit `in:...` → bestätigt (siehe oben, `gender`/`status` sind Plain-Enum-Migrationsspalten, keine PHP-`enum`-Klassen referenziert in den geprüften Dateien).

### Backend-Tests (T10)

- `tasks.md` T10 Z.284-285: `assertJsonStructure`-Block in `DogApiTest.php` bei "z. B. Zeile 27-38" → bestätigt exakt in `backend/tests/Feature/Api/DogApiTest.php:27-38`.
- `design.md`/`tasks.md`: Dateien `backend/tests/Feature/Api/DogApiTest.php` und `backend/tests/Feature/DogRegistrationRequestApiTest.php` existieren und sind Pest-Testdateien mit `describe`/`test`/`beforeEach` → bestätigt.

---

## Widerlegt

Keine inhaltlichen Falschbehauptungen über Codestruktur, Zeilennummern oder Feld-/Methodennamen gefunden. Alle geprüften konkreten Behauptungen in `proposal.md`/`design.md`/`tasks.md` zu bestehendem Code waren zutreffend.

---

## Nicht auffindbar

- `tasks.md` T01/T02/T03/T04/T10 (Akzeptanzkriterien, jeweils letzter Punkt): "`composer compat-check` meldet keine PHP-8.3/8.4-Verstöße" — das Composer-Script `compat-check` **existiert nicht** in `backend/composer.json` (Scripts-Sektion enthält nur `post-autoload-dump`, `post-update-cmd`, `post-root-package-install`, `post-create-project-cmd`, `dev`). Auch `composer test`, `composer lint`, `composer stan`, `composer qa` (aus CLAUDE.md Abschnitt 5/7.1) existieren nicht als Composer-Scripts. Das Dev-Dependency `phpcompatibility/php-compatibility` ist ebenfalls nicht in `composer.json`/`composer.lock` vorhanden (Grep ohne Treffer). CLAUDE.md Abschnitt 5 weist selbst darauf hin, dass dieses Script ggf. noch nicht existiert und liefert eine Anlege-Anleitung — dieser Hinweis wurde aber weder in `design.md` noch in `tasks.md` aufgegriffen; die Akzeptanzkriterien setzen die Existenz des Scripts stillschweigend voraus, ohne dass eine Task das Script anlegt.
- `tasks.md` T12 (Akzeptanzkriterium): "`npm run lint` ohne neue Verstöße" — das npm-Script `lint` **existiert nicht** in `frontend/package.json` (Scripts: `dev`, `build`, `build:deploy`, `preview`, `test`, `test:ui`, `test:coverage`, `e2e`, `e2e:ui` — kein `lint`). Auch keine ESLint-Konfigurationsdatei im `frontend/`-Verzeichnis gefunden. Die CI (`.github/workflows/ci.yml`) führt ebenfalls kein Lint aus, nur `npm run test` für das Frontend.
- `design.md` Z.115 / `tasks.md` T03: `$fillable`-Erweiterung "um `owner_since`, `age_at_acquisition`, `origin`" wird als reine Ergänzung beschrieben — nicht prüfbar, ob dabei versehentlich `medical_notes` (in der PHPDoc referenziert, `backend/app/Models/Dog.php:30`, aber **nicht** im aktuellen `$fillable`) betroffen sein könnte; da dies außerhalb des Scopes der Behauptung liegt, hier nur als Hinweis, keine Bewertung als Widerspruch.

---

## Neue Elemente (Plausibilität)

- `tasks.md` T01/T02: neue Migrationsdateien `backend/database/migrations/2026_07_16_120000_add_owner_history_to_dogs_table.php` und `..._120001_add_owner_history_to_dog_registration_requests_table.php` → Pfade existieren noch nicht (Verzeichnis-Scan bestätigt keine Datei mit diesem Namen), Namensmuster konsistent mit vorhandenem Präzedenzfall `2026_01_03_144125_add_open_group_to_course_type_enum.php`. Kein Konflikt.
- `tasks.md` T10: neue Testfälle in bestehenden Dateien `DogApiTest.php`/`DogRegistrationRequestApiTest.php` → plausibel, Dateien existieren und folgen Pest-`describe`/`test`-Konventionen, an die neue Fälle anschließen können.
- Neue Capability `dog-owner-history` (`specs/dog-owner-history/spec.md`) → kein bestehender Capability-Ordner dieses Namens unter `openspec/specs/` gefunden (nicht separat verifiziert, da openspec-Tooling dies bereits über `openspec validate` strukturell prüft und dies erfolgreich war).

---

## Zusätzliche Beobachtungen (vom Architekten nicht behandelt)

- **`frontend/src/views/dogs/DogsView.vue`** zeigt Dog-Daten in einer Karten-Liste an (`name`, `breed`, `customer.user.fullName`, `dateOfBirth`, `chipNumber` — Zeilen 38-70), wird aber in `proposal.md`/`design.md`/`tasks.md` **an keiner Stelle erwähnt**, weder als betroffene Datei noch explizit als Out-of-Scope wie `DashboardView.vue` (proposal.md Z.81-86). Da diese Kartenansicht bereits jetzt nicht alle Dog-Felder zeigt (z. B. auch `gender` fehlt dort), ist eine Nichtberücksichtigung der drei neuen Felder wahrscheinlich vertretbar (analog zur YAGNI-Begründung für `DashboardView.vue`), aber der Architekt hat diese Konsistenzfrage nicht explizit adressiert — im Gegensatz zu `DashboardView.vue`, wo eine bewusste Entscheidung dokumentiert ist.
- Keine API-Dokumentation (OpenAPI/Postman-Collection) im Projekt gefunden (`find` ohne Treffer) — die Behauptung "API-Doku" aus der User-Anfrage ist somit gegenstandslos, es gibt keine zu aktualisierende Doku-Datei.
- Kein dedizierter `DogSeeder` gefunden, der `Dog::create(...)` mit hartcodierten Werten aufruft (`DatabaseSeeder.php` enthält keinen `Dog::`-Aufruf) — Seeder-Anpassung ist somit korrekterweise nicht Teil der Tasks.
- CI (`.github/workflows/ci.yml`) testet Backend bereits automatisiert gegen MySQL **und** PostgreSQL (Matrix-Job `backend-tests`) — die in `design.md` Abschnitt 2 als manueller Schritt geforderte Doppel-DB-Prüfung ist damit zusätzlich bereits durch CI abgesichert; das ist eine positive Randbeobachtung, keine Diskrepanz.

---

## Empfehlung

Die Spec ist inhaltlich außergewöhnlich präzise — praktisch alle Datei- und Zeilenverweise in `proposal.md`/`design.md`/`tasks.md` konnten exakt gegen den echten Code verifiziert werden, einschließlich der technisch nicht trivialen Portabilitätsbehauptung zu `$table->enum(...)` (durch Laravel-Vendor-Code bestätigt). Vor Freigabe sollte der Architekt jedoch zwei konkrete Lücken schließen: (1) die Akzeptanzkriterien, die `composer compat-check` und `npm run lint` referenzieren, sind mit den aktuellen `composer.json`/`package.json`-Scripts nicht ausführbar — entweder die Scripts vorab anlegen (eigener Mini-Task oder Vorbedingung) oder die Akzeptanzkriterien anpassen; (2) eine kurze Aussage ergänzen, ob `frontend/src/views/dogs/DogsView.vue` bewusst außen vor bleibt (analog zur `DashboardView.vue`-Begründung) oder ob dort ebenfalls Anzeigebedarf besteht.

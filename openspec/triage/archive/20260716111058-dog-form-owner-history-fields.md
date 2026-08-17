# Triage: Herkunfts-/Übernahme-Felder im Hunde-Formular

**Pfad:** standard
**Geschätzter Umfang:** ca. 9–11 Dateien, PHP (Backend) + TypeScript/Vue (Frontend)
**Risiko:** mittel — neue Migration (additive, nullable Spalten) + API-Response-Erweiterung (DogResource), kein Schnittstellenbruch, aber mehrere Module betroffen (Model, 2 FormRequests, Resource, Vue-Formular, ggf. neuer PHP-Enum).
**Klarheit:** mehrdeutig — Feld 2 ("Alter bei Einzug") ist fachlich nicht eindeutig spezifiziert (siehe Rückfragen).

## Anforderung (Zusammenfassung)

Im Formular zum Anlegen/Bearbeiten eines Hundes (`DogFormModal.vue`, genutzt
von Admin/Trainer) fehlen drei Eingabefelder:

1. **Seit wann ist der Hund beim Halter** — Datum, seit dem der aktuelle
   Halter/Owner den Hund hat.
2. **Wie alt war der Hund bei Einzug** — Alter des Hundes zum Zeitpunkt der
   Aufnahme beim Halter.
3. **Woher kommt der Hund** — Herkunft, Auswahl aus fester Liste: Züchter,
   Tierschutz, Privat, unbekannt.

## Rechercheergebnis

**Datenmodell (Backend):**

- `backend/app/Models/Dog.php:51-66` — `$fillable` enthält aktuell:
  `customer_id, name, breed, date_of_birth, gender, neutered, weight,
  chip_number, color, veterinarian, special_needs, notes, is_active,
  profile_image`. Kein Feld für Halter-seit-Datum, Einzugsalter oder
  Herkunft vorhanden.
- `backend/database/migrations/2025_12_22_184754_create_dogs_table.php` —
  Ursprungs-Migration der `dogs`-Tabelle, keine der drei gesuchten Spalten.
- Repo-weite Suche (`grep -in "owner_since|acquired|herkunft|origin|source|
  breeder|shelter|tierschutz|züchter|einzug|intake"`) über
  `backend/app/Models`, `backend/database/migrations`,
  `backend/app/Http/Requests`, `backend/app/Http/Resources` ergab **keine
  Treffer** — die Felder existieren nirgends im Datenmodell. Der einzige
  Treffer für "seit wann" war eine unabhängige Anamnese-Frage
  (`backend/database/seeders/AnamnesisTemplateSeeder.php:128`,
  `"Seit wann tritt das Verhalten auf?"`) — fachlich nicht verwandt.
- **Fazit:** Für alle drei Felder sind **neue DB-Spalten und eine neue
  Migration nötig.**

**Betroffene Dateien (Backend):**

- Neue Migration `backend/database/migrations/<ts>_add_owner_history_to_
  dogs_table.php` — z. B. `owner_since` (`date`, nullable), `age_at_
  acquisition` (Typ klärungsbedürftig, s. Rückfrage), `origin` (`enum` oder
  `string`, nullable).
- `backend/app/Models/Dog.php` — `$fillable`, `casts()`, PHPDoc-Property-
  Block erweitern.
- `backend/app/Http/Requests/StoreDogRequest.php:31-48` — Validierungsregeln
  für die drei neuen Felder ergänzen (Attribut-Labels in `attributes()`
  ebenfalls).
- `backend/app/Http/Requests/UpdateDogRequest.php:45-64` — analog mit
  `sometimes`-Regeln.
- `backend/app/Http/Resources/DogResource.php:25-56` — drei neue Felder in
  `toArray()` (camelCase-Output, Projektkonvention).
- Optional: neuer PHP-Enum (z. B. `App\Enums\DogOrigin`) für die feste
  Herkunftsliste, falls der Architekt sich für PHP-Enum statt DB-`enum`-
  Spalte entscheidet — Projekt nutzt für vergleichbare Fälle (`gender`,
  `status` in Bookings/Courses/Payments etc.) durchgehend
  `$table->enum(...)` direkt in der Migration (siehe z. B.
  `backend/database/migrations/2025_12_22_184754_create_dogs_table.php:20`
  und `2026_04_25_120000_create_dog_registration_requests_table.php:31`).
  Für DB-`enum`-Spalten existiert im Repo bereits ein Präzedenzfall für
  spätere Werteänderungen unter Postgres
  (`backend/database/migrations/2026_01_03_144125_add_open_group_to_
  course_type_enum.php`), d. h. das Muster ist etabliert und MySQL-/
  Postgres-portabel gelöst.
- `backend/tests/Feature/Api/DogApiTest.php` (305 Zeilen) — bestehende
  Dog-CRUD-Tests, müssen um die drei neuen Felder erweitert werden
  (Tester-Agent).

**Betroffene Dateien (Frontend):**

- `frontend/src/components/DogFormModal.vue` — einziges Formular für
  Anlegen/Bearbeiten eines Hundes (Zeile 29: `{{ dog ? 'Hund bearbeiten' :
  'Neuer Hund' }}`). Betrifft: Template (drei neue Input-Felder analog zu
  bestehenden Date-/Select-/Text-Feldern, Zeile 107-131), `form`-Ref
  (Zeile 250-262), `watch(() => props.dog, ...)`-Befüllung (Zeile 269-287),
  `resetForm()` (Zeile 334-352), `saveDogRecord()`-Payload (Zeile 390-402),
  ggf. `translateError()`-Übersetzungstabelle (Zeile 359-369) für neue
  Validierungsfehler.
- `frontend/src/components/DogFormModal.test.ts` (261 Zeilen) — bestehende
  Tests müssen erweitert werden.
- Kein globaler TypeScript-`interface Dog`-Typ gefunden (nur lokale
  `interface Dog` in `CustomerBookingModal.vue:31` und
  `anamnesis/AnamnesisFormModal.vue:174`, beide fachlich unabhängig; in
  `DogFormModal.vue` wird `dog?: any` verwendet) — kein zentraler Typ-Ort,
  der zwingend angepasst werden muss, aber die lokalen lockeren Typen
  sollten geprüft werden, falls die neuen Felder dort auch gebraucht
  werden.

**Ungeprüfte Referenz:** `DogRegistrationRequestController`/`DogDeletionRequestController`
und die zugehörige `dog_registration_requests`-Tabelle
(`backend/database/migrations/2026_04_25_120000_create_dog_registration_
requests_table.php`) — ein separater Self-Service-Flow, über den Kunden
selbst einen Hund anmelden und der bei Genehmigung einen `Dog`-Datensatz
erzeugt. Ob die drei neuen Felder dort ebenfalls erfasst werden sollen, ist
in der Anforderung **nicht erwähnt** und wurde daher nicht recherchiert.
Wird hier als offene Rückfrage markiert, nicht spekulativ in den Umfang
aufgenommen.

## Rückfragen an den User (Klarheit = mehrdeutig)

1. **Feld 2 "Alter bei Einzug" — Datentyp und Zweck:** Soll das ein vom
   Nutzer frei eingegebener Wert sein (z. B. weil bei Tierschutz-/Fundhunden
   das genaue Geburtsdatum oft unbekannt ist und nur eine grobe
   Altersschätzung vorliegt), oder soll es aus `date_of_birth` und dem neuen
   "seit wann beim Halter"-Datum automatisch berechnet werden? Falls manuelle
   Eingabe: Einheit (Monate? Jahre? Freitext wie "ca. 2 Jahre")?
2. **Feld 1 vs. Feld 2 — Redundanz:** Wenn sowohl Geburtsdatum als auch
   "seit wann beim Halter"-Datum bekannt sind, ließe sich das Einzugsalter
   rechnerisch ableiten. Soll Feld 2 nur als Fallback dienen, wenn
   `date_of_birth` unbekannt ist (typisch bei Tierschutzhunden ohne Papiere)?
3. **Feld 3 "Herkunft" — Erweiterbarkeit:** Reicht eine feste Werteliste
   (Züchter, Tierschutz, Privat, unbekannt) als DB-`enum` (wie im Projekt
   für `gender`/`status` üblich), oder wird erwartet, dass die Liste später
   ohne Migration erweiterbar sein soll (dann eher `string` + Validierung
   `in:...` statt DB-`enum`)?
4. **Scope-Grenze zur Registrierungsanfrage:** Sollen die drei Felder auch
   im Self-Service-Formular für Kunden-Hundeanmeldungen
   (`StoreDogRegistrationRequest`, `dog_registration_requests`-Tabelle)
   erfasst werden, oder ausschließlich im Admin/Trainer-Formular
   (`DogFormModal.vue` via `StoreDogRequest`/`UpdateDogRequest`)?

## Antworten des Users (2026-07-16)

1. **Feld 2 "Alter bei Einzug":** Manuelle Eingabe (kein Berechnungsfeld).
2. **Format Feld 2:** Freitext (z. B. "ca. 2 Jahre"), kein numerisches Feld.
3. **Feld 3 "Herkunft":** Feste Werteliste als DB-`enum` (Züchter, Tierschutz,
   Privat, unbekannt), analog zum bestehenden `gender`/`status`-Muster.
   Spätere Erweiterung per Migration möglich (Präzedenzfall vorhanden).
4. **Scope:** Beide Formulare betroffen — Admin/Trainer-Formular
   (`DogFormModal.vue`) **und** Kunden-Self-Service
   (`dog_registration_requests`-Flow inkl.
   `StoreDogRegistrationRequest`/zugehöriger Vue-Komponente). Der Architekt
   muss den bisher ungeprüften Registrierungsanfrage-Flow recherchieren und
   in die Tasks aufnehmen.

## Empfohlene nächste Aktion

`@architect` (Modus A) erstellt den openspec-Change (Vorschlag Change-ID:
`add-dog-owner-history-fields`) **erst nachdem** die vier Rückfragen oben
geklärt sind — insbesondere Frage 1/2 (Datentyp und Berechnungslogik von
"Alter bei Einzug") und Frage 4 (Scope-Grenze), da diese die Migration und
die Anzahl der betroffenen Tasks direkt beeinflussen. Der Architekt sollte
in `design.md` explizit vermerken, ob die neue Migration MySQL- und
Postgres-kompatibel ist (Abschnitt 4.2 CLAUDE.md), und Tasks für
`dev-php` (Migration, Model, FormRequests, Resource) und `dev-typescript`
(DogFormModal.vue) getrennt anlegen.

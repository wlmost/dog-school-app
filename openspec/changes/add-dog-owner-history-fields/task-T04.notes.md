# Notes T04: `DogRegistrationRequest`-Model + `DogRegistrationRequestFactory` erweitern

**Agent:** dev-php
**Status:** abgeschlossen

## Geänderte Dateien

- `backend/app/Models/DogRegistrationRequest.php`
  - `$fillable`: `owner_since`, `age_at_acquisition`, `origin` ergänzt (nach `chip_number`).
  - `casts()`: `'owner_since' => 'date'` ergänzt (analog zu `date_of_birth`).
  - PHPDoc-Property-Block: drei neue `@property`-Zeilen ergänzt
    (`owner_since` als `\Illuminate\Support\Carbon|null`, `age_at_acquisition`
    als `string|null`, `origin` als `string|null` mit Werteliste im
    Kommentar, analog zum bestehenden `status`-Kommentarmuster).
- `backend/database/factories/DogRegistrationRequestFactory.php`
  - `definition()`: `owner_since` (`fake()->optional()->dateTimeBetween('-5 years', 'now')`),
    `age_at_acquisition` (`fake()->optional()->randomElement([...])` mit
    deutschen Freitext-Beispielen), `origin`
    (`fake()->optional()->randomElement(['breeder', 'shelter', 'private', 'unknown'])`)
    ergänzt — analog zum bestehenden `fake()->optional()`-Muster bei
    `chip_number`/`notes`. Kein Pflichtfeld, `status`/`reviewed_by`/
    `reviewed_at` bleiben unverändert einzige immer gesetzte Zusatzfelder.

## Abgleich mit `design.md` Abschnitt 3.2

Umsetzung entspricht exakt der dort beschriebenen Vorgabe (identisches
Muster zu T03/Abschnitt 3.1, angewendet auf `DogRegistrationRequest`).

## Verifikation der Akzeptanzkriterien

Durchgeführt via `docker compose exec -T php php artisan tinker` gegen die
lokale PostgreSQL-Docker-Umgebung (Migration `T02` war bereits gemerged und
migriert):

- [x] `DogRegistrationRequest::create([...])` mit `owner_since`,
      `age_at_acquisition`, `origin` speichert und liest alle drei Felder
      korrekt (verifiziert via `DogRegistrationRequestFactory` mit
      Overrides: `owner_since` als `2024-01-01`, `age_at_acquisition` als
      `'ca. 2 Jahre'`, `origin` als `'shelter'` — alle drei nach dem
      Neuladen korrekt gelesen).
- [x] `DogRegistrationRequest::create([...])` ohne die drei Felder
      funktioniert weiterhin (Regressionsschutz) — erstellter Datensatz mit
      nur `customer_id`/`name`/`status` liefert `null` für alle drei neuen
      Felder, keine Exception.
- [x] `owner_since` wird als `Carbon`-Instanz zurückgegeben — verifiziert
      via `get_class($r->owner_since)` ⇒ `Illuminate\Support\Carbon`.
- [x] `DogRegistrationRequest::factory()->create()` erzeugt gültige
      Datensätze inkl. optional befüllter neuer Felder — verifiziert, keine
      Exceptions, `owner_since` bei Belegung korrekt als `Carbon`-Objekt.
- [x] Manuelle Prüfung: keine der in CLAUDE.md Abschnitt 4.1 gelisteten
      PHP-8.3/8.4-Konstrukte verwendet (`grep` gegen beide geänderten
      Dateien auf `new X()->`, `::{`, `#[\Override]`, `#[\Deprecated]`,
      `const string`, `array_find`/`array_any`/`array_all` — keine Treffer).

## QA-Läufe

- `docker compose exec -T php ./vendor/bin/pest --no-coverage`:
  **679 passed (2120 assertions)**, keine Regressionen.
  (Hinweis: `composer qa`/`composer test` existiert nicht als Script in
  `backend/composer.json` — siehe `proposal.md` "Out of Scope — Fehlende
  QA-Scripts", bereits in T01–T03 dokumentiert. Direkter Pest-Aufruf
  entspricht dem in `.github/workflows/ci.yml:112` verwendeten Befehl.)
- Kein `composer lint`/`composer stan`/`composer compat-check` verfügbar
  (nicht in `backend/composer.json` definiert) — manuelle Prüfung gegen
  CLAUDE.md Abschnitt 4.1 stattdessen durchgeführt (siehe oben).
- Migration `2026_07_16_120001_add_owner_history_to_dog_registration_requests_table.php`
  war zum Zeitpunkt der Implementierung bereits migriert (T02 gemerged) —
  `php artisan migrate` meldete "Nothing to migrate", kein erneuter
  MySQL/Postgres-Doppellauf nötig, da keine Migrations-Änderung in T04.

## Abweichungen vom Muster

Keine. `DogRegistrationRequest.php` und `DogRegistrationRequestFactory.php`
folgen exakt demselben Muster wie in `design.md` für `Dog`/`DogFactory`
(T03) beschrieben, nur auf die andere Tabelle/das andere Model angewendet.

## Scope-Einhaltung

Ausschließlich `backend/app/Models/DogRegistrationRequest.php` und
`backend/database/factories/DogRegistrationRequestFactory.php` bearbeitet.
Keine Berührung von `Dog.php`/`DogFactory.php` (T03, paralleler Agent) oder
anderen Dateien.

# Notes: T03 — `Dog`-Model + `DogFactory` erweitern

**Agent:** dev-php
**Change-ID:** add-dog-owner-history-fields
**Status:** abgeschlossen

## Geänderte Dateien

- `backend/app/Models/Dog.php`
  - `$fillable` um `owner_since`, `age_at_acquisition`, `origin` erweitert.
  - `casts()` um `'owner_since' => 'date'` erweitert (`age_at_acquisition`
    und `origin` bleiben ohne Cast, Plain-String, analog zu `design.md`
    Abschnitt 3.1).
  - PHPDoc-Property-Block um `@property \Illuminate\Support\Carbon|null
    $owner_since`, `@property string|null $age_at_acquisition`,
    `@property string|null $origin` ergänzt (eingefügt nach
    `$profile_image`, vor `$created_at`).
- `backend/database/factories/DogFactory.php`
  - `definition()` um drei optionale Faker-Werte ergänzt, analog zum
    bestehenden `fake()->optional()`-Muster bei `color`/`veterinarian`:
    - `owner_since` → `fake()->optional()->dateTimeBetween('-5 years', 'now')`
    - `age_at_acquisition` → `fake()->optional()->randomElement([...])` mit
      vier Freitext-Beispielen
    - `origin` → `fake()->optional()->randomElement(['breeder', 'shelter',
      'private', 'unknown'])`
  - Kein Pflichtfeld ergänzt — `is_active` bleibt einziges immer-gesetztes
    Zusatzfeld, wie in `design.md` Abschnitt 3.1 gefordert.

## Nicht angefasst (außerhalb des Scopes von T03)

- `backend/app/Models/DogRegistrationRequest.php` und
  `backend/database/factories/DogRegistrationRequestFactory.php` — Teil von
  T04, das laut Task-Beschreibung parallel von einem anderen Agenten
  bearbeitet wird. Beim Lesen des `git status` vor Arbeitsbeginn waren diese
  Dateien bereits als "modified" markiert — das ist die Arbeit des
  T04-Agenten, nicht dieser Task. Ich habe sie nicht angerührt.

## Verifikation der Akzeptanzkriterien

Alle Prüfungen liefen in der laufenden Docker-Dev-Umgebung
(`docker compose exec php ...`) gegen die lokale PostgreSQL-Instanz, auf der
die T01-Migration (`2026_07_16_120000_add_owner_history_to_dogs_table.php`)
bereits angewendet ist.

1. **`Dog::create([...])` mit allen drei Feldern speichert und liest sie
   korrekt** — verifiziert per `php artisan tinker` (in einer
   `DB::beginTransaction()`/`DB::rollBack()`-Sandbox, keine Testdaten
   verblieben):
   ```
   $dog = App\Models\Dog::create([
       'customer_id' => $customer->id,
       'name' => 'Rex',
       'owner_since' => '2024-01-01',
       'age_at_acquisition' => 'ca. 2 Jahre',
       'origin' => 'shelter',
   ]);
   $dog->refresh();
   ```
   Ergebnis nach `refresh()`: `owner_since` ist `Illuminate\Support\Carbon`
   mit `toDateString() === '2024-01-01'`, `age_at_acquisition === 'ca. 2
   Jahre'`, `origin === 'shelter'`.

2. **`Dog::create([])` ohne die drei Felder funktioniert weiterhin
   (Regressionsschutz)** — verifiziert: `Dog::create(['customer_id' =>
   ..., 'name' => 'Bello'])` (ohne die drei neuen Felder) erzeugt einen
   Datensatz mit `owner_since === null`, `age_at_acquisition === null`,
   `origin === null`. Kein Fehler, keine Exception.

3. **`owner_since` wird als `Carbon`-Instanz zurückgegeben (Cast greift)**
   — verifiziert, `get_class($dog->owner_since) ===
   'Illuminate\Support\Carbon'`.

4. **`Dog::factory()->create()` erzeugt gültige Datensätze inkl. optional
   befüllter neuer Felder** — verifiziert, `Dog::factory()->create()` läuft
   ohne Fehler; `owner_since` ist entweder `null` oder eine
   `Carbon`-Instanz (Assertion `true` bestätigt).

5. **Bestehende Test-Suite bleibt grün** — zusätzlich zur manuellen Prüfung:
   `docker compose exec php php artisan test --filter=Dog` →
   **98 passed (251 assertions)**, keine Regression in `DogApiTest`,
   `ModelRelationshipsTest`, `ModelScopesTest`, `DatabaseStructureTest` etc.

6. **Keine PHP-8.3/8.4-Konstrukte** — manuelle Prüfung gegen CLAUDE.md
   Abschnitt 4.1: keine Typed Class Constants, kein `#[\Override]`, kein
   `json_validate()`, keine Dynamic Class Constant Fetch, kein
   `new MyClass()->method()` ohne Klammern, keine Property Hooks, keine
   Asymmetric Visibility, keine `array_find`/`array_any`/`array_all`. Beide
   geänderten Dateien verwenden ausschließlich Standard-Array-Literale,
   Standard-Eloquent-Casts und die bereits etablierten `fake()->optional()`
   Faker-Aufrufe. Kein automatisiertes `compat-check`-Script im Projekt
   vorhanden (siehe `proposal.md` "Out of Scope — Fehlende QA-Scripts"),
   daher rein manuelle Prüfung wie in den Akzeptanzkriterien gefordert.

## Lokale Checks

- `docker compose exec php vendor/bin/pint --test app/Models/Dog.php
  database/factories/DogFactory.php` → meldet 2 vorbestehende Stilfindings
  (`fully_qualified_strict_types`, `not_operator_with_successor_space`).
  Per `git stash`-Vergleich verifiziert: **identisch vor und nach meiner
  Änderung** — vorbestehender Zustand, nicht durch T03 eingeführt, daher
  nicht Teil dieser Task behoben (kein Scope-Creep).
- Kein `composer stan`/`composer compat-check`/`composer qa`-Script in
  `backend/composer.json` vorhanden (weder Larastan/PHPStan noch
  PHPCompatibility als Dev-Dependency installiert) — deckt sich mit
  `proposal.md` "Out of Scope — Fehlende QA-Scripts". Manuelle Prüfung wie
  oben dokumentiert durchgeführt.
- `php artisan test --filter=Dog` grün (98 Tests, 251 Assertions).

## Offene Punkte / Hinweise für nachfolgende Tasks

- Keine. T03 ist eigenständig abgeschlossen, T05/T07/T09/T10 bauen wie in
  `tasks.md` beschrieben darauf auf.

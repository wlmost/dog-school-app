# Task T02 Notes — Migration: Herkunfts-/Übernahme-Felder auf `dog_registration_requests`

**Change-ID:** add-dog-owner-history-fields
**Agent:** dev-php
**Status:** abgeschlossen

## Umgesetzt

Neue additive Migration:
`backend/database/migrations/2026_07_16_120001_add_owner_history_to_dog_registration_requests_table.php`

Fügt der Tabelle `dog_registration_requests` drei nullable Spalten hinzu,
identisch zum Muster von T01 auf `dogs`:

```php
Schema::table('dog_registration_requests', function (Blueprint $table) {
    $table->date('owner_since')->nullable();
    $table->string('age_at_acquisition', 255)->nullable();
    $table->enum('origin', ['breeder', 'shelter', 'private', 'unknown'])->nullable();
});
```

`down()`: `$table->dropColumn(['owner_since', 'age_at_acquisition', 'origin']);`

`declare(strict_types=1);` gesetzt (CLAUDE.md Abschnitt 6 — Pflicht für
neue PHP-Dateien), kein raw SQL, kein `DB::statement()`.

## Verifikation

**Postgres (lokale Docker-Standardumgebung, `docker-compose.yml`,
Service `php` + `postgres`):**
- `php artisan migrate --path=database/migrations/2026_07_16_120001_...php --force`
  → läuft fehlerfrei, alle drei Spalten in `dog_registration_requests`
  vorhanden (geprüft via `Schema::getColumnListing(...)`).
- `php artisan migrate:rollback --path=...` → entfernt alle drei Spalten
  korrekt (geprüft: `owner_since`/`age_at_acquisition`/`origin` nicht mehr
  in `getColumnListing(...)`).
- Migration danach erneut ausgeführt, um den Entwicklungs-DB-Stand
  konsistent zu belassen (`migrate:status` zeigt beide neuen Migrationen
  aus T01 und T02 als `Ran`).

**MySQL:**
`docker-compose.mysql.yml` existiert **nicht** im Repo (CLAUDE.md Abschnitt
5/7.1 referenziert diese Datei, sie ist aber nicht vorhanden — bereits von
`verification.md` als Lücke dokumentiert, siehe dort "Nicht auffindbar").
Als Ersatz wurde ein temporärer MySQL-8.0-Container manuell im selben
Docker-Netzwerk (`dog-school-app_dog-school-network`) gestartet, mit
denselben Zugangsdaten wie in der CI-Matrix (`.github/workflows/ci.yml:29-42`):

```bash
docker run -d --name t02-mysql-test --network dog-school-app_dog-school-network \
  -e MYSQL_ROOT_PASSWORD=root_password -e MYSQL_DATABASE=dog_school_test \
  -e MYSQL_USER=test_user -e MYSQL_PASSWORD=test_password mysql:8.0
```

Gegen diesen Container:
- `php artisan migrate:fresh --force` (mit `DB_CONNECTION=mysql` env-Override
  auf dem `php`-Container) → **komplette** Migrationskette (inkl. T01- und
  T02-Migration) läuft fehlerfrei durch (42 Migrationen, `DONE`).
- Spalten in `dog_registration_requests` vorhanden (`owner_since`,
  `age_at_acquisition`, `origin`).
- `php artisan migrate:rollback --path=database/migrations/2026_07_16_120001_...php --force`
  → entfernt die drei Spalten korrekt aus `dog_registration_requests`,
  während die T01-Spalten auf `dogs` unverändert bleiben (isolierter
  `--path`-Rollback, verifiziert per `Schema::getColumnListing(...)` auf
  beiden Tabellen).
- Migration danach erneut angewendet, temporärer MySQL-Container anschließend
  entfernt (`docker rm -f t02-mysql-test`) — kein bleibender Fußabdruck in
  der lokalen Docker-Umgebung.

**SQLite (Pest-Test-Suite, `phpunit.xml`: `DB_CONNECTION=sqlite`,
`:memory:`):**
- `vendor/bin/pest --no-coverage tests/Feature/DogRegistrationRequestApiTest.php`
  → 24/24 Tests grün (50 Assertions). Läuft implizit über
  `RefreshDatabase`/`migrate:fresh`, bestätigt zusätzlich die
  Migrationslauffähigkeit auf einem dritten Treiber und Regressionsfreiheit
  der bestehenden Feature-Tests.

**Formatierung:**
- `vendor/bin/pint --test database/migrations/2026_07_16_120001_...php`
  → PASS (PSR-12-konform).

**PHP-Kompatibilität (CLAUDE.md Abschnitt 4.1, manuelle Prüfung):**
Kein automatisiertes `compat-check`-Script vorhanden (composer.json enthält
kein solches Script, `phpcompatibility/php-compatibility` nicht als
Dependency installiert — vorbestehende Lücke, bereits in `verification.md`
dokumentiert, außerhalb des Scopes dieser Task). Manuell geprüft: Migration
verwendet nur `declare(strict_types=1)`, `Schema::table()`,
`Blueprint::date()/string()/enum()`, `dropColumn()` — keines der in
CLAUDE.md Abschnitt 4.1 gelisteten 8.3/8.4-Features.

## Anmerkungen für nachfolgende Tasks / Reviewer

- Das Fehlen von `docker-compose.mysql.yml` betrifft nicht nur T02, sondern
  auch T01 und potenziell alle künftigen migrations-bezogenen Tasks in
  diesem Projekt. Es handelt sich um eine vorbestehende Infrastruktur-Lücke
  (in `verification.md` bereits als "Nicht auffindbar" vermerkt), keine
  Regression durch diesen Change. Empfehlung an den Architekten/Team: eigener
  kleiner Change, der `docker-compose.mysql.yml` gemäß CLAUDE.md-Referenz
  anlegt, damit künftige DB-Portabilitäts-Prüfungen nicht jedes Mal einen
  manuellen `docker run`-Workaround benötigen.
- T01s Migration (`2026_07_16_120000_add_owner_history_to_dogs_table.php`)
  war zum Zeitpunkt der Verifikation bereits vom parallelen Agenten angelegt
  und wurde für die MySQL-Volllauf-Prüfung (`migrate:fresh`) mit ausgeführt,
  aber nicht inhaltlich verändert oder bewertet — außerhalb des Scopes von
  T02.

# Notes: T01 — Migration `create_announcements_table`

**Agent:** dev-php
**Status:** abgeschlossen

## Umgesetzt

- `backend/database/migrations/2026_07_17_100000_create_announcements_table.php`
  (neu), Schema exakt wie in `tasks.md`/`design.md` Abschnitt 2.2 vorgegeben:
  `id`, `title` (string, 255), `body` (text), `image_path` (string, nullable),
  `display_days` (unsignedSmallInteger), `expires_at` (timestamp, **nicht**
  nullable — siehe Begründung in `design.md` Abschnitt 2.2), `timestamps()`,
  Index auf `expires_at`. `down()`: `Schema::dropIfExists('announcements')`.
- Stil an den zuletzt hinzugefügten Migrationen orientiert (z. B.
  `backend/database/migrations/2026_07_16_120000_add_owner_history_to_dogs_table.php`,
  `2026_05_14_000001_create_pricing_items_table.php`): kein
  `declare(strict_types=1);` — in `backend/database/migrations/` verwenden nur
  9 von 41 bestehenden Dateien diese Deklaration, alle jüngeren (2026er)
  Migrationen verzichten darauf; CLAUDE.md Abschnitt 6 fordert
  `strict_types` explizit nur für neue Dateien in `backend/app/`, nicht für
  Migrationen. Deshalb dem etablierten, aktuelleren Muster gefolgt statt der
  Minderheitskonvention.

## Ausschließlich treiberneutrale Blueprint-Methoden

Verwendet: `id()`, `string()`, `text()`, `nullable()`, `unsignedSmallInteger()`,
`timestamp()`, `timestamps()`, `index()`. Kein `DB::raw()`, kein
`DB::statement()`, keine Postgres-/MySQL-spezifischen Typen oder Operatoren
(CLAUDE.md Abschnitt 4.2).

## PHP-Kompatibilität (CLAUDE.md Abschnitt 4.1)

Manuell geprüft (kein `compat-check`-Script im Projekt vorhanden, siehe
`verification.md` Zeile 47-49 sowie `proposal.md` "Out of Scope"): keine
Property Hooks, keine Asymmetric Visibility, kein `#[\Deprecated]`, keine
PHP-8.4-`array_*`-Funktionen, kein `new MyClass()->method()`, keine Lazy
Objects; keine Typed Class Constants, kein `#[\Override]`, kein
`json_validate()`, kein Dynamic Class Constant Fetch, keine
`Randomizer`-8.3-Methoden. Die Datei nutzt ausschließlich seit PHP 8.1/8.2
verfügbare Standard-Laravel-Migrations-Syntax.

## Lokale Tests (Docker, siehe CLAUDE.md Abschnitt 5/7.1)

**PostgreSQL (lokale Docker-Standardumgebung, `docker-compose.yml`):**
- `docker compose up -d postgres php`
- `php artisan migrate` → `2026_07_17_100000_create_announcements_table` lief
  fehlerfrei durch (zusammen mit anderen zuvor bereits ausstehenden
  Migrationen der Entwicklungs-DB, die nicht Teil dieser Task sind).
- Schema via `psql \d announcements` verifiziert: exakt die erwarteten Spalten
  und Typen (`smallint` für `display_days`, `timestamp(0) without time zone`
  für `expires_at`, Index `announcements_expires_at_index` auf `expires_at`).
- `php artisan migrate:rollback --step=1` → Tabelle korrekt entfernt
  (`\dt` zeigt sie danach nicht mehr).
- Erneutes `php artisan migrate` (nur der Announcements-Migration) → lief
  danach wieder fehlerfrei durch (Idempotenz von up/down bestätigt).

**MySQL:** `docker-compose.mysql.yml` existiert **nicht** im Repository
(geprüft, kein Treffer bei `find . -iname "*mysql*"` außerhalb von
`openspec/triage/`). CLAUDE.md Abschnitt 7.1 referenziert diese Datei, sie ist
aber noch nicht angelegt — das ist ein bereits vom Skeptiker in einem anderen
Kontext identifizierter, projektweiter Nachrüstbedarf (siehe CLAUDE.md
Abschnitt 4.2 "Migrations-Test", verweist auf einen eigenen künftigen
openspec-Change für die CI-Matrix). Da MySQL-Kompatibilität dennoch
Akzeptanzkriterium dieser Task ist, wurde stattdessen ein temporärer,
eigenständiger MySQL-8.0-Container (`docker run mysql:8.0`, verbunden mit dem
bestehenden `dog-school-app_dog-school-network`) gestartet und die
Laravel-App per Env-Overrides (`DB_CONNECTION=mysql`, `DB_HOST=...` etc.,
`docker compose exec -e ...`) testweise dagegen migriert:
- `php artisan migrate --force` → alle Migrationen inkl. der neuen
  `announcements`-Tabelle liefen fehlerfrei durch.
- Schema via `mysql ... DESCRIBE announcements` verifiziert: exakt die
  erwarteten Spalten und Typen (`smallint unsigned` für `display_days`,
  `timestamp` für `expires_at`, Index `announcements_expires_at_index`).
- `php artisan migrate:rollback --step=1 --force` → Tabelle korrekt entfernt
  (`SHOW TABLES LIKE 'announcements'` liefert danach kein Ergebnis mehr).
- Der temporäre MySQL-Testcontainer wurde anschließend wieder entfernt
  (`docker rm -f dog-school-mysql-test`), keine dauerhaften
  Repository-Änderungen für diesen Test nötig.

**Ergebnis:** Beide Datenbanktreiber (PostgreSQL via projektstandard
`docker-compose.yml`, MySQL via temporärem Ad-hoc-Container mangels
vorhandener `docker-compose.mysql.yml`) wurden erfolgreich getestet — sowohl
`migrate` als auch `migrate:rollback` funktionieren fehlerfrei und erzeugen
identische, treiberneutral erwartete Schemata.

## Weitere lokale Checks

- `php -l` → keine Syntaxfehler.
- `vendor/bin/pint --test` → PASS (PSR-12-konform, kein `--fix` nötig).
- `vendor/bin/pest --filter=SettingsValidation` (Stichprobe eines
  bestehenden Feature-Tests, der via `RefreshDatabase` alle Migrationen
  inkl. der neuen gegen SQLite in-memory ausführt) → 7/7 Tests grün, keine
  Regression durch die neue Migration.
- `composer qa`/`composer test`/`composer stan`/`composer compat-check`
  existieren laut `composer.json` (Stand dieser Task) nicht im Projekt
  (bereits von `verification.md` bestätigt) — daher nicht ausführbar; die
  oben genannten Ersatzchecks (`php -l`, `pint`, `pest`) wurden stattdessen
  verwendet.

## Abweichungen von der Spec

Keine. Migration entspricht 1:1 dem in `tasks.md`/`design.md` Abschnitt 2.2
vorgegebenen Code.

## Hinweis für nachfolgende Tasks / Reviewer

- `docker-compose.mysql.yml` fehlt im Repo, obwohl CLAUDE.md Abschnitt 7.1
  sie referenziert. Für zukünftige DB-bezogene Tasks in diesem Change (bzw.
  als projektweite Nachrüstung) wäre ein eigener openspec-Change sinnvoll,
  der diese Datei anlegt (siehe CLAUDE.md Abschnitt 4.2 "Migrations-Test" /
  `add-db-matrix-ci`-Erwähnung). Für T01 wurde das Fehlen durch einen
  gleichwertigen Ad-hoc-Test kompensiert (siehe oben).

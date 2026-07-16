# Notes: T01 — Migration `add_owner_history_to_dogs_table`

**Agent:** dev-php
**Datei:** `backend/database/migrations/2026_07_16_120000_add_owner_history_to_dogs_table.php` (neu)

## Umsetzung

Additive Migration erstellt, exakt wie in `tasks.md` T01 und `design.md`
Abschnitt 2.1 spezifiziert:

```php
Schema::table('dogs', function (Blueprint $table) {
    $table->date('owner_since')->nullable();
    $table->string('age_at_acquisition', 255)->nullable();
    $table->enum('origin', ['breeder', 'shelter', 'private', 'unknown'])->nullable();
});
```

`down()`: `$table->dropColumn(['owner_since', 'age_at_acquisition', 'origin']);`

Struktur (Anonyme Klasse, `up()`/`down()`-Methoden, `Schema::table()` statt
`Schema::create()`) orientiert sich am bestehenden Präzedenzfall
`backend/database/migrations/2026_05_04_100000_add_profile_image_to_dogs_table.php`.
Kein `declare(strict_types=1)` — konsistent mit allen bestehenden
Migrationsdateien im Projekt (Migrationen liegen unter
`backend/database/migrations/`, nicht unter `backend/app/`, wo CLAUDE.md
Abschnitt 6 `declare(strict_types=1)` explizit vorschreibt).

Kein `->after(...)` verwendet (laut `design.md` Zeile 106-109 nur unter
MySQL wirksam, unter PostgreSQL von Laravel stillschweigend ignoriert —
Spaltenreihenfolge hat keine fachliche Bedeutung).

## Prüfung der Akzeptanzkriterien

Getestet in der lokalen Docker-Umgebung (`docker compose up -d`, bereits
laufend zu Beginn der Task).

1. **`php artisan migrate` gegen PostgreSQL (lokale Docker-Standardumgebung):**
   ```
   docker compose exec php php artisan migrate --path=database/migrations/2026_07_16_120000_add_owner_history_to_dogs_table.php
   ```
   → `DONE`. Verifiziert per `psql \d dogs` in `dog-school-postgres`: alle
   drei Spalten vorhanden, `origin` als `character varying(255)` mit
   `CHECK`-Constraint `dogs_origin_check` (erwartetes Laravel-Postgres-Mapping
   für `Blueprint::enum()`, siehe `design.md` Zeile 91-92).

2. **`php artisan migrate:rollback` auf PostgreSQL:**
   ```
   docker compose exec php php artisan migrate:rollback --path=database/migrations/2026_07_16_120000_add_owner_history_to_dogs_table.php
   ```
   → `DONE`. Verifiziert: alle drei Spalten aus `dogs` entfernt (`\d dogs`
   zeigt keine der drei Spalten mehr). Anschließend erneut migriert, um die
   Dev-DB im erwarteten End-Zustand des Changes zu belassen.

3. **`php artisan migrate` gegen MySQL:**
   `docker-compose.mysql.yml` existiert **nicht** im Projekt (geprüft per
   `find` — nur eine unabhängige Triage-Notiz
   `openspec/triage/20260517134659-mysql-json-key-order.md` enthält "mysql"
   im Namen, keine Compose-Datei). Das ist eine Lücke zwischen CLAUDE.md
   Abschnitt 7.1 (referenziert die Datei) und dem tatsächlichen
   Projektstand — analog zu den in `proposal.md` "Out of Scope — Fehlende
   QA-Scripts" bereits dokumentierten fehlenden Composer-Scripts. **Nicht**
   Teil dieses Tasks, daher nicht behoben, aber hier dokumentiert.

   Als Ersatz wurde MySQL 8.0 lokal repliziert, exakt nach dem Muster der
   tatsächlichen CI-Matrix (`.github/workflows/ci.yml:29-42`, `mysql:8.0`,
   `MYSQL_DATABASE=dog_school_test`/`test_user`/`test_password`): ein
   temporärer `mysql:8.0`-Container im selben Docker-Netzwerk wie der
   bestehende `php`-Container gestartet, dann `artisan migrate` gegen
   diesen Container mit überschriebenen `DB_*`-Env-Variablen ausgeführt:
   ```
   docker run -d --name dog-school-mysql-test \
     --network dog-school-app_dog-school-network \
     -e MYSQL_ROOT_PASSWORD=root_password \
     -e MYSQL_DATABASE=dog_school_test \
     -e MYSQL_USER=test_user -e MYSQL_PASSWORD=test_password \
     mysql:8.0

   docker compose exec \
     -e DB_CONNECTION=mysql -e DB_HOST=dog-school-mysql-test -e DB_PORT=3306 \
     -e DB_DATABASE=dog_school_test -e DB_USERNAME=test_user -e DB_PASSWORD=test_password \
     php php artisan migrate:fresh --force
   ```
   → Vollständiger `migrate:fresh`-Lauf über **alle** Migrationen (inkl. T01
   und der bereits vorhandenen T02-Migration eines parallelen Agenten)
   erfolgreich, keine Fehler. Verifiziert per
   `DESCRIBE dogs;` in `dog-school-mysql-test`: `owner_since` (`date`),
   `age_at_acquisition` (`varchar(255)`), `origin`
   (`enum('breeder','shelter','private','unknown')` — natives MySQL-`ENUM`,
   wie in `design.md` Zeile 91 vorhergesagt).

4. **`php artisan migrate:rollback` auf MySQL:**
   ```
   docker compose exec \
     -e DB_CONNECTION=mysql -e DB_HOST=dog-school-mysql-test -e DB_PORT=3306 \
     -e DB_DATABASE=dog_school_test -e DB_USERNAME=test_user -e DB_PASSWORD=test_password \
     php php artisan migrate:rollback --path=database/migrations/2026_07_16_120000_add_owner_history_to_dogs_table.php --force
   ```
   → Verifiziert per `DESCRIBE dogs;`: alle drei Spalten entfernt. Danach
   erneut migriert (`artisan migrate --force`), um konsistent mit dem
   PostgreSQL-Test zu bleiben. Temporärer MySQL-Container anschließend
   entfernt (`docker rm -f dog-school-mysql-test`) — kein permanenter
   Bestandteil der Docker-Umgebung, nur für diesen Test verwendet.

5. **Kein raw SQL / `DB::statement()`:** Migration verwendet ausschließlich
   `Schema::table()` mit `Blueprint::date()`/`string()`/`enum()`/
   `dropColumn()`. Kein `DB::raw()`, kein `whereRaw()`, kein
   `DB::statement()`. Geprüft per Sichtprüfung der 27-Zeilen-Datei.

6. **Keine PHP-8.3/8.4-Konstrukte (manuelle Prüfung, kein automatisiertes
   `compat-check`-Script vorhanden, siehe `proposal.md`):** Datei enthält
   nur `use`-Imports, eine anonyme Migration-Klasse mit `up()`/`down()`,
   Standard-`Schema::table()`/`Blueprint`-Aufrufe. Kein `#[\Override]`,
   keine Typed Class Constants, kein Dynamic Class Constant Fetch, kein
   `json_validate()`, keine Property Hooks, keine Asymmetric Visibility,
   kein `new MyClass()->method()` ohne Klammern, keine 8.4-`array_*`-
   Funktionen — alles PHP-8.2-kompatibel.

## Ergebnis

Alle fünf Akzeptanzkriterien aus `tasks.md` T01 erfüllt und manuell
verifiziert (Migration, Rollback, jeweils auf PostgreSQL und MySQL, kein
raw SQL, keine 8.3/8.4-Konstrukte). Checkboxen in `tasks.md` T01
entsprechend abgehakt.

## Offene Punkte / Beobachtungen außerhalb des Scopes

- `docker-compose.mysql.yml` (in CLAUDE.md Abschnitt 5/7.1 referenziert)
  existiert nicht im Repo. Für zukünftige DB-bezogene Tasks in diesem oder
  anderen Changes empfiehlt es sich, diese Datei als eigenständigen
  openspec-Change nachzurüsten, damit der in CLAUDE.md beschriebene
  Pre-Flight-Workflow ohne manuelle Ad-hoc-Container reproduzierbar ist.
  Nicht in diesem Task behoben (Scope-Grenze: T01 betrifft nur die
  Migrationsdatei selbst).

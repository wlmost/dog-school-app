# T01 — Migration: Status "reminded" + Mahnstufen-Datenmodell

## Status
Implementiert. Alle Akzeptanzkriterien in `tasks.md` T01 abgehakt.

## Was wurde umgesetzt

### Neue Dateien

- `backend/database/migrations/2026_08_12_130001_add_reminded_status_to_invoices_table.php`
  (M1) — erweitert `invoices.status` treiberspezifisch um `reminded`,
  exakt nach dem Muster von
  `2026_05_04_110001_add_cancellation_requested_status_to_bookings_table.php`:
  - `mysql`: `ALTER TABLE ... MODIFY COLUMN status ENUM(...)`.
  - `pgsql`: bestehenden CHECK-Constraint `{prefix}invoices_status_check`
    droppen und mit dem zusätzlichen Wert neu anlegen.
  - `sqlite`: Tabelle per Copy-Rename (`invoices_new` → Drop `invoices` →
    Rename) neu angelegt, mit allen bestehenden Spalten (`id,
    customer_id, invoice_number, status, total_amount, issue_date,
    due_date, paid_date, notes, timestamps`) und Indizes (`customer_id,
    invoice_number, status, issue_date`) identisch zur ursprünglichen
    `2025_12_22_185107_create_invoices_table.php` nachgebildet.
  - `down()` setzt betroffene `reminded`-Zeilen vor dem Downgrade auf
    `sent` zurück (in allen drei Zweigen), analog zum Präzedenzfall.
- `backend/database/migrations/2026_08_12_130002_create_invoice_dunnings_table.php`
  (M2) — neue Tabelle `invoice_dunnings`
  (`invoice_id` FK cascade, `level` unsignedTinyInteger, `dunning_date`
  date, `fee_amount` decimal(10,2) default 0, Timestamps, Index auf
  `invoice_id`). Standard-Migration, kein treiberspezifischer Code.
- `backend/app/Models/InvoiceDunning.php` — `$fillable = ['invoice_id',
  'level', 'dunning_date', 'fee_amount']`, `casts()` mit `dunning_date =>
  'date'`, `fee_amount => 'decimal:2'`, Relation `invoice(): BelongsTo`.
  PHPDoc-`@var`-Typ für `$fillable` bewusst als `list<string>` (nicht
  `array<int, string>`) gewählt, analog `User.php` — vermeidet einen
  neuen PHPStan-Baseline-Eintrag (siehe QA-Abschnitt).
- `backend/database/factories/InvoiceDunningFactory.php` — Standard-
  Factory analog `PaymentFactory.php`, `invoice_id => Invoice::factory()`,
  `level` zufällig 1–3, `dunning_date => now()`, `fee_amount` zufällig
  0–25.

### Geänderte Dateien

- `backend/app/Models/Invoice.php`:
  - `dunnings(): HasMany` ergänzt.
  - `getDunningLevelAttribute(): ?int` ergänzt — `$this->dunnings->max('level')`,
    auf `int` gecastet, `null` wenn keine Mahnungen existieren
    (Abweichung von der wörtlichen Task-Vorlage: dort fehlender
    Cast/Null-Check hätte einen PHPStan-Fehler erzeugt, da
    `Collection::max()` `mixed` zurückgibt — funktional identisch).
  - `getRemindedAtAttribute(): ?Carbon` ergänzt —
    `$this->dunnings->sortByDesc('dunning_date')->first()?->dunning_date`.
  - PHPDoc `@property-read` um `Collection<int, InvoiceDunning> $dunnings`
    ergänzt.

## Tests / Checks — Ergebnisse

Alle Checks liefen in der lokalen Docker-Umgebung
(`docker compose exec php ...`), Standard-Verbindung dort ist Postgres
(`DB_CONNECTION=pgsql`, Service `postgres`).

- `composer qa` (lint + stan + compat-check + pest, SQLite In-Memory):
  **grün**. 771 Tests bestanden (2440 Assertions), PHPStan Level 5 ohne
  neue Fehler (nach Anpassung des `$fillable`-PHPDoc-Typs, siehe oben),
  Pint- und `compat-check`-Läufe fehlerfrei.
- `php artisan migrate:fresh` auf der laufenden Docker-Postgres-Instanz:
  **grün**, inkl. M1/M2 ohne Fehler.
- Manuelle funktionale Verifikation via `artisan tinker` auf Postgres:
  - `Invoice::update(['status' => 'reminded'])` erfolgreich, kein
    DB-Fehler.
  - `InvoiceDunning::create([...])` (zwei Datensätze, Level 1 und 2)
    über `$invoice->dunnings` korrekt zurückgelesen.
  - `$invoice->dunning_level` liefert `2` (höchste Stufe),
    `$invoice->reminded_at` liefert das Datum der jüngsten Mahnung.
- `php artisan migrate:rollback --step=2` auf Postgres: **grün** — Status
  der zuvor auf `reminded` gesetzten Zeile wurde vor dem Constraint-
  Downgrade automatisch auf `sent` zurückgesetzt; ein erneuter Versuch,
  `status = 'reminded'` zu setzen, schlägt danach mit
  `Illuminate\Database\QueryException` (CHECK-Constraint-Verletzung) fehl
  — erwartetes Verhalten.
  Anschließend wieder hochmigriert (`php artisan migrate`), DB-Zustand
  am Ende mit `migrate:fresh` bereinigt.
- **MySQL:** Im Repo existiert **kein** `docker-compose.mysql.yml`-Overlay
  (nur `docker-compose.yml` mit Postgres-Service ist vorhanden — geprüft
  per `find`/`grep`). CLAUDE.md Abschnitt 7.1 referenziert diese Datei,
  sie wurde aber offenbar noch nicht angelegt (kein eigener
  openspec-Change dafür im Repo gefunden). Um die MySQL-Kompatibilität
  dennoch nicht nur behauptet, sondern real zu verifizieren, habe ich
  **zusätzlich** einen Ad-hoc-Container `mysql:8.4` manuell im selben
  Docker-Netzwerk gestartet (`docker run --network
  dog-school-app_dog-school-network ... mysql:8.4`) und darüber via
  `docker compose exec -e DB_CONNECTION=mysql -e DB_HOST=... php
  artisan migrate:fresh` getestet:
  - `migrate:fresh` inkl. M1/M2: **grün**.
  - `Invoice::update(['status' => 'reminded'])`,
    `InvoiceDunning::create()`, `dunning_level`/`reminded_at`: identisch
    zu Postgres verifiziert, **grün**.
  - `migrate:rollback --step=2`: **grün**, Status-Zurücksetzung auf
    `sent` funktioniert, anschließender `reminded`-Update-Versuch schlägt
    mit `QueryException` fehl (MySQL-ENUM lässt den Wert nach Downgrade
    nicht mehr zu).
  - Der Ad-hoc-Container wurde danach entfernt (`docker rm -f
    dog-school-mysql-test`) — er ist **kein** Teil des Repos und wurde
    nicht committet. Diese Verifikation ersetzt **nicht** die im
    Change/CLAUDE.md vorgesehene feste `docker-compose.mysql.yml`-Matrix
    (separate Infrastruktur-Aufgabe, außerhalb des Scopes von T01) — sie
    dient hier ausschließlich als ehrlicher Beleg, dass die
    MySQL-spezifischen Raw-SQL-Zweige der Migration syntaktisch und
    funktional korrekt sind.

## Abweichungen von der Task-Beschreibung

- `getDunningLevelAttribute()` castet den Rückgabewert von
  `Collection::max()` explizit auf `int`/`null`, statt ihn ungecastet
  zurückzugeben wie im Codeschnipsel der Task-Beschreibung. Grund: PHP
  8.2-typisierte Rückgabe `?int` vs. `mixed`-Rückgabe von `max()` —
  ohne Cast wäre das Verhalten zur Laufzeit identisch (da `level` als
  `unsignedTinyInteger` immer ein Integer ist), aber sauberer typisiert
  und PHPStan-konform.
- `InvoiceDunning::$fillable`-PHPDoc nutzt `list<string>` statt
  `array<int, string>` (Details siehe oben) — rein PHPDoc-Ebene, keine
  Verhaltensänderung.
- MySQL-Verifikation lief gegen einen manuell gestarteten Ad-hoc-
  Container statt gegen die in CLAUDE.md referenzierte
  `docker-compose.mysql.yml`, da diese Datei im Repo nicht existiert
  (siehe Testergebnisse oben für Details und Begründung).

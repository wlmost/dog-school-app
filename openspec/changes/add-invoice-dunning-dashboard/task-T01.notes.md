# Notes: T01 — Schema-Fundament — `document_type`, `fee_invoice_id`, Config, `DunningFeeSchedule`

## Umgesetzt

- `backend/database/migrations/2026_08_14_140001_add_document_type_to_invoices_table.php`
  (neu): `invoices.document_type` (nullable `string`, `after('original_invoice_id')`)
  + Index, additiv. Im selben `up()` Backfill:
  `DB::table('invoices')->whereNotNull('original_invoice_id')->update(['document_type' => 'cancellation'])`
  — schreibt alle bereits existierenden Stornorechnungen zurück, sicher
  weil `original_invoice_id` gemäß `verification.md` bislang ausschließlich
  von `InvoiceController::cancel()` gesetzt wird.
- `backend/database/migrations/2026_08_14_140002_add_fee_invoice_id_to_invoice_dunnings_table.php`
  (neu): `invoice_dunnings.fee_invoice_id` (nullable FK auf `invoices`,
  `after('fee_amount')`, `->constrained('invoices')->nullOnDelete()`)
  + Index, additiv, kein Backfill nötig (Tabelle enthielt bislang nur
  Test-Fixtures).
- `backend/config/invoicing.php` (neu): `dunning_fees` (Level 1/2/3, Default
  5.00/10.00/15.00, überschreibbar via `DUNNING_FEE_LEVEL_1/2/3`),
  `max_dunning_level = 3`. Kein `declare(strict_types=1)` — Bestandskonvention
  aller Config-Dateien in `backend/config/` verzichtet darauf (verifiziert
  gegen `config/paypal.php`/`config/dompdf.php`), CLAUDE.md Abschnitt 6
  schreibt `declare(strict_types=1)` explizit nur für `backend/app/` vor.
- `backend/app/Support/DunningFeeSchedule.php` (neu): `feeForLevel(int $level): ?float`,
  `nextLevel(?int $currentLevel): ?int`. Als **statische** Methoden
  implementiert (design.md Decision D4 spezifiziert dies explizit
  wörtlich: "statische Methoden feeForLevel(...)/nextLevel(...)"; auch die
  Akzeptanzkriterien in `tasks.md` sind mit `::`-Notation formuliert:
  `DunningFeeSchedule::nextLevel(null) === 1`).
- `backend/app/Models/Invoice.php`:
  - `document_type` zu `$fillable` ergänzt (nach `original_invoice_id`).
  - `cancellationInvoice(): HasOne` erhält `->where('document_type', 'cancellation')`.
  - Neue `dunningFeeInvoices(): HasMany` mit `->where('document_type', 'dunning_fee')`.
  - Neue `getNextDunningLevelAttribute(): ?int` und
    `getNextDunningFeeAmountAttribute(): ?float`, beide über
    `DunningFeeSchedule` berechnet aus dem bestehenden `dunning_level`-Attribut.
  - Docblock (`@property`/`@property-read`) um `document_type`,
    `dunningFeeInvoices`, `nextDunningLevel`, `nextDunningFeeAmount` ergänzt.
- `backend/app/Models/InvoiceDunning.php`: `fee_invoice_id` zu `$fillable`
  ergänzt, neue `feeInvoice(): BelongsTo` (`Invoice::class, 'fee_invoice_id'`),
  Docblock entsprechend erweitert.
- `backend/tests/Feature/DatabaseStructureTest.php`:
  - `invoice_dunnings table exists with required columns` um `fee_invoice_id`
    erweitert.
  - Neuer Test `invoices table has document_type column`.
  - Neuer Test `document_type backfill sets cancellation on pre-existing
    cancellation invoices`: rollt gezielt die Migration
    `2026_08_14_140001_...` zurück (`Artisan::call('migrate:rollback', ['--path' => ...])`),
    legt danach — auf dem Schema-Stand **vor** `document_type` — eine
    Stornorechnung per direktem `DB::table('invoices')->insert(...)` mit
    gesetztem `original_invoice_id` an, migriert die Datei erneut vor
    (`Artisan::call('migrate', ['--path' => ...])`) und prüft, dass
    `document_type` danach `'cancellation'` ist. Simuliert damit exakt den
    in `tasks.md` geforderten Produktivfall (Datensatz existiert, bevor die
    Spalte da ist), nicht nur ein `assertDatabaseHas` nach vollständigem
    `RefreshDatabase`-Lauf (der die Reihenfolge nicht abbilden würde).

## Über den Task-Dateikatalog hinaus angelegt (zur Erfüllung der Akzeptanzkriterien)

`tasks.md` listet für T01 keine eigene Test-Datei für die Regressions-
und Unit-Akzeptanzkriterien (nur `DatabaseStructureTest.php`). Da die
Akzeptanzkriterien selbst aber explizit Tests für `cancellationInvoice()`
(Decision-D1-Regression) und `DunningFeeSchedule` fordern, wurden — analog
zum bereits bestehenden Präzedenzfall `tests/Unit/Models/InvoiceDunningTest.php`
aus `add-invoice-status-lifecycle` T01 (dort ebenfalls als dokumentierte
Lücke nachgezogen) — zwei neue, kleine Testdateien ergänzt:

- `backend/tests/Unit/Support/DunningFeeScheduleTest.php` (neu,
  `uses()->group('unit', 'support')`): deckt `nextLevel(null) === 1`,
  `nextLevel(1) === 2`, `nextLevel(2) === 3`, `nextLevel(3) === null`,
  `feeForLevel(1/2/3)` gegen die konfigurierten Default-Beträge sowie
  `feeForLevel(4) === null` ab. Bewusst lokal an `Tests\TestCase` gebunden
  (`uses(TestCase::class)`, ohne `RefreshDatabase`) — abweichend vom
  `tests/Pest.php`-Standard für `tests/Unit/` ("ohne Container"), weil
  `config('invoicing...')` den gebooteten Laravel-Container benötigt.
  Kein DB-Zugriff, daher kein `RefreshDatabase`. Gleiches Muster wie die
  bereits bestehenden `tests/Unit/Models/*Test.php`.
- `backend/tests/Unit/Models/InvoiceTest.php` (neu, `uses()->group('unit', 'invoice')`,
  `uses(TestCase::class, RefreshDatabase::class)`): deckt den D1-Regressionstest
  (`cancellationInvoice()` liefert weiterhin ausschließlich die echte
  Stornorechnung, auch wenn zusätzlich ein `dunning_fee`-Dokument mit
  `original_invoice_id` existiert), `dunningFeeInvoices()` sowie
  `next_dunning_level`/`next_dunning_fee_amount` (Stufe 1 ohne Vorgeschichte,
  `null` bei bereits erreichter Stufe 3, korrekter Betrag bei Stufe 1) ab.

## Bestandskorrektur (notwendig für `composer test` grün)

`backend/tests/Feature/InvoiceApiTest.php`, Test
`'InvoiceResource exposes cancellation invoice fields on both sides of the
relation'`: die Fixture erzeugte die simulierte Stornorechnung bislang nur
mit `original_invoice_id`, ohne `document_type`. Nach der neuen
`->where('document_type', 'cancellation')`-Filterung in
`cancellationInvoice()` fand die Relation dieses Fixture nicht mehr
(`cancellationInvoiceId` wurde `null` statt der erwarteten ID). Fixture um
`'document_type' => 'cancellation'` ergänzt — reine Testdaten-Korrektur,
keine Änderung der Assertion-Logik.

## Wichtiger Befund für Architekt/Reviewer (nicht in T01-Scope behebbar)

**`InvoiceController::cancel()`/`createCancellationInvoiceWithRetry()`
setzt aktuell `document_type` auf neu erzeugten Stornorechnungen nicht.**
Das Migrations-Backfill (M1) deckt nur *bereits bestehende*
Stornorechnungen ab (`WHERE original_invoice_id IS NOT NULL`, zum
Migrationszeitpunkt). Für alle **nach** dieser Migration über den
`cancel()`-Endpunkt neu erzeugten Stornorechnungen bleibt `document_type`
`null`, solange `InvoiceController.php` nicht angepasst wird — dadurch
würde `Invoice::cancellationInvoice()` diese *zukünftigen* Stornorechnungen
nicht mehr finden (Regression des in T01 gerade behobenen Verhaltens,
diesmal umgekehrt: die echte Stornorechnung würde fälschlich *nicht*
gefunden statt ein Gebührendokument fälschlich *gefunden*).

Geprüft: weder `tasks.md` (T01–T10) noch `design.md` (Decision D1/D2)
sehen eine Anpassung von `InvoiceController::cancel()` explizit vor —
D1 beschreibt nur die Migrations-Backfill-Logik und die Relation-Filterung,
D2 hält ausdrücklich fest, dass `cancel()` "unverändert" bleibt (dort im
Kontext des Retry-Musters, nicht explizit im Kontext von `document_type`).
Das ist nach meiner Einschätzung eine **Lücke im Change**, kein bewusster
Non-Goal — `add-invoice-status-lifecycle`s ursprüngliches Design ging von
genau einem Kind-Dokument pro Original-Rechnung aus, und ohne die
`document_type`-Zuweisung in `cancel()` würde dieser Change die Lücke, die
er schließen soll, für neue Daten wieder öffnen.

**Empfehlung:** `InvoiceController::createCancellationInvoiceWithRetry()`
sollte beim `Invoice::create([...])`-Aufruf zusätzlich
`'document_type' => 'cancellation'` setzen. Da `InvoiceController.php`
nicht in T01s Dateikatalog steht und der Controller in keiner der Tasks
T01–T10 für diesen Zweck angefasst wird, wurde diese Änderung hier
**bewusst nicht vorgenommen** (außerhalb des Scopes von T01) — sollte aber
vor Abschluss des Changes (spätestens beim Architekten-Review Modus B)
nachgezogen werden, entweder als Ergänzung zu T02 (die
`InvoiceDunningRecorder`-Task, die ohnehin das Gebührendokument mit
`document_type = 'dunning_fee'` erzeugt und damit bereits Erfahrung mit
der neuen Spalte hat) oder als eigener kleiner Task.

## Verifikation

```
docker compose exec php composer lint          # PASS, 322 files
docker compose exec php composer stan          # No errors (210/210)
docker compose exec php composer compat-check  # keine Ausgabe, exit 0
docker compose exec php composer test          # 869 passed (2679 assertions), 2 skipped (PostgreSQL-Concurrency, s. task-T02.notes.md aus add-invoice-payment-entry)
docker compose exec php composer qa            # aggregiert alle vier, exit 0
```

Getestet gegen SQLite (`DB_CONNECTION=sqlite`, `:memory:` aus `phpunit.xml`,
via `docker compose exec php`) — entspricht dem in `tasks.md` T01
geforderten Akzeptanzkriterium. Kein `docker-compose.mysql.yml` im
Repo vorhanden (das in CLAUDE.md Abschnitt 7.1 referenzierte
MySQL-Matrix-Setup existiert noch nicht — vermutlich Teil des in
`design.md` erwähnten, noch ausstehenden `add-db-matrix-ci`-Changes);
der lokale Postgres-Service aus `docker-compose.yml` wird von
`composer test` nicht genutzt (Test-DB ist laut `phpunit.xml` fest auf
SQLite gepinnt), daher kein zusätzlicher Postgres-Lauf für T01 möglich/nötig
— die additive Spalten-/FK-Syntax (`$table->string()->nullable()`,
`$table->foreignId()->nullable()->constrained()->nullOnDelete()`) ist
laut `design.md`-Migrationsabschnitt ohnehin treiberunabhängig.

## Offene Punkte für T02/T03/Reviewer

- Siehe "Wichtiger Befund" oben: `InvoiceController::cancel()` fehlt die
  `document_type`-Zuweisung für neue Stornorechnungen — vor Abnahme des
  Gesamt-Changes klären/nachziehen.
- `DunningFeeSchedule` ist bewusst als statische Utility-Klasse ohne
  Konstruktor-Injection implementiert (siehe oben) — T02
  (`InvoiceDunningRecorder`) sollte dieselbe `::`-Aufrufkonvention nutzen,
  nicht per DI instanziieren.

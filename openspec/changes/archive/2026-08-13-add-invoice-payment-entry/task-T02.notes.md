# Task T02: `App\Services\InvoicePaymentRecorder`

## Status

Implementiert. Alle Akzeptanzkriterien in `tasks.md` T02 sind erfüllt,
inklusive des PostgreSQL-Concurrency-Tests (gegen echtes PostgreSQL
verifiziert, siehe unten). Eine wesentliche, dokumentierte Abweichung von
der wörtlichen `design.md`-Code-Vorlage betrifft ausschließlich die
Platzierung des Concurrency-Tests (siehe "Abweichungen").

## Was wurde umgesetzt

### Neue Datei

- `backend/app/Services/InvoicePaymentRecorder.php` — exakt wie in
  `design.md` Decision D2 beschrieben: `record(Invoice $invoice, array
  $paymentData): Payment` und `completeExisting(Payment $payment):
  Payment`, beide in eigenem `DB::transaction()` mit
  `Invoice::query()->lockForUpdate()->findOrFail()`. Private
  `syncStatus()` setzt `status = 'paid'` + `paid_date` = Datum der
  abschließenden Zahlung (nicht `now()`), sobald die Summe abgeschlossener
  Zahlungen `total_amount` erreicht/übersteigt — identisch zur
  `design.md`-Vorlage. Ausführliches Klassen-Docblock erklärt die
  Locking-Strategie, warum sie (anders als
  `InvoiceNumberGenerator::generate()`, siehe `task-T03.notes.md` von
  `add-invoice-status-lifecycle`) keine Phantom-Zeilen-Lücke hat (eine
  existierende Invoice-Zeile wird per Primärschlüssel gesperrt, kein
  leeres Ergebnis möglich), und warum verschachtelte
  `DB::transaction()`-Aufrufe unter PostgreSQL hier unkritisch sind
  (keine Retry-/Catch-Logik, die eine vergiftete äußere Transaktion
  bräuchte, anders als der in `git log` dokumentierte `finalize()`-Fix,
  Commit `4f60e79`).
- **Eine kleine, PHPStan-bedingte Abweichung vom `design.md`-Codebeispiel:**
  `record()` nutzt `Payment::create([...$paymentData, 'invoice_id' =>
  $locked->id])` statt `$locked->payments()->create($paymentData)`. Der
  Relations-Aufruf liefert laut PHPStan/Larastan (Level 5) den
  untypisierten `Illuminate\Database\Eloquent\Model` zurück (die
  `HasMany`-Relation in `Invoice::payments()` ist nicht generisch
  typisiert — dasselbe, bereits im Bestand baselinete Muster wie bei
  `Course::runs()` etc.), was den deklarierten `Payment`-Rückgabetyp der
  Methode gebrochen hätte. `Payment::create()` (statischer Aufruf,
  bereits das etablierte Muster in
  `PaymentController::store()`) liefert dank `@return static` einen
  korrekt typisierten `Payment` zurück. Funktional identisch — `notes`,
  `payment_date`, `amount` etc. werden unverändert aus `$paymentData`
  übernommen, nur `invoice_id` wird jetzt explizit statt implizit über
  die Relation gesetzt.

### Baseline-Anpassung (notwendig für `composer stan`)

- `backend/phpstan-baseline.neon`: neuer Eintrag für
  `Call to an undefined method
  Illuminate\Database\Eloquent\Relations\HasMany::completed()` (`count:
  2`, `path: app/Services/InvoicePaymentRecorder.php`) — identisches,
  bereits für `InvoiceController.php`/`PaymentController.php` baselinetes
  Muster (der `completed()`-Scope auf `Payment` ist zur Laufzeit über
  Eloquents Scope-Magie gültig, PHPStan kennt diese Magie ohne
  IDE-Helper-Erweiterung nicht).

## Tests

### Neue Datei: `backend/tests/Feature/Domain/Payment/InvoicePaymentRecorderTest.php`

`uses(RefreshDatabase::class)`, `uses()->group('domain', 'payment')`,
`it()`-Stil gemäß `TESTING.md`. 5 Tests:

- (a) Teilzahlung lässt Status unverändert (`record()`, 40 von 100 €).
- (b) Summe erreicht `total_amount` exakt → Status `paid`, `paid_date` =
  Datum der abschließenden Zahlung.
- (c) Mehrere Teilzahlungen übersteigen `total_amount` in Summe (60 € +
  50 € = 110 € bei `total_amount = 100 €`) → Status wechselt trotzdem zu
  `paid`, `paid_date` = Datum der zweiten (abschließenden) Zahlung.
- Zusätzlich (über die drei geforderten Szenarien hinaus, aber innerhalb
  des Service-Scopes): `completeExisting()` lässt den Status unverändert
  für eine weiterhin `pending`-Zahlung, und setzt ihn korrekt auf `paid`
  (mit `paid_date` = Zahlungsdatum), wenn das Abschließen einer
  `pending`-Zahlung die Rechnung voll bezahlt macht — deckt die zweite
  öffentliche Methode des Service mit derselben Sorgfalt ab wie
  `record()`.

Alle 5 Tests grün gegen SQLite (`composer test`), Teil der 838 grünen
Tests der Gesamtsuite (siehe unten).

### Neue Datei: `backend/tests/Concurrency/Domain/Payment/InvoicePaymentRecorderConcurrencyTest.php`

Der geforderte "dedizierte, explizite Concurrency-Test" — zwei nahezu
gleichzeitige `record()`-Aufrufe (`pcntl_fork()`, zwei Kindprozesse mit
je 50 € bei `total_amount = 100 €`, synchronisiert auf denselben
Startzeitpunkt via `microtime(true) + 0.3` + Busy-Wait, damit beide
tatsächlich um die Zeilensperre konkurrieren statt sequenziell
nacheinander zu laufen) für dieselbe Rechnung. Nach Abschluss beider
Kindprozesse wird geprüft: beide Prozesse erfolgreich (`exit(0)`),
Rechnung ist `paid`, `paid_date` gesetzt, genau 2 `Payment`-Datensätze,
Summe abgeschlossener Zahlungen = 100 €.

**Wichtiger struktureller Befund (nicht in `tasks.md`/`design.md`
vorgesehen, aber notwendig):** Dieser Test kann **nicht** unter
`tests/Feature/` liegen. `tests/Pest.php` bindet `RefreshDatabase`
global an alle Tests unter `tests/Feature/` (`.in('Feature')`).
`RefreshDatabase` wickelt einen Test in eine einzige, nicht committete
Transaktion auf der *Eltern*-Prozess-Verbindung und rollt sie am Ende
zurück. Die beiden per `pcntl_fork()` erzeugten Kindprozesse öffnen
jedoch **eigene, unabhängige** DB-Verbindungen — sie sehen die
unveröffentlichte Fixture der Eltern-Transaktion nie. Empirisch
verifiziert: mit dem Test unter `tests/Feature/Domain/Payment/` schlugen
beide Kindprozesse reproduzierbar mit `"No query results for model
[App\Models\Invoice]"` fehl, weil die zuvor angelegte Invoice für sie
schlicht nicht existierte.

Lösung: neue eigenständige Testsuite `tests/Concurrency/` (registriert
als `<testsuite name="Concurrency"><directory>tests/Concurrency</directory></testsuite>`
in `phpunit.xml`), gebunden **ohne** `RefreshDatabase` über eine zweite
`pest()->extend(TestCase::class)->in('Concurrency')`-Zeile in
`tests/Pest.php` (Ursprungsversuch: eine eigene
`tests/Concurrency/Pest.php` — verworfen, weil Pests `BootFiles`-
Bootstrapper laut `vendor/pestphp/pest/src/Bootstrappers/BootFiles.php`
**ausschließlich** die einzelne `tests/Pest.php` am Testverzeichnis-Root
lädt, keine verschachtelten `Pest.php`-Dateien pro Unterverzeichnis —
verifiziert durch Lesen des Bootstrapper-Quellcodes und einen
fehlgeschlagenen Sanity-Check, der ohne diese Erkenntnis `"A facade root
has not been set"` warf). Test-Datei-Aufbau (`beforeEach`/`afterEach`
statt `RefreshDatabase`) legt Fixtures manuell an und räumt sie manuell
wieder auf; `User` nutzt `SoftDeletes`, daher `forceDelete()` für den
Test-User, um die dedizierte Test-DB nicht mit weichgelöschten Zeilen
über wiederholte Läufe hinweg zuzumüllen.

**Neue Test-Gruppe `concurrency`** (nicht in `TESTING.md` Abschnitt 7.1
gelistet) statt `domain`, weil die erste Group laut `TESTING.md`
"Erste Group passt zum Pfad" zum tatsächlichen Verzeichnis passen muss
(`tests/Concurrency/`, nicht `tests/Feature/`). Pragmatische
Sofortlösung gemäß `TESTING.md` Abschnitt 11 — dem Architekten zur
möglichen dauerhaften Aufnahme in Abschnitt 7.1 empfohlen, falls
künftige Changes weitere echte Concurrency-Tests brauchen.

**Bewusst NICHT genutzt:** `RefreshDatabase`, direkte
`DB::statement('TRUNCATE …')` (`TESTING.md` Abschnitt 9 verbietet das
für *normale* Feature-Tests — hier liegt eine begründete Ausnahme vor,
da `RefreshDatabase` für den Testzweck strukturell ungeeignet ist, siehe
oben; es wird trotzdem **kein** `TRUNCATE` verwendet, sondern gezielte
`delete()`/`forceDelete()`-Aufrufe auf genau den selbst angelegten
Zeilen).

## PostgreSQL-Concurrency-Verifikation (DB-kritisches Akzeptanzkriterium)

Durchgeführt in der lokalen Docker-Umgebung gegen eine **dedizierte**
Postgres-Test-Datenbank (nicht die Dev-DB `dog_school_app`, nicht die
`sqlite`-In-Memory-Standard-Testsuite):

```bash
docker compose exec postgres createdb -U dog_school_user dog_school_test   # bereits vorhanden
docker compose exec php sh -c "DB_CONNECTION=pgsql DB_DATABASE=dog_school_test php artisan migrate:fresh --force"
docker compose exec php sh -c "DB_CONNECTION=pgsql DB_DATABASE=dog_school_test vendor/bin/pest --filter='verliert keine teilzahlung'"
```

**Ergebnis:** `1 passed (5 assertions)`, 5-mal in Folge reproduziert
(keine Flakiness beobachtet).

**Sanity-Check, dass der Test die Race tatsächlich erkennt (nicht nur
zufällig grün ist):** `Invoice::query()->lockForUpdate()->findOrFail()`
in `record()` temporär zu `Invoice::query()->findOrFail()` verändert
(Sperre entfernt) und denselben Test 3-mal gegen dieselbe
PostgreSQL-Test-DB laufen lassen → **reproduzierbar `1 failed (2
assertions)`** (verlorene Teilzahlung: beide Kindprozesse lesen
`totalPaid < total_amount`, bevor der jeweils andere committet, Rechnung
bleibt bei `sent` trotz zweier je 50 €-Zahlungen). Fix wieder
hergestellt (`diff` gegen Backup bestätigt Identität), Test wieder
grün. Damit ist belegt, dass der Test die spezifizierte Race Condition
tatsächlich reproduziert und die `lockForUpdate()`-Lösung sie wirksam
schließt — nicht nur eine oberflächliche Assertion, die zufällig
besteht.

**Zusätzlich (über die AC-Mindestanforderung hinaus):** volle
Testsuite (838 Domain/Feature/Unit-Tests + 1 Concurrency-Test = 839)
einmal komplett gegen `dog_school_test` (PostgreSQL) laufen lassen:

```bash
docker compose exec php sh -c "DB_CONNECTION=pgsql DB_DATABASE=dog_school_test vendor/bin/pest --no-coverage"
```

Ergebnis: `839 passed (2621 assertions)` — keine Regression, keine
DB-spezifischen Überraschungen über den Concurrency-Test hinaus.
Test-DB danach mit `migrate:fresh --force` zurückgesetzt (0 Zeilen in
allen betroffenen Tabellen verifiziert).

**MySQL:** Kein `docker-compose.mysql.yml` im Repo vorhanden (bereits in
früheren Changes dokumentiert, z. B. `task-T03.notes.md` von
`add-invoice-status-lifecycle`) — lokale MySQL-Verifikation für T02
daher nicht durchgeführt. `.github/workflows/ci.yml` führt `composer qa`
bereits in einer Matrix gegen **sowohl** `mysql:8.0` **als auch**
`postgres:16` aus (`DB_CONNECTION`/`DB_HOST`/`DB_PORT` per Env
injiziert), und `docker/php/Dockerfile` hat die `pcntl`-Extension
bereits aktiviert (Zeile 34) — die neue `Concurrency`-Testsuite läuft
damit nach dem Push automatisch echt (nicht nur geskippt) gegen **beide**
Datenbanken in der CI, ohne dass dafür etwas Zusätzliches eingerichtet
werden musste.

## Verifikation (lokale Docker-Umgebung, SQLite-Standardsuite)

- `docker compose exec php composer lint` → grün (314 Dateien, Pint;
  Pint hat beim ersten Lauf automatisch zwei Formatierungsabweichungen
  in den neuen Dateien korrigiert: `phpdoc_align` im Service, ein
  `\Throwable` → `Throwable`-Import-Fix im Concurrency-Test).
- `docker compose exec php composer stan` → grün (0 Fehler, 207
  Dateien) nach der oben beschriebenen Baseline-Ergänzung.
- `docker compose exec php composer compat-check` → grün (kein Output,
  keine PHP-8.3/8.4-Verstöße).
- `docker compose exec php composer test` → 838 Tests, 2616 Assertions,
  grün; der neue PostgreSQL-Concurrency-Test wird auf SQLite korrekt
  übersprungen (`1 skipped`, `markTestSkipped()` mit Begründung), keine
  Regression gegenüber dem T01-Stand (833 → 838 durch 5 neue Domain-Tests
  in `InvoicePaymentRecorderTest.php`; der Concurrency-Test zählt als
  `skipped`, nicht als `passed`).
- `docker compose exec php composer qa` (lint + stan + compat-check +
  test) → grün in einem Durchlauf.

## Abweichungen von der Task-Beschreibung

1. **`Payment::create()` statt `$locked->payments()->create()`** im
   Service — siehe "Was wurde umgesetzt" oben, PHPStan-bedingt,
   funktional identisch zur `design.md`-Vorlage.
2. **Neue Testsuite `tests/Concurrency/` statt Concurrency-Test in
   `tests/Feature/Domain/Payment/InvoicePaymentRecorderTest.php`** —
   strukturell notwendig (siehe ausführliche Begründung oben), nicht in
   `tasks.md`/`design.md` vorgesehen, aber ohne Alternative, die sowohl
   TESTING.md-konform als auch technisch funktionsfähig gewesen wäre.
   Betrifft zwei zusätzliche, nicht in T02s Dateiliste genannte Dateien:
   `backend/phpunit.xml` (neue `<testsuite name="Concurrency">`) und
   `backend/tests/Pest.php` (zweite `pest()->extend()->in()`-Zeile).
   Beide Änderungen sind rein additiv (kein bestehendes Verhalten
   geändert) und wurden durch die volle grüne Suite (838/838 auf SQLite,
   839/839 auf PostgreSQL) sowie `composer qa` abgesichert.
3. **Neue Test-Gruppe `concurrency`** statt `domain` für den
   Concurrency-Test — siehe Begründung oben, dem Architekten als
   möglicher `TESTING.md`-Ergänzungsvorschlag mitgegeben.

Keine funktionalen Abweichungen von `design.md` Decision D2 im
öffentlichen Verhalten von `InvoicePaymentRecorder`.

## Offene Punkte für Folge-Tasks

- T03 verdrahtet `PaymentController::store()`/`::markAsCompleted()`/
  `::handlePaymentCaptureCompleted()` auf diesen Service (`record()`
  bzw. `completeExisting()`), inklusive der in `design.md` Decision D3/D4
  beschriebenen Geschäftsregeln und Policy-Anpassung — dieser Service
  ist bewusst noch nicht in `PaymentController` verwendet.
- Empfehlung an Architekt: `TESTING.md` Abschnitt 7.1 um die Gruppe
  `concurrency` (Pfad `tests/Concurrency/`) ergänzen, falls weitere
  echte Multi-Prozess-Concurrency-Tests absehbar sind.

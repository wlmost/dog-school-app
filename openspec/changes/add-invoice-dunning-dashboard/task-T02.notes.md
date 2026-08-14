# Notes: T02 — `App\Services\InvoiceDunningRecorder`

## Status

Implementiert. Alle Akzeptanzkriterien in `tasks.md` T02 sind erfüllt,
inklusive des PostgreSQL-Concurrency-Tests (gegen echtes PostgreSQL
verifiziert, siehe unten).

## Umgesetzt

- `backend/app/Services/InvoiceDunningRecorder.php` (neu):
  `trigger(Invoice $invoice): InvoiceDunning`, innerhalb eines
  `DB::transaction()` mit `Invoice::query()->lockForUpdate()->findOrFail()`.
  Innerhalb der gesperrten Sektion (design.md Decision D3): Eligibility-
  Prüfung (`document_type === null` und `status` in `['sent', 'reminded',
  'overdue']` als `self::ELIGIBLE_STATUSES`, sonst
  `InvoiceDunningNotEligibleException`), danach Stufen-Prüfung über
  `DunningFeeSchedule::nextLevel($locked->dunning_level)` (`null` →
  `InvoiceDunningLevelExceededException`). Danach Gebührendokument über
  private `createFeeInvoiceWithRetry()` (Unique-Constraint-Retry-Schleife,
  `self::FEE_INVOICE_MAX_ATTEMPTS = 3`, jeder Versuch eigene genestete
  `DB::transaction()`, 1:1-Muster wie
  `InvoiceController::createCancellationInvoiceWithRetry()`, inkl.
  `document_type = 'dunning_fee'`, `original_invoice_id` auf die
  Original-Rechnung, `status = 'sent'`, `total_amount = Gebühr`). Danach
  eine `InvoiceItem`-Zeile ("Mahngebühr Stufe {level} zu Rechnung
  {invoice_number}", `quantity=1`, `unit_price=amount=Gebühr`,
  `tax_rate=0`) auf dem Gebührendokument. Danach `InvoiceDunning::create([...])`
  mit `fee_invoice_id`. Danach `$locked->update(['status' => 'reminded'])`.
  Rückgabe: `$dunning->fresh(['invoice', 'feeInvoice'])`.
  `InvoiceNumberGenerator` wird per Konstruktor-Injection (`private
  readonly`) bezogen, damit `createFeeInvoiceWithRetry()` (analog zu
  `InvoiceController::cancel()`) eine eindeutige Rechnungsnummer je Versuch
  ziehen kann.
- `backend/app/Exceptions/InvoiceDunningNotEligibleException.php` (neu):
  `\RuntimeException`, readonly `invoiceId`/`documentType`/`status`,
  Nachricht unterscheidet zwischen "ist selbst ein Kind-Dokument" und
  "hat nicht-mahnfähigen Status" — analog zu `InvoiceOverpaymentException`
  gestaltet.
- `backend/app/Exceptions/InvoiceDunningLevelExceededException.php` (neu):
  `\RuntimeException`, readonly `invoiceId`/`maxDunningLevel`, sprechende
  Nachricht — analog zu `InvoiceOverpaymentException` gestaltet.

## Tests

### Neue Datei: `backend/tests/Feature/Domain/Invoice/InvoiceDunningRecorderTest.php`

`uses(RefreshDatabase::class)`, `uses()->group('domain', 'invoice')`,
`it()`-Stil gemäß `TESTING.md`. 6 Tests (eine davon mit `->with([...])`
für die drei nicht-mahnfähigen Status, daher 8 tatsächliche Assertions-Läufe):

- Erste Mahnung erzeugt Level 1, Gebührendokument mit `document_type =
  'dunning_fee'`, korrektem Betrag (5,00 €, aus `config/invoicing.php`
  Default), genau einer `InvoiceItem`-Zeile mit Stufe/Rechnungsnummer im
  Text, Statuswechsel der Original-Rechnung auf `reminded`.
- Zweite Mahnung auf bereits gemahnter Rechnung erzeugt Level 2 (10,00 €),
  zwei `InvoiceDunning`-Datensätze insgesamt.
- Vierter Trigger-Versuch nach Level 3 wirft
  `InvoiceDunningLevelExceededException`, ohne einen vierten
  `InvoiceDunning`-Datensatz zu erzeugen (weiterhin genau 3).
- Trigger auf `draft`/`paid`/`cancelled`-Rechnung (parametrisiert via
  `->with(['draft', 'paid', 'cancelled'])`) wirft
  `InvoiceDunningNotEligibleException`, keine Mahnung erfasst.
- Trigger auf dem Gebührendokument selbst (`document_type = 'dunning_fee'`)
  wirft `InvoiceDunningNotEligibleException`.
- `total_amount` der Original-Rechnung bleibt nach drei aufeinanderfolgenden
  Mahnungen exakt der ursprüngliche Wert (Kernkriterium der bindenden
  Entscheidung 1 — Mahngebühren mutieren nicht die Original-Rechnung).

Alle 8 Tests grün gegen SQLite.

### Neue Datei: `backend/tests/Concurrency/Domain/Invoice/InvoiceDunningRecorderConcurrencyTest.php`

Analog zu `InvoicePaymentRecorderConcurrencyTest.php` (bereits bestehende
`tests/Concurrency/`-Testsuite aus `add-invoice-payment-entry` T02
wiederverwendet, keine neue Infrastruktur nötig — `tests/Pest.php` bindet
sie bereits ohne `RefreshDatabase`, `phpunit.xml` hat die Testsuite bereits
registriert). Zwei `pcntl_fork()`-Kindprozesse rufen `trigger()`
nahezu gleichzeitig für dieselbe Rechnung auf (synchronisiert auf
`microtime(true) + 0.3` + Busy-Wait).

**Wichtige Abweichung von der wörtlichen Formulierung in `tasks.md`
("bestätigt genau einen Übergang auf Level 1")**: Anders als beim
Überzahlungs-Fall (`InvoiceOverpaymentException`, wo der *zweite* Aufruf
abgelehnt werden **muss**) gibt es hier keinen legitimen Grund, warum der
zweite Aufruf scheitern sollte — sobald der erste committet hat, liest der
zweite (nach dem Warten auf die Zeilensperre) korrekt `dunning_level = 1`
und rückt legitim auf Stufe 2 vor (`DunningFeeSchedule::nextLevel(1) ===
2` ist gültig). Bei funktionierender Sperre laufen daher **beide** Aufrufe
erfolgreich durch, aber seriell — mit dem Ergebnis **eine** Stufe-1- und
**eine** Stufe-2-Mahnung, nie zwei Stufe-1-Mahnungen. Das ist die
eigentliche Bedeutung von "keine doppelte Stufe" aus dem Akzeptanzkriterium
und wurde entsprechend im Test-Docblock dokumentiert. Der Test prüft
final: beide Kindprozesse `exit(0)`, genau 2 `InvoiceDunning`-Datensätze,
deren sortierte Levels exakt `[1, 2]` sind (keine Duplikate), Rechnung
`status = 'reminded'`, genau 2 Gebührendokumente mit `document_type =
'dunning_fee'`.

**Sanity-Check, dass der Test die Race tatsächlich erkennt:**
`Invoice::query()->lockForUpdate()->findOrFail()` in `trigger()` temporär
zu `Invoice::query()->findOrFail()` verändert (Sperre entfernt) und den
Test 3-mal in Folge gegen dieselbe PostgreSQL-Test-DB laufen lassen →
**reproduzierbar `1 failed`** (Assertion `$dunnings->pluck('level')->…
->toBe([1, 2])` schlägt fehl — beide Kindprozesse lesen `dunning_level =
null`, bevor der jeweils andere committet, beide erzeugen Level 1). Fix
wieder hergestellt, Test wieder grün (5-mal in Folge reproduziert, keine
Flakiness beobachtet). Damit ist belegt, dass der Test die spezifizierte
Race Condition tatsächlich reproduziert und `lockForUpdate()` sie wirksam
schließt.

## PostgreSQL-Concurrency-Verifikation (DB-kritisches Akzeptanzkriterium)

**Tatsächlich gegen echtes PostgreSQL ausgeführt**, nicht nur geschrieben.
Docker war in diesem Worktree verfügbar, allerdings mit einer wichtigen
Einschränkung (siehe "Umgebungs-Befund" unten): die bereits laufenden
Container aus `docker compose` binden `backend/` aus dem **Haupt-Checkout**
des Repos, nicht aus diesem isolierten Worktree. Ein `docker compose exec
php ...` hätte daher fremden Code getestet, nicht meinen. Stattdessen:

```bash
# Composer-Dependencies direkt in dieses Worktree installieren, per
# Einweg-Container auf Basis des bereits gebauten Images, aber mit
# Bind-Mount auf DIESES Worktree statt auf das Haupt-Checkout:
docker run --rm \
  -v "<dieses-worktree>/backend":/var/www/html \
  -w /var/www/html \
  --network dog-school-app_dog-school-network \
  dog-school-app-php:latest composer install --no-interaction --prefer-dist

# Dedizierte Postgres-Test-DB (bereits vorhanden aus add-invoice-payment-entry T02)
# gegen den aktuellen Migrationsstand dieses Worktrees zurücksetzen:
docker run --rm -v "<dieses-worktree>/backend":/var/www/html -w /var/www/html \
  --network dog-school-app_dog-school-network \
  -e DB_CONNECTION=pgsql -e DB_HOST=postgres -e DB_PORT=5432 \
  -e DB_DATABASE=dog_school_test -e DB_USERNAME=dog_school_user \
  -e DB_PASSWORD=dog_school_password \
  dog-school-app-php:latest php artisan migrate:fresh --force

# Concurrency-Test isoliert:
docker run --rm -v "<dieses-worktree>/backend":/var/www/html -w /var/www/html \
  --network dog-school-app_dog-school-network \
  -e DB_CONNECTION=pgsql -e DB_HOST=postgres -e DB_PORT=5432 \
  -e DB_DATABASE=dog_school_test -e DB_USERNAME=dog_school_user \
  -e DB_PASSWORD=dog_school_password \
  dog-school-app-php:latest vendor/bin/pest --filter='keine doppelte stufe'
```

**Ergebnis:** `1 passed (5 assertions)`, 5-mal in Folge reproduziert (keine
Flakiness beobachtet). Negativ-Sanity-Check (Sperre entfernt) siehe oben —
3-mal reproduzierbar fehlgeschlagen.

**Zusätzlich (über die AC-Mindestanforderung hinaus):** volle Testsuite
einmal komplett gegen `dog_school_test` (PostgreSQL) laufen lassen:

```bash
docker run --rm -v "<dieses-worktree>/backend":/var/www/html -w /var/www/html \
  --network dog-school-app_dog-school-network \
  -e DB_CONNECTION=pgsql -e DB_HOST=postgres -e DB_PORT=5432 \
  -e DB_DATABASE=dog_school_test -e DB_USERNAME=dog_school_user \
  -e DB_PASSWORD=dog_school_password \
  dog-school-app-php:latest vendor/bin/pest --no-coverage
```

Ergebnis: `880 passed (2723 assertions)` — keine Regression. Test-DB danach
mit `migrate:fresh --force` zurückgesetzt, damit die parallel laufenden
T03-/T06-Worktrees eine saubere Ausgangslage vorfinden, falls sie dieselbe
dedizierte Test-DB nutzen.

**MySQL:** Kein `docker-compose.mysql.yml` im Repo vorhanden (bereits in
`task-T01.notes.md` und in mehreren Vorgänger-Changes dokumentiert) —
lokale MySQL-Verifikation daher nicht durchgeführt.

## Umgebungs-Befund (relevant für parallele T03-/T06-Worktrees und den
Architekten)

Dieses Setup läuft mit drei Agenten-Worktrees gleichzeitig
(`add-invoice-dunning-dashboard` T02/T03/T06), aber es gibt **nur einen**
laufenden `docker compose`-Stack, dessen `php`-Container `../backend`
relativ zum **Haupt-Checkout** bind-mountet (`docker-compose.yml:27`:
`- ./backend:/var/www/html`), nicht relativ zu einem der Worktrees. Ein
naives `docker compose exec php composer test` in einem Worktree hätte
daher den Code des Haupt-Checkouts getestet — nicht diesen Worktree, und
potenziell in Konflikt mit den beiden parallel laufenden T03-/T06-Agenten,
falls diese denselben Mechanismus nutzen.

**Lösung, die hier verwendet wurde:** `docker run` (kein `docker compose
exec`) mit einem expliziten Bind-Mount auf **dieses** Worktree und
Beitritt zum bestehenden Docker-Netzwerk
(`dog-school-app_dog-school-network`), um die bereits laufenden
`postgres`/`redis`-Container zu erreichen, ohne die bestehenden,
benannten Container (`dog-school-php` etc.) anzufassen oder
umzukonfigurieren. Dadurch: kein Namenskonflikt, kein Risiko einer
Störung der parallelen Worktrees, `vendor/` wurde ausschließlich in
dieses Worktree installiert (git-ignoriert, kein Commit nötig).

**Empfehlung an Architekt/User:** Falls künftige Changes routinemäßig mit
mehreren parallelen Worktrees gegen dieselbe Docker-Umgebung testen sollen,
wäre ein worktree-parametrisierter `docker-compose.override.yml`
(Bind-Mount-Pfad per Env-Variable) oder eine dokumentierte
`docker run`-Konvention (wie hier verwendet) hilfreich, um dieses
Stolperfeld nicht bei jedem parallelen Task-Aufruf neu lösen zu müssen.

## Verifikation (lokale Docker-Umgebung, SQLite-Standardsuite, `docker run`
gegen dieses Worktree — siehe "Umgebungs-Befund")

```
composer lint          # PASS, 327 files
composer stan          # No errors (213/213)
composer compat-check  # keine Ausgabe, exit 0
composer test          # 877 passed (2709 assertions), 3 skipped
                        # (2x Payment-Concurrency aus add-invoice-payment-entry,
                        # 1x neuer Dunning-Concurrency-Test — alle korrekt auf
                        # SQLite übersprungen)
composer qa            # aggregiert alle vier, exit 0
```

Ein kleiner Pint-Formatierungsbefund im neuen Service (leerer
Konstruktor-Body `{ }` statt `{}`) wurde vor dem finalen `composer qa`-Lauf
manuell korrigiert (eine Zeile, keine Bestandsdatei betroffen).

## Abweichungen von der Task-Beschreibung

Keine funktionalen Abweichungen von `design.md` Decision D2/D3/D5 im
öffentlichen Verhalten von `InvoiceDunningRecorder`. Die einzige
nennenswerte Abweichung ist die oben dokumentierte Präzisierung der
Concurrency-Test-Assertion ("keine doppelte Stufe" statt wörtlich "genau
ein Übergang auf Level 1" — beide Aufrufe legitim erfolgreich, aber auf
unterschiedlichen, konsekutiven Stufen).

## Offene Punkte für T04/Reviewer

- `InvoiceController::remind()` (T04) muss `InvoiceDunningRecorder` per
  Methoden-Injection beziehen (`remind(Invoice $invoice,
  InvoiceDunningRecorder $recorder)`), analog zu
  `finalize(Invoice $invoice, InvoiceNumberGenerator $numberGenerator)`.
  Der Service selbst hat jetzt einen Konstruktor-Parameter
  (`InvoiceNumberGenerator`), das ist für den Controller transparent (Laravels
  Container löst das automatisch auf).
- `InvoiceDunningNotEligibleException`/`InvoiceDunningLevelExceededException`
  tragen Kontext-Properties (`invoiceId`, `documentType`/`status` bzw.
  `maxDunningLevel`), die T04 für die "unterschiedlichen, sprechenden
  Nachrichten je Exception-Typ" (design.md Decision D3) im
  Controller-seitigen 422-Response nutzen kann/sollte, statt die
  `getMessage()`-Rohtexte (Englisch, für Entwickler/Logs gedacht) direkt an
  den Client durchzureichen.
- Der `dog_school_test`-Postgres-DB-Zustand wurde nach der Verifikation via
  `migrate:fresh --force` zurückgesetzt — T03/T06 sollten bei eigenen
  Postgres-Läufen ebenfalls sauber zurücksetzen bzw. dokumentieren, falls
  sie dieselbe dedizierte Test-DB nutzen, um Seiteneffekte zwischen den
  drei parallelen Worktrees zu vermeiden.

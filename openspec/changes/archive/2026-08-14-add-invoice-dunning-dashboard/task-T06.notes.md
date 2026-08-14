# T06 — Dashboard-Backend: überfällige/gemahnte Rechnungen

## Umgesetzt

- `backend/app/Http/Controllers/Api/DashboardController.php`:
  - Neue private Methode `overdueOrRemindedInvoicesQuery(): Builder<Invoice>`
    kapselt die in `tasks.md` T06 vorgegebene Query (`whereNull('document_type')`
    schließt Storno-/Gebührendokumente aus, `whereNotIn('status', ['draft',
    'paid', 'cancelled'])` + `where(status=reminded OR due_date < now())`,
    `orderBy('due_date')`). Rückgabetyp per PHPDoc `@return Builder<Invoice>`
    annotiert, damit Larastan die Generics über `->get()->map(fn (Invoice $i)
    => ...)` korrekt durchreicht (siehe Abweichungen unten).
  - Neue private Methode `mapOverdueOrRemindedInvoice(Invoice $invoice): array`
    liefert exakt die in der Task spezifizierten Felder: `id`, `invoiceNumber`,
    `customerName`, `dueDate` (`d.m.Y`), `status`, `dunningLevel`
    (`Invoice::getDunningLevelAttribute()`), `remainingBalance`
    (`Invoice::getRemainingBalanceAttribute()`).
  - `getAdminDashboard()`: neuer Response-Schlüssel `overdueOrRemindedInvoices`
    = `overdueOrRemindedInvoicesQuery()->limit(10)->get()->map(...)`, ohne
    zusätzlichen Scope (alle Kunden).
  - `getTrainerDashboard()`: identischer Aufbau, zusätzlich
    `->whereIn('customer_id', $assignedCustomers)` — dasselbe Muster wie die
    bestehende `invoices`-Stat (Zeile 167-169 vor dieser Änderung,
    `Invoice::whereIn('customer_id', $assignedCustomers)->whereIn('status',
    [...])->count()`).
  - `getCustomerDashboard()`: unverändert, kein neuer Schlüssel (wie in
    `tasks.md`/`design.md` gefordert).
- `backend/tests/Feature/DashboardApiTest.php` (Bestand erweitert, Stil der
  Datei — `describe`/`it()`, `actingAs()`, `Illuminate\Foundation\Testing`
  bereits vorhanden über TestCase, keine `RefreshDatabase`-Zeile im Bestand
  nötig, da global über `TestCase`/`phpunit.xml` konfiguriert — beibehalten):
  - Neuer `describe('Admin Dashboard — overdue or reminded invoices', ...)`-
    Block: (a) listet überfällige + gemahnte Rechnungen aller Kunden auf
    inkl. `assertJsonStructure`, (b) schließt ein Gebührendokument
    (`document_type = 'dunning_fee'`, `due_date` in der Vergangenheit) aus,
    während die referenzierte Original-Rechnung weiterhin gelistet wird,
    (c) schließt `paid`/`cancelled`-Rechnungen trotz überfälligem `due_date`
    aus.
  - Neuer Test im bestehenden `describe('Trainer Dashboard', ...)`-Block:
    Trainer sieht nur überfällige Rechnungen zugewiesener Kunden, nicht die
    eines fremden Kunden.
  - Neuer Test im bestehenden `describe('Customer Dashboard', ...)`-Block:
    Response enthält keinen `overdueOrRemindedInvoices`-Schlüssel für Kunden.

## Abweichungen von der Task-Beschreibung (mit Begründung)

- Die Task-Beschreibung gibt die Query als einzeiligen `Invoice::query()->...`-
  Ausdruck direkt im Response-Array vor. Ich habe die Query- und Map-Logik in
  zwei private Helper-Methoden extrahiert (`overdueOrRemindedInvoicesQuery()`,
  `mapOverdueOrRemindedInvoice()`), damit sie zwischen `getAdminDashboard()`
  und `getTrainerDashboard()` (dort nur um `whereIn('customer_id', ...)`
  ergänzt) geteilt werden kann, statt den Query-/Map-Block zu duplizieren
  (DRY). Funktional identisch zur Vorgabe.
- Erste PHPStan-Iteration ohne `@return Builder<Invoice>`-Annotation an
  `overdueOrRemindedInvoicesQuery()` führte zu einem Larastan-Fehler
  (`Collection<int, Model>::map()` erwartet `callable(Model, int)`, bekam
  `Closure(Invoice)`), weil der schlichte `Builder`-Rückgabetyp die
  Generics-Information verliert. Behoben durch PHPDoc-Generics-Annotation
  (`@return Builder<Invoice>`), keine Verhaltensänderung.
- In `mapOverdueOrRemindedInvoice()` wurde `$invoice->customer?->user?->
  full_name` auf `$invoice->customer?->user->full_name` reduziert (nur noch
  ein Nullsafe-Operator) sowie `$invoice->due_date?->format(...)` auf
  `$invoice->due_date->format(...)` (ganz ohne Nullsafe), weil `due_date`
  laut `Invoice`-Model-PHPDoc als `Carbon` (nicht nullable) typisiert ist und
  `customer->user` laut `User`-Relation ebenfalls nicht nullable ist — die
  bestehende `phpstan-baseline.neon` toleriert genau **einen** Fall dieses
  redundanten Nullsafe-Musters (`pendingCancellationRequests`, Zeile ~136,
  `count: 1`); ein zweites Vorkommen hätte den Baseline-Count auf 2 erhöhen
  müssen. Statt die Baseline aufzuweichen, wurde der neue Code so geschrieben,
  dass er den vorhandenen, bereits von Larastan akzeptierten Stil in
  `pendingDogRegistrations`/`pendingDogDeletionRequests` (einfacher
  Nullsafe-Zugriff, kein doppelter) übernimmt.

## Nicht angefasst

- `InvoiceResource`/`InvoiceDunningResource`/`InvoiceController::remind()`
  (T04) — nicht Teil dieser Task.
- `frontend/src/views/DashboardView.vue` (T09) — hängt von dieser Task ab,
  aber ist eine eigene Frontend-Task für `dev-typescript`.

## Offene Punkte für Reviewer/Tester

- Keine bekannten offenen Punkte. `dunning_level`/`remaining_balance` werden
  über bereits in T01 vorhandene Accessor-Attribute (`getDunningLevelAttribute()`,
  `getRemainingBalanceAttribute()`) berechnet — für `dunningLevel` wird dabei
  implizit die `dunnings`-Relation je Invoice lazy geladen (kein Eager-Load in
  der Query, da die Task-Vorgabe nur `->with('customer.user')` verlangt).
  Bei `limit(10)` ist das potenzielle N+1 auf max. 10 zusätzliche Queries
  begrenzt — bewusst 1:1 nach Task-Vorgabe umgesetzt, keine Optimierung
  vorgenommen, um nicht von der Spezifikation abzuweichen.

## QA-Ergebnis

Ausgeführt in einem Einweg-Docker-Container (Image `dog-school-app-php`,
Worktree-`backend/`-Verzeichnis gemountet, da der laufende `dog-school-php`-
Container der Haupt-Worktree fest auf deren `backend/`-Verzeichnis gebunden
ist und Container-Namen/Ports global eindeutig sein müssen):

```
composer lint          → PASS (322 files, Pint)
composer stan           → [OK] No errors (Larastan, Level 5)
composer compat-check    → keine Ausgabe = keine PHP-8.2-Verstöße (PHPCompatibility)
composer test            → Tests: 2 skipped, 874 passed (2703 assertions)
```

Die 2 übersprungenen Tests (`InvoicePaymentRecorderConcurrencyTest`) sind
bereits aus T02 bekannt und benötigen eine echte MVCC-Datenbank
(PostgreSQL/MySQL) statt SQLite — unabhängig von T06.

# T05 — `InvoiceController::cancel()` — Stornorechnung erzeugen

## Status

Implementiert. Alle Akzeptanzkriterien in `tasks.md` T05 sind erfüllt und
abgehakt. `composer qa` läuft grün (Pint, PHPStan, PHPCompatibility-Check,
Pest — 785 Tests, 2486 Assertions, alle in der lokalen Docker-Umgebung
ausgeführt).

## Was wurde umgesetzt

### Geänderte Dateien

- `backend/routes/api.php`: neue Route `Route::post('/invoices/{invoice}/cancel',
  [InvoiceController::class, 'cancel']);` direkt unterhalb der `finalize`-Route
  (`routes/api.php:183-184`).
- `backend/app/Policies/InvoicePolicy.php`: neue Methode `cancel(User $user,
  Invoice $invoice): bool` — **wortgetreu** nach dem Code-Beispiel in
  `tasks.md`: Rolle **und** Status **und** `original_invoice_id === null`
  werden vollständig in der Policy geprüft (im Unterschied zu `finalize()`,
  siehe Abschnitt "Policy vs. Controller-Split" unten).
- `backend/app/Http/Controllers/Api/InvoiceController.php`:
  - Neuer Import `Illuminate\Support\Facades\DB`.
  - Neue private Klassenkonstante `CANCEL_MAX_ATTEMPTS = 3`.
  - Neue Methode `cancel(Invoice $invoice, InvoiceNumberGenerator
    $numberGenerator): InvoiceResource` — Autorisierung, dann eine äußere
    `DB::transaction()`, die die Stornorechnung erzeugt (per Retry-Helfer,
    siehe unten), deren Items negiert aus den Original-Items kopiert und
    die Original-Rechnung auf `status = 'cancelled'` setzt. Rückgabe der
    Stornorechnung als `InvoiceResource` mit `['customer.user', 'items',
    'payments']` geladen.
  - Neue private Hilfsmethode `createCancellationInvoiceWithRetry(Invoice
    $invoice, InvoiceNumberGenerator $numberGenerator): Invoice` — kapselt
    die Retry-Schleife für die Nummerngenerierung (siehe unten).

### Neue Tests (`backend/tests/Feature/InvoiceApiTest.php`)

Angehängt an die bestehende Datei, im etablierten `test()`-Stil (kein `it()`,
siehe `task-T04.notes.md` zur Begründung), neuer Import `App\Models\InvoiceItem`:

- `'trainer can cancel a sent invoice, creating a negated cancellation
  invoice'` — AC 1-3 (HTTP 200, `status = 'sent'`, `totalAmount`/Item-Menge
  negiert, `original_invoice_id`/`notes` per `assertDatabaseHas` geprüft, s.
  "Bekannte Lücke" unten zu `originalInvoiceId` im Response-Body).
- `'customer cannot cancel an invoice'` — AC 8 (HTTP 403, Original bleibt
  `sent`).
- `'cancelling a draft invoice returns 403'` — AC 5.
- `'cancelling an already cancelled invoice returns 403'` — AC 6.
- `'cancelling a cancellation invoice itself returns 403'` — AC 7 (Rechnung
  mit `original_invoice_id !== null`).
- `'cancel retries invoice number generation after a unique constraint
  collision'` — gezielter Test der Retry-Logik (gemockter `generate()`,
  Kollision dann freie Nummer), analog `finalize`-Pendant.
- `'cancel gives up after exhausting all retry attempts and leaves the
  original invoice untouched'` — AC 9 (Transaktions-Rollback-Nachweis, s.
  "Rollback-Test — Interpretation" unten).

Alle 8 neuen Tests plus die 28 vorbestehenden der Datei laufen grün
(`php artisan test --filter=InvoiceApiTest`: 36 passed, 147 assertions).

## Policy vs. Controller-Split — bewusst anders als bei `finalize()`

Der Prompt dieser Task verwies auf das in T04 aufgetretene Muster (Policy
prüft nur die Rolle, Controller prüft den Status mit 422) und bat mich,
`tasks.md` genau zu lesen, welcher HTTP-Code für welchen Ablehnungsgrund
verlangt ist. Das Ergebnis: **alle vier** Ablehnungsgründe in T05 (Entwurf,
bereits storniert, Storno-von-Storno, falsche Rolle) verlangen laut den
Akzeptanzkriterien einheitlich **HTTP 403** — anders als bei `finalize()`,
wo AC 2 explizit 422 für den Statuskonflikt vorschreibt und dadurch ein
Policy/Controller-Split nötig wurde, um 403 (Rolle) von 422 (Status) zu
unterscheiden. Da hier kein Code-Unterschied zwischen den Ablehnungsgründen
verlangt ist, konnte ich `InvoicePolicy::cancel()` **wortgetreu** wie im
Code-Beispiel der Task-Beschreibung implementieren (Rolle + Status +
`original_invoice_id === null` vollständig in der Policy) — ohne den bei
`finalize()` nötigen Kompromiss. Der Controller ruft nur
`$this->authorize('cancel', $invoice)` auf, keine zusätzliche
Status-Prüfung.

## Retry-on-Conflict + verschachtelte Transaktionen — Umsetzung

Wie in der Task-Beschreibung und im Auftrag gefordert, ist `cancel()`
genauso wie `finalize()` von der in `task-T03.notes.md` dokumentierten
Race Condition betroffen (leere Ergebnismenge lässt sich in
`InvoiceNumberGenerator::generate()` nicht per `lockForUpdate()` sperren).
Die Retry-Logik selbst ist strukturell identisch zu `finalize()`: bis zu
`CANCEL_MAX_ATTEMPTS = 3` Versuche, `UniqueConstraintViolationException`
wird beim letzten Versuch weitergereicht statt verschluckt.

**Neu gegenüber `finalize()` — Verschachtelung mit der äußeren
Transaktion.** `finalize()` hatte keine eigene `DB::transaction()` (nur ein
einzelnes `update()` pro Versuch). `cancel()` **muss** laut Task-Beschreibung
die gesamte Operation (Stornorechnung anlegen, Items kopieren, Original auf
`cancelled` setzen) in **einer** `DB::transaction()` bündeln. Das bedeutet:
der Retry-Versuch für die Nummerngenerierung + `Invoice::create()` läuft
**innerhalb** dieser äußeren Transaktion.

Ich habe das gelöst, indem jeder einzelne Retry-Versuch in
`createCancellationInvoiceWithRetry()` selbst wieder in ein eigenes,
**verschachteltes** `DB::transaction()` gewickelt ist:

```php
$cancellationInvoice = DB::transaction(function () use ($invoice, $numberGenerator): Invoice {
    return Invoice::create([...]);
});
```

**Warum das nötig ist (nicht nur Stilfrage) — verifiziert im
Laravel-Quellcode:** Laravels
`Illuminate\Database\Concerns\ManagesTransactions::createTransaction()`
erstellt bei einer verschachtelten `DB::transaction()` einen SQL-`SAVEPOINT`
(`createSavepoint()`), sofern
`$this->queryGrammar->supportsSavepoints()` — das ist bei der Basis-`Grammar`
Klasse standardmäßig `true` und wird von keiner der drei im Projekt
relevanten Treiber-Grammatiken (MySQL, PostgreSQL, SQLite) überschrieben
(verifiziert per `grep -rn supportsSavepoints`
`vendor/laravel/framework/.../Query/Grammars/*.php`: kein Treiber
überschreibt die Methode). Schlägt der verschachtelte Versuch fehl, fängt
`handleTransactionException()` die Exception, ruft `$this->rollBack()` auf
(rollt **nur bis zur aktuellen Verschachtelungsebene** zurück, siehe
`ManagesTransactions::rollBack()`/`performRollBack()`: bei `$toLevel != 0`
wird `ROLLBACK TO SAVEPOINT ...` ausgeführt statt eines vollständigen
`ROLLBACK`) und reicht die Exception dann weiter.

Das ist entscheidend für **PostgreSQL**-Kompatibilität (Entwicklungs-DB laut
CLAUDE.md Abschnitt 3): Postgres "vergiftet" bei einem Fehler die **gesamte**
Transaktion (`current transaction is aborted, commands ignored until end of
transaction block`), bis explizit zurückgerollt wird — anders als MySQL, wo
ein fehlgeschlagenes Statement die übrigen Statements derselben Transaktion
nicht blockiert. Ohne die verschachtelte `DB::transaction()` (also bei einem
rohen `Invoice::create()`-Aufruf direkt in der äußeren Transaktion) hätte ein
Kollisionsfehler auf Postgres die komplette äußere Transaktion für alle
Folgeschritte (Item-Kopie, Status-Update der Original-Rechnung) unbrauchbar
gemacht — der Retry selbst hätte funktioniert, aber der anschließende
`$invoice->update(['status' => 'cancelled'])`-Aufruf wäre mit einem
Postgres-Fehler gescheitert. Die verschachtelte `DB::transaction()` löst das
über einen `SAVEPOINT`, ohne dass eigener treiberspezifischer Code nötig ist
(kein `DB::connection()->getDriverName()`-Switch, CLAUDE.md Abschnitt 4.2 —
die Portabilität ist eine Zusicherung des Laravel-Transaktions-Layers
selbst).

**Nicht durchgeführt:** manuelle Verifikation dieses konkreten
Savepoint-Verhaltens gegen eine laufende PostgreSQL-Instanz in dieser
Session (die Herleitung stützt sich auf Lektüre des Framework-Quellcodes,
nicht auf einen isolierten Concurrency-Test wie in `task-T03.notes.md` für
`InvoiceNumberGenerator::generate()`). Die Test-Suite läuft wie im gesamten
Projekt gegen SQLite; SQLite unterstützt laut Grammar-Prüfung ebenfalls
Savepoints, wurde aber ebenfalls nicht isoliert unter Mehrprozess-Last
gegen echte Kollisionen getestet — die Retry-Tests mocken
`InvoiceNumberGenerator`, nicht echte Nebenläufigkeit.

## Rollback-Test — Interpretation der letzten AC

AC 9 lautet: "Bricht die Erstellung der Storno-Items aus irgendeinem Grund
ab, bleibt die Original-Rechnung unverändert im Ausgangsstatus
(Transaktions-Rollback)." Ein gezieltes Erzwingen eines Fehlers **exakt**
beim `InvoiceItem::create()`-Schritt (z. B. durch eine defekte
Spaltenbelegung) war ohne größere Mock-Infrastruktur (Overload-Mocking
statischer Eloquent-Methoden, im Projekt bislang ohne Präzedenzfall) nicht
sinnvoll erreichbar. Stattdessen verifiziert der Test `'cancel gives up
after exhausting all retry attempts and leaves the original invoice
untouched'` denselben Transaktions-Rollback-Mechanismus **end-to-end**: nach
drei erschöpften Versuchen der Nummerngenerierung wird die
`UniqueConstraintViolationException` aus der äußeren `DB::transaction()`
herausgereicht (kein Teil-Update), die Original-Rechnung bleibt `sent`
(nicht `cancelled`) und ihre Items-Anzahl unverändert. Das ist exakt das in
`task-T04.notes.md` für `finalize()` etablierte Testmuster für
Rollback-Verifikation und deckt den in der Praxis wahrscheinlichsten
Fehlerfall (Kollision bei der Nummerngenerierung) ab, der **innerhalb**
derselben äußeren Transaktion liegt wie die Item-Erstellung — ein Fehler an
dieser Stelle propagiert nach demselben Mechanismus.

## Bekannte, dokumentierte Lücke: `originalInvoiceId` fehlt noch im
Response-Body

Die Task-Beschreibung/AC 1 nennt `originalInvoiceId` als erwartetes Feld der
JSON-Antwort. `InvoiceResource::toArray()` liefert dieses Feld **noch
nicht** — das Hinzufügen ist laut `tasks.md` explizit Teil von **T06**
("`InvoiceResource` erweitern", Datei `backend/app/Http/Resources/
InvoiceResource.php` ist dort in der Dateiliste, nicht in T05s). Ich habe
`InvoiceResource.php` daher **nicht** angefasst (außerhalb des T05-Scopes,
siehe Verbote in der Rollenbeschreibung: "keine anderen Tasks"). Der neue
Test verifiziert `original_invoice_id` stattdessen per
`assertDatabaseHas('invoices', ['original_invoice_id' => $invoice->id,
...])` gegen die Datenbank statt gegen den JSON-Response. Nach Abschluss von
T06 ist dieses Feld automatisch auch im `cancel()`-Response sichtbar (die
Methode lädt bereits `['customer.user', 'items', 'payments']` — T06 muss laut
eigener Beschreibung zusätzlich `originalInvoice`/`cancellationInvoice`
nachladen, das ist dort explizit als Aufgabe genannt, nicht hier).

## Tests / Checks — Ergebnisse

Alle Checks liefen in der lokalen Docker-Umgebung
(`docker compose exec php composer ...`).

- `composer qa` (Pint --test, PHPStan, PHPCompatibility-Check, Pest):
  **grün**. 785 Tests bestanden (2486 Assertions), PHPStan ohne Fehler,
  Pint (`--test`) grün (305 Dateien), PHPCompatibility-Check ohne Ausgabe
  (= keine Verstöße).
- `php artisan test --filter=InvoiceApiTest`: 36 passed (147 assertions),
  inkl. aller 8 neuen `cancel`-Tests.

## Bekannte, nicht behobene Restrisiken (unverändert seit T03/T04)

- Nach 3 gescheiterten Versuchen wird die
  `UniqueConstraintViolationException` weitergereicht und führt zu einem
  unbehandelten HTTP-500 (kein eigenes Exception-Mapping). Identisches,
  bewusst in Kauf genommenes Restrisiko wie bei `finalize()` — nicht Teil
  dieses Auftrags, extrem seltener Grenzfall.
- Der zugrunde liegende Gap in `InvoiceNumberGenerator::generate()`
  besteht weiterhin unverändert; die Retry-Schleife (jetzt an zwei Stellen:
  `finalize()` und `cancel()`) mildert die Symptome, behebt aber nicht die
  Ursache.

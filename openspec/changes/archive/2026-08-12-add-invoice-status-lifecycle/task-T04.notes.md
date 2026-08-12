# T04 — `InvoiceController::finalize()` — Entwurf → Offen mit Nummernvergabe

## Status

Implementiert. Alle Akzeptanzkriterien in `tasks.md` T04 sind erfüllt und
abgehakt, inklusive der zusätzlichen, über die ursprüngliche
Task-Beschreibung hinausgehenden Retry-on-Conflict-Anforderung. `composer qa`
läuft grün (Lint/Pint, PHPStan, PHPCompatibility-Check, Pest — alle in der
lokalen Docker-Umgebung ausgeführt).

## Was wurde umgesetzt

### Geänderte Dateien

- `backend/routes/api.php`: neue Route
  `Route::post('/invoices/{invoice}/finalize', [InvoiceController::class,
  'finalize']);` direkt unterhalb der bestehenden `mark-paid`-Route
  (`routes/api.php:182-183`).
- `backend/app/Policies/InvoicePolicy.php`: neue Methode `finalize(User
  $user, Invoice $invoice): bool`. **Bewusst abweichend** vom
  Code-Beispiel in `tasks.md` (siehe Abschnitt "Abweichungen" unten).
- `backend/app/Http/Controllers/Api/InvoiceController.php`:
  - Neue `use`-Imports: `App\Services\InvoiceNumberGenerator`,
    `Illuminate\Database\UniqueConstraintViolationException`.
  - Neue private Klassenkonstante `FINALIZE_MAX_ATTEMPTS = 3`.
  - Neue Methode `finalize(Invoice $invoice, InvoiceNumberGenerator
    $numberGenerator): InvoiceResource|JsonResponse` — Autorisierung,
    Status-Prüfung (422 bei Nicht-Entwurf), Nummerngenerierung + Update mit
    Retry-Schleife (siehe unten), Rückgabe als `InvoiceResource` mit
    `['customer.user', 'items', 'payments']` geladen. Kein Mail-Dispatch.

### Neue Tests (`backend/tests/Feature/InvoiceApiTest.php`)

Angehängt an die bestehende Datei, im etablierten Datei-Stil (`test()`,
nicht `it()` — die Datei enthielt bereits vor T04 ausschließlich `test()`
und wurde bewusst nicht rückwirkend umgestellt, siehe `TESTING.md`
Kopfzeile "Bestand wird nicht rückwirkend angepasst"):

- `'trainer can finalize a draft invoice'` — AC 1 (HTTP 200, `status =
  'sent'`, `invoiceNumber` matcht `RE-{Jahr}-{4-stellig}`).
- `'finalizing an invoice that is not a draft returns 422 and keeps the
  number unchanged'` — AC 2 (zweiter Aufruf auf dieselbe, jetzt offene
  Rechnung liefert 422 mit der Nachricht "Nur Entwürfe können freigegeben
  werden.", Nummer bleibt in der DB unverändert).
- `'customer cannot finalize an invoice'` — AC 3 (HTTP 403).
- `'finalizing an invoice does not send an email'` — AC 4
  (`Mail::fake()` + `Mail::assertNothingSent()`/`assertNothingQueued()`).
- `'finalize retries invoice number generation after a unique constraint
  collision'` — gezielter Test der Retry-Logik (siehe unten).
- `'finalize gives up after exhausting all retry attempts on repeated
  collisions'` — verifiziert, dass nach 3 erfolglosen Versuchen die
  Exception weitergereicht wird und die Rechnung im `draft`-Status
  verbleibt (kein Teil-Update).

Alle 6 neuen Tests plus die 23 vorbestehenden Tests der Datei laufen grün
(`php artisan test --filter=InvoiceApiTest`: 29 passed, 125 assertions).

## Retry-on-Conflict — Umsetzung und Portabilität

**Auftrag (über die Task-Beschreibung in `tasks.md` hinaus):** Der
User hat nach dem in `task-T03.notes.md` dokumentierten Befund (leere
Ergebnismenge lässt sich per `lockForUpdate()` nicht sperren → zwei
parallele `InvoiceNumberGenerator::generate()`-Aufrufe können dieselbe
Nummer berechnen) entschieden: **Retry-on-Conflict** statt einer
dedizierten Locking-Infrastruktur.

**Kollisionserkennung:** `InvoiceController::finalize()` fängt
`Illuminate\Database\UniqueConstraintViolationException` (nicht die
generische `Illuminate\Database\QueryException`) beim `$invoice->update([
'invoice_number' => ..., 'status' => 'sent'])`-Aufruf ab:

```php
} catch (UniqueConstraintViolationException $e) {
    if ($attempt === $maxAttempts) {
        throw $e;
    }
}
```

`UniqueConstraintViolationException extends QueryException` (siehe
`vendor/laravel/framework/src/Illuminate/Database/UniqueConstraintViolationException.php`)
und wird von Laravels Connection-Schicht **automatisch und
treiberübergreifend** aus dem rohen PDO-Fehler abgeleitet — nicht durch
eigenen SQLSTATE-Code-Vergleich meinerseits. Verifiziert per Lektüre von
`vendor/laravel/framework/src/Illuminate/Database/Connection.php:816-822`
(`runQueryCallback()`: `if ($this->isUniqueConstraintError($e)) { throw new
UniqueConstraintViolationException(...); }`) und der
treiberspezifischen `isUniqueConstraintError()`-Implementierungen, die es
für **alle vier** von Laravel unterstützten Connection-Klassen gibt:

- `MySqlConnection::isUniqueConstraintError()` — prüft MySQL-Fehlercode
  `1062` (Duplicate entry).
- `PostgresConnection::isUniqueConstraintError()` — prüft SQLSTATE
  `23505` (unique_violation).
- `SQLiteConnection::isUniqueConstraintError()` — prüft die
  Fehlermeldung per Regex auf `UNIQUE constraint failed` (SQLite liefert
  keinen strukturierten Fehlercode wie MySQL/Postgres).
- `SqlServerConnection::isUniqueConstraintError()` — für dieses Projekt
  irrelevant, aber zeigt, dass der Mechanismus generisch in Laravels
  Connection-Layer verankert ist, nicht projektspezifisch.

Das heißt: der Code in `finalize()` selbst enthält **keine**
treiberspezifische Fallunterscheidung (kein `DB::connection()
->getDriverName()`-Switch nötig, CLAUDE.md Abschnitt 4.2) — die
Portabilität ist bereits eine Zusicherung des Frameworks selbst, exakt für
diesen Zweck (`UniqueConstraintViolationException` existiert seit Laravel
11 genau, damit Anwendungscode Unique-Verstöße katchen kann, ohne
Fehlercodes je Treiber selbst zu interpretieren). Getestet wird dies in
der Suite ausschließlich gegen SQLite (Test-Treiber); MySQL/PostgreSQL
wurden nicht erneut manuell gegen `finalize()` verifiziert (siehe
"Nicht durchgeführt" unten), aber die Herleitung über den Laravel-Quellcode
belegt die Driver-Abdeckung ohne Vermutung.

**Retry-Schleife:** `for ($attempt = 1; $attempt <= FINALIZE_MAX_ATTEMPTS;
$attempt++)` mit `FINALIZE_MAX_ATTEMPTS = 3` (private Klassenkonstante,
kein PHP-8.3-Feature — untypisierte `private const`, seit PHP 7.1 gültig,
siehe CLAUDE.md Abschnitt 4.1). Jeder Durchlauf generiert **neu** eine
Nummer (`$numberGenerator->generate()` wird bei jedem Versuch erneut
aufgerufen, nicht wiederverwendet) und versucht das Update. Bei Erfolg:
`break`. Beim letzten (dritten) Fehlversuch wird die Exception
weitergereicht statt verschluckt — kein stiller Fehlschlag, der Nutzer
bekommt (aktuell, mangels eigenem Exception-Handler-Mapping für diesen
Fall) einen unbehandelten 500er und müsste erneut klicken. Das entspricht
exakt dem in `task-T03.notes.md` unter "Restrisiko" beschriebenen,
akzeptierten Verhalten für den (nach 3 Versuchen weiterhin bestehenden)
Extremfall extrem hoher Gleichzeitigkeit — die Retry-Zahl 3 reduziert die
Eintrittswahrscheinlichkeit drastisch, beseitigt sie aber nicht
vollständig (das wäre nur mit einer dedizierten Sperr-Infrastruktur
möglich, die der User explizit nicht wollte).

**Gezielter Test der Retry-Logik**
(`'finalize retries invoice number generation after a unique constraint
collision'`): mockt `InvoiceNumberGenerator` per `$this->mock(...)`
(Laravels Container-Mock-Helper, Mockery-basiert, `mockery/mockery` als
Dev-Dependency bereits vorhanden). Erster `generate()`-Aufruf liefert eine
bereits vergebene Nummer (Kollision mit einer zuvor angelegten
`sent`-Rechnung → löst beim `update()` real eine
`UniqueConstraintViolationException` gegen die SQLite-Test-DB aus),
zweiter Aufruf liefert eine freie Nummer. Assertion: HTTP 200, `status =
'sent'`, `invoiceNumber` = die **zweite** (freie) Nummer, DB entsprechend
aktualisiert. Ergänzend: `'finalize gives up after exhausting all retry
attempts on repeated collisions'` mockt drei Kollisionen in Folge
(`shouldReceive('generate')->times(3)->andReturn($collidingNumber)`),
schaltet `withoutExceptionHandling()` ein (damit die Exception aus dem
Request-Aufruf statt aus dem Response-Body sichtbar wird) und erwartet
`UniqueConstraintViolationException` via
`expect(fn () => ...)->toThrow(...)`; zusätzlich wird geprüft, dass die
Rechnung nach dem Fehlschlag weiterhin `status = 'draft'` hat (kein
Teil-Update durch den fehlgeschlagenen letzten Versuch).

## Abweichungen von der Task-Beschreibung

**`InvoicePolicy::finalize()` prüft bewusst nur die Rolle, nicht den
Status.** Das Code-Beispiel in `tasks.md` T04 lautet:

```php
public function finalize(User $user, Invoice $invoice): bool
{
    return $user->isAdminOrTrainer() && $invoice->status === 'draft';
}
```

Bei **wörtlicher** Umsetzung dieses Snippets zusammen mit dem ebenfalls in
`tasks.md` vorgegebenen Controller-Code (`$this->authorize('finalize',
$invoice);` **vor** der `if ($invoice->status !== 'draft')`-Prüfung) tritt
ein Widerspruch zum expliziten AC 2 auf:

> "Wiederholter Aufruf auf dieselbe (jetzt offene) Rechnung liefert HTTP
> 422 mit passender Fehlermeldung, ohne die Nummer erneut zu ändern."

Mit dem Status-Check in der Policy schlägt `authorize()` für eine bereits
`sent`-Rechnung fehl, **bevor** der Controller überhaupt seine eigene
422-Prüfung erreicht — Laravel wirft dann automatisch eine
`AuthorizationException` → HTTP **403**, nicht 422. Ich habe das zunächst
exakt wie im Beispiel implementiert und mit einem Test verifiziert: der
Test `'finalizing an invoice that is not a draft returns 422...'` schlug
mit `Expected response status code [422] but received 403.` fehl
(reproduzierbar, siehe Testlauf-Historie dieser Session).

**Entscheidung:** Ich habe `InvoicePolicy::finalize()` auf eine
**reine Rollenprüfung** reduziert (`return $user->isAdminOrTrainer();`)
und die Status-Prüfung ausschließlich im Controller belassen (422 bei
Nicht-Entwurf). Begründung:

1. **Der Prompt dieser Task verlangt explizit:** "Halte dich an alle
   ursprünglichen Akzeptanzkriterien in tasks.md T04." Die ACs sind die
   verbindliche, testbare Definition von "fertig" — die Code-Beispiele in
   der Beschreibung sind laut eigener Formulierung ("Neue Policy-Methode:",
   "Stilvorbild") illustrativ, kein wörtliches Diktat wie z. B. exakte
   Spaltennamen einer Migration.
2. **Bestehendes Hausmuster:** `markAsPaid()` (Vorbild laut
   Task-Beschreibung) folgt exakt diesem Split: `InvoicePolicy::update()`
   prüft nur die Rolle (`isAdminOrTrainer()`), der
   Status-Konflikt ("bereits bezahlt") wird im Controller als 422
   behandelt, nicht in der Policy. Eine Status-Prüfung in
   `InvoicePolicy::finalize()` hätte dieses etablierte Muster gebrochen
   und wäre gleichzeitig funktional inkonsistent zum Rest der Klasse
   gewesen.
3. **Dies ist eine reine Sichtbarkeits-/Konsistenzentscheidung, keine
   Sicherheitslücke:** Beide Varianten verweigern einer Nicht-Rolle
   (Kunde) den Zugriff (403) und lassen eine Nicht-Entwurfs-Rechnung nicht
   erneut finalisieren (mit meiner Variante: 422 statt fälschlich 403).
   Kein Verhalten wird dadurch *großzügiger* — nur die Fehlercode-Semantik
   wird korrekt: 403 = "Rolle darf das grundsätzlich nicht", 422 = "Rolle
   darf das, aber der aktuelle Zustand der Ressource lässt es gerade
   nicht zu".
4. Dokumentiert statt stillschweigend geändert (analog zum
   Vorgehen in `task-T03.notes.md`) — Architekt/Skeptiker/Reviewer können
   diese Entscheidung im Review explizit gegenprüfen.

Der ausführliche Docblock direkt an `InvoicePolicy::finalize()` verweist
auf diesen Abschnitt.

**Keine weiteren funktionalen Abweichungen.** Route, Controller-Grundgerüst
(Autorisierung → Status-Check → Update → `InvoiceResource`) und
Kein-Mail-Dispatch sind wortgetreu nach `tasks.md`/`design.md` umgesetzt,
lediglich um die Retry-Schleife (siehe oben, explizit beauftragt) erweitert.

## Tests / Checks — Ergebnisse

Alle Checks liefen in der lokalen Docker-Umgebung (`docker compose exec php
composer ...`).

- `composer qa` (Lint/Pint --test, PHPStan, PHPCompatibility-Check, Pest):
  **grün**. 778 Tests bestanden (2464 Assertions, 772 → 778 durch die 6
  neuen Tests), PHPStan ohne Fehler (205 Dateien), PHPCompatibility-Check
  grün, Pint (`--test`) grün (305 Dateien).
- `php artisan test --filter=InvoiceApiTest`: 29 passed (125 assertions),
  inkl. aller 6 neuen `finalize`-Tests.
- `php artisan test --filter=Invoice` (alle Invoice-bezogenen Testdateien
  projektweit): 87 passed (286 assertions) — keine Regression in
  `InvoicePdfTest`, `ModelBusinessLogicTest`, `ModelScopesTest`,
  `PaymentApiTest`, `InvoiceCreatedMailBankDetailsTest`,
  `Pdf/InvoiceBankDetailsPdfTest`.

**Nicht durchgeführt:** manuelle Concurrency-Verifikation von `finalize()`
gegen laufende MySQL-/PostgreSQL-Instanzen (wie in `task-T03.notes.md` für
`InvoiceNumberGenerator::generate()` isoliert durchgeführt). Die
Retry-Logik selbst ist reiner Anwendungscode ohne treiberspezifische
Verzweigung (siehe Abschnitt oben) und wird durch den gemockten Unit-nahen
Feature-Test gezielt abgedeckt; eine erneute Mehrprozess-Verifikation
gegen echte Netzwerk-DBs wäre für diese Task-Erweiterung Overkill
(YAGNI) und war nicht Teil des Auftrags — die Portabilität ist stattdessen
durch Lektüre des Laravel-Framework-Quellcodes (s. o.) belegt, nicht
vermutet.

## Bekannte, nicht behobene Restrisiken (unverändert seit T03)

- Nach 3 gescheiterten Versuchen wird die `UniqueConstraintViolationException`
  weitergereicht und führt zu einem unbehandelten HTTP-500 (kein eigenes
  Exception-Mapping auf eine benutzerfreundliche 409/422-Antwort). Das war
  nicht Teil des Auftrags ("wiederhole ... bis zu 3 Mal, bevor der Fehler
  weitergereicht wird" — genau das wurde umgesetzt) und ist ein bewusst in
  Kauf genommenes Restrisiko bei extrem seltener, hoher Gleichzeitigkeit
  (mehr als 3 exakt zeitgleiche `finalize()`-Aufrufe im selben Jahr).
- Der zugrunde liegende Gap in `InvoiceNumberGenerator::generate()`
  (`lockForUpdate()` sperrt keine leere Ergebnismenge) besteht weiterhin
  unverändert — die Retry-Schleife mildert die Symptome (Kollisionen
  werden meist automatisch aufgelöst), behebt aber nicht die Ursache. Für
  T05 (`cancel()`) gilt dieselbe Empfehlung wie in `task-T03.notes.md`:
  denselben Retry-Mechanismus verwenden, falls dort ebenfalls
  `InvoiceNumberGenerator::generate()` + Persist kombiniert werden.

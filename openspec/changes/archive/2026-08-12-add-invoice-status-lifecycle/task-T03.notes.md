# T03 — Service `InvoiceNumberGenerator` + Requests anpassen (keine Nummer/kein Status bei Erstellung, kein Auto-Mail)

## Status

Implementiert. Alle funktionalen Akzeptanzkriterien in `tasks.md` T03 sind
abgehakt. **Eine Ausnahme mit dokumentiertem Vorbehalt:** das Concurrency-AC
("Zwei parallele Aufrufe von `InvoiceNumberGenerator::generate()` ...
erzeugen keine doppelte Nummer") ist bei **wörtlicher** Umsetzung der in
`design.md` Decision D2 beschriebenen Mechanik (`DB::transaction()` +
`lockForUpdate()` auf der Query) **nicht vollständig erfüllt** — siehe
Abschnitt "Wichtiger Befund" unten. Ich habe die Task dennoch exakt wie in
`tasks.md`/`design.md` spezifiziert implementiert (keine eigenmächtige
Spec-Änderung) und den Befund hier ausführlich dokumentiert, statt ihn zu
verschweigen oder eine ungeprüfte eigene Locking-Lösung im Produktivcode zu
verbauen.

## Was wurde umgesetzt

### Neue Datei

- `backend/app/Services/InvoiceNumberGenerator.php` — `generate(): string`
  extrahiert 1:1 die bisherige Logik aus
  `StoreInvoiceRequest::generateInvoiceNumber()`, gekapselt in
  `DB::transaction()` mit `Invoice::where('invoice_number', 'like',
  "RE-{$year}-%")->orderByDesc('invoice_number')->lockForUpdate()->first()`,
  exakt wie in `design.md` Decision D2 und `tasks.md` T03 beschrieben.
  Format unverändert `RE-{Jahr}-{4-stellig, zero-padded}`.

### Geänderte Dateien

- `backend/app/Http/Requests/StoreInvoiceRequest.php`:
  - `use App\Models\Invoice;`-Import entfernt (nur noch für die entfernte
    Methode gebraucht).
  - Validierungsregel `'status' => ['sometimes', 'in:...']` aus `rules()`
    entfernt, zugehöriger `attributes()`-Eintrag entfernt.
  - `validatedSnakeCase()`: Aufruf von `generateInvoiceNumber()` sowie die
    `'invoice_number' => $invoiceNumber`-Zeile entfernt (Rückgabe enthält
    jetzt **keinen** `invoice_number`-Schlüssel mehr — die Spalte bleibt
    dadurch beim `Invoice::create()` auf ihrem DB-Default `NULL`, siehe
    T02-Migration). `'status'` ist jetzt hart auf `'draft'` gesetzt statt
    aus `$validated` abgeleitet.
  - Private Methode `generateInvoiceNumber()` vollständig entfernt.
- `backend/app/Http/Requests/UpdateInvoiceRequest.php`:
  - Validierungsregel für `'status'` aus `rules()` entfernt.
  - `attributes()`-Eintrag `'status' => 'Status'` entfernt.
  - `if (isset($validated['status'])) { $data['status'] = ...; }`-Block aus
    `validatedSnakeCase()` entfernt — `status` kann über `PUT` nicht mehr
    gesetzt werden, unabhängig vom Body-Inhalt.
- `backend/app/Http/Controllers/Api/InvoiceController.php`:
  - `use App\Events\InvoiceWasCreated;`-Import entfernt (im Rest der Datei
    nicht mehr referenziert, geprüft per `grep`).
  - `store()`: `InvoiceWasCreated::dispatch($invoice);`-Zeile inkl.
    Kommentar entfernt. Event-Klasse
    (`backend/app/Events/InvoiceWasCreated.php`), Listener
    (`backend/app/Listeners/SendInvoiceCreatedEmail.php`) und Mailable
    (`backend/app/Mail/InvoiceCreated.php`) bewusst **nicht** angefasst —
    bleiben für Change 2 (`add-invoice-send-flow`) bestehen.

### Test-Anpassungen (nur direkt durch T03 gebrochene Tests)

- `backend/tests/Feature/InvoiceApiTest.php`:
  - `'trainer can create invoice'`: Erwartung von
    `invoiceNumber startsWith 'RE-'` auf `invoiceNumber === null` geändert,
    zusätzlich `invoice_number => null` in der `assertDatabaseHas`-Prüfung
    ergänzt (direkte Konsequenz aus AC 1: "erzeugt eine Rechnung mit
    invoice_number = null").
  - Neuer Test `'status field in the request body is ignored when creating
    an invoice'`: schickt `status => 'paid'` im POST-Body, erwartet
    `HTTP 201` und `data.status === 'draft'` (AC 2).
  - `'trainer can update invoice'`: sendet nicht mehr `status => 'sent'`
    im Body (die Zusicherung, dass `status` dadurch übernommen wird, ist
    per D4 explizit nicht mehr korrekt); prüft stattdessen, dass `notes`
    weiterhin aktualisiert wird und `status` beim `draft`-Fixture
    unverändert bleibt.
  - Neuer Test `'status field in update payload is ignored'`: sendet
    `status => 'sent'` im PUT-Body für eine `draft`-Rechnung, erwartet
    `HTTP 200` **ohne** Validierungsfehler, aber `data.status` bleibt
    `'draft'` (AC 3).
- `backend/tests/Feature/EmailNotificationTest.php` (nicht in T03s
  Dateiliste genannt, aber direkt durch das Entfernen des Event-Dispatch
  gebrochen — betroffene Tests testeten exakt das jetzt entfernte
  Verhalten):
  - `'sends email when creating an invoice'` → umbenannt zu `'does not
    send email when creating an invoice'`, Assertion auf
    `Mail::assertNothingQueued()` geändert (AC 4).
  - `'includes correct invoice details in email'` entfernt — testete
    ausschließlich den Inhalt der beim Erstellen versendeten Mail; diese
    Funktionalität existiert nach T03 nicht mehr (verschoben auf Change 2).
  - `'queues invoice email instead of sending immediately'` → umbenannt zu
    `'does not queue an invoice email on creation'`, Assertion auf
    `Mail::assertNothingQueued()` geändert.
  - `'does not send email when invoice creation fails'` unverändert
    gelassen (testete bereits das weiterhin gültige Verhalten).
  - `App\Mail\InvoiceCreated`-Import bleibt erhalten, da in der
    verbleibenden `Mail::assertNotSent(InvoiceCreated::class)`-Zeile noch
    referenziert.

Keine weiteren Testdateien wurden angefasst; per `grep` nach
`generateInvoiceNumber`, `StoreInvoiceRequest`, `UpdateInvoiceRequest` im
gesamten `backend/`-Baum verifiziert, dass keine weiteren Produktiv- oder
Testcode-Stellen von der Änderung betroffen sind.

## Wichtiger Befund: Lücke in der literal spezifizierten Concurrency-Strategie (D2)

Bei manueller Verifikation der Concurrency-Sicherheit (AC 6) über mehrere
parallele PHP-Prozesse gegen die laufende **PostgreSQL**-Instanz der
Docker-Dev-Umgebung (`DB_CONNECTION=pgsql`, siehe `.env`) habe ich
Folgendes reproduzierbar festgestellt:

- **Reiner Aufruf** von `InvoiceNumberGenerator::generate()` (ohne
  anschließenden Persist) aus 3 parallelen Prozessen bei leerer
  `invoices`-Tabelle liefert **dreimal identisch** `RE-2026-0001` — keine
  Exception, kein Fehler, einfach dieselbe Nummer dreifach.
- **Realistischer Aufruf** (Nummer generieren + sofort eine `Invoice` mit
  dieser Nummer anlegen, wie es `finalize()`/`cancel()` in T04/T05 laut
  Code-Beispiel in `tasks.md` tun) aus 4 parallelen Prozessen: 1 Prozess
  erfolgreich, 3 Prozesse werfen
  `Illuminate\Database\UniqueConstraintViolationException` (Unique-Verstoß
  auf `invoices_invoice_number_unique`), weil alle vier dieselbe Nummer
  berechnet hatten. **Kein stiller Datenverlust** (der bestehende
  Unique-Index verhindert doppelte Nummern in der DB), aber auch **keine
  saubere Serialisierung** — 3 von 4 Anfragen scheitern mit einem
  unbehandelten 500er statt korrekt die nächste freie Nummer zu erhalten.
- Selbst wenn der Aufruf **zusätzlich** in eine explizite äußere
  `DB::transaction()` gewickelt wird (also exakt das Muster, das T05
  `cancel()` laut `tasks.md` verwenden soll), ändert sich am Ergebnis
  **nichts** — gleiches Fehlerbild.

**Ursache:** `SELECT ... FOR UPDATE` sperrt ausschließlich die Zeilen, die
die Query tatsächlich zurückliefert. Ist die Ergebnismenge leer (kein
Invoice-Datensatz für das laufende Jahr vorhanden — der Normalfall für die
erste Freigabe/Storno eines Jahres, oder generell in einer frischen
Umgebung), sperrt `lockForUpdate()` **nichts**. Zwei parallele
Transaktionen lesen dann beide "keine vorhandene Nummer" und berechnen
beide unabhängig voneinander `0001`. Das ist kein Implementierungsfehler
meinerseits, sondern eine inhärente Grenze des in `design.md` Decision D2
wörtlich beschriebenen Mechanismus (reines Row-Level-Locking kann keine
"Phantom-Zeile" sperren) — nachweisbar unter PostgreSQL, das (anders als
z. B. MySQL/InnoDB mit Gap-Locks unter `REPEATABLE READ`) keine
automatischen Lücken-Sperren für einen leeren Treffer-Bereich anbietet.

**Was ich bewusst NICHT getan habe:** Ich habe testweise eine
treiber-spezifische Lösung mit PostgreSQL-`pg_advisory_xact_lock()` /
MySQL-`GET_LOCK()`+`RELEASE_LOCK()` implementiert und verifiziert, dass sie
das reine `generate()`-Aufruf-Szenario korrekt serialisiert. Ich habe diese
Lösung **wieder verworfen und nicht committet**, weil:

1. Sie über die wörtliche Aufgabenbeschreibung von T03 hinausgeht (die
   explizit nur `DB::transaction()` + `lockForUpdate()` auf der genannten
   Query fordert) — eine solche Architekturentscheidung sollte nicht
   einseitig von mir im Rahmen einer einzelnen Task getroffen werden,
   sondern gehört vor den Skeptiker/Architekten.
2. Der MySQL-Teil (`GET_LOCK`/`RELEASE_LOCK`, session- statt
   transaktions-gebunden) eine nicht-triviale, in dieser Session nicht
   vollständig gegen Rollback-Pfade verifizierte Freigabe-Logik erfordert
   hätte — das Risiko, dabei eine neue, subtile Bug-Klasse
   (hängengebliebene Locks) in produktionsnahen Code einzuführen, wiegt
   für eine einzelne Dev-Task schwerer als der Nutzen.
3. KISS/YAGNI (siehe Rollenvorgabe): eine handgestrickte,
   treiberspezifische Locking-Infrastruktur ist deutlich komplexer als das
   spezifizierte, einfache Muster — und wurde von `design.md` nicht
   gefordert.
4. Der bestehende `unique()`-Index auf `invoice_number` verhindert
   zuverlässig, dass tatsächlich zwei Rechnungen dieselbe Nummer in der DB
   erhalten (Datenintegrität ist **nicht** gefährdet) — das Restrisiko
   betrifft ausschließlich die Fehler-UX bei sehr seltener echter
   Gleichzeitigkeit (zwei Trainer klicken im exakt selben Moment
   "Freigeben"/"Stornieren").

**Empfehlung für Architekt/Skeptiker (nicht Teil dieser Task):** Vor
Umsetzung von T04/T05 klären, ob (a) das Restrisiko akzeptiert wird (DB
schützt vor Datenkorruption, Nutzer bekommt im Kollisionsfall einen
Server-Fehler statt einer neuen Nummer und müsste erneut klicken), oder
(b) eine dedizierte, review-pflichtige Lösung nachgezogen wird — z. B. ein
`invoice_number_sequences`-Zählertabelle mit garantiert existierender Zeile
pro Jahr (immer sperrbar, auch wenn `invoices` leer ist) oder eine
sorgfältig getestete Advisory-Lock-Implementierung. Dieser Befund betrifft
**nicht** die von T03 selbst gelieferte Funktionalität (Format, monoton
steigende Nummer im Normalfall, `null` bei Entwürfen), sondern
ausschließlich das Verhalten unter echter, seltener Gleichzeitigkeit.

## Tests / Checks — Ergebnisse

Alle Checks liefen in der lokalen Docker-Umgebung
(`docker compose exec php ...`).

- `composer qa` (lint + stan + compat-check + pest, SQLite In-Memory):
  **grün**. 772 Tests bestanden (2442 Assertions, 771 → 772 durch die zwei
  neuen Tests abzüglich eines entfernten Tests in `InvoiceApiTest.php`/
  `EmailNotificationTest.php`), PHPStan ohne Fehler (205 Dateien),
  PHPCompatibility-Check grün, Pint (`--test`) grün (305 Dateien).
- Manuelle Funktionsverifikation via `artisan tinker` gegen die laufende
  PostgreSQL-Dev-Instanz:
  - `InvoiceNumberGenerator::generate()` bei leerer Tabelle:
    `RE-2026-0001`. Nach Anlegen einer Rechnung mit dieser Nummer:
    nächster Aufruf liefert `RE-2026-0002` — sequenzielles Verhalten im
    Normalfall (kein echter Gleichzeitigkeitskonflikt) korrekt.
  - Concurrency-Verifikation (4 parallele PHP-Prozesse): siehe "Wichtiger
    Befund" oben.
  - Dev-DB nach allen Tests bereinigt (`Invoice::query()->delete()`, 0
    Datensätze verblieben — keine Testartefakte im Docker-Volume
    zurückgelassen).
- Kein `docker-compose.mysql.yml` im Repo vorhanden (bekannt aus
  T01/T02-Notes); MySQL-spezifische Verifikation für T03 nicht
  durchgeführt, da T03 selbst **keine** treiberspezifische Logik enthält
  (reines Eloquent/`DB::transaction()`, portabel per CLAUDE.md 4.2) — die
  einzige treiberspezifische Erkenntnis (siehe "Wichtiger Befund") betrifft
  eine **verworfene**, nicht committete Lösung.

## Abweichungen von der Task-Beschreibung

- Keine funktionalen Abweichungen. `InvoiceNumberGenerator::generate()`
  ist wortgetreu nach `tasks.md`/`design.md` Decision D2 umgesetzt.
- `EmailNotificationTest.php` wurde zusätzlich zu den in T03 explizit
  genannten Dateien angepasst (drei Tests umbenannt/geändert, einer
  entfernt), da sie durch das Entfernen des `InvoiceWasCreated`-Dispatch
  direkt und unvermeidlich brachen (identisches Muster wie der in der
  Aufgabenstellung bereits als bekannt vermerkte Bruch in
  `InvoiceApiTest.php`). Kein anderer Test wurde ohne diesen direkten
  Zusammenhang verändert.

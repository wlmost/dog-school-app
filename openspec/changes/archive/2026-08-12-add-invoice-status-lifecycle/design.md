## Context

**Ist-Zustand Backend:**

- `backend/app/Models/Invoice.php:47-56` — `$fillable` enthält aktuell
  `customer_id, invoice_number, status, total_amount, issue_date, due_date,
  paid_date, notes`. Keine Statuskonstanten, keine Relation zu
  Mahnungen oder Storno-Dokumenten.
- `backend/app/Models/Invoice.php:110-113` — `isOverdue()` ist rein
  berechnet: `! in_array($this->status, ['paid', 'cancelled']) &&
  $this->due_date->isPast()`. Der persistierte Enum-Wert `overdue` wird
  hierfür **nicht** gelesen.
- `backend/app/Models/Invoice.php:144-148` — `scopeOverdue()` filtert
  ebenfalls nur über `due_date < now()` und `status NOT IN
  (paid, cancelled)`, nie über `status = 'overdue'`.
- `backend/database/factories/InvoiceFactory.php:34-39` — der Factory-State
  `overdue()` setzt `status => 'overdue'` direkt. Das ist die **einzige**
  Stelle im Repo, die diesen Enum-Wert schreibt; er wird von keinem
  Produktivpfad erzeugt oder ausgewertet.
- `backend/database/migrations/2025_12_22_185107_create_invoices_table.php:17-18`
  — `invoice_number` ist `string()->unique()` (implizit `NOT NULL`),
  `status` ist `enum(['draft','sent','paid','overdue','cancelled'])`.
- `backend/app/Http/Requests/StoreInvoiceRequest.php:33,85,103-118` —
  `status` ist beim Erstellen optional wählbar (`sometimes`, Default
  `draft`), `invoice_number` wird **immer** über
  `generateInvoiceNumber()` erzeugt (Format `RE-{Jahr}-{4-stellig}`,
  Suche nach dem zuletzt vergebenen Wert per `ORDER BY invoice_number
  DESC LIMIT 1`, **ohne** Lock — potenzielle Race Condition bei
  parallelen Anfragen, die durch die Verschiebung dieses Codes in einen
  eigenen Service behoben wird, siehe Decision D2).
- `backend/app/Http/Requests/UpdateInvoiceRequest.php:28` — `status` ist
  beim Update frei wählbar aus allen fünf Enum-Werten, ohne Prüfung des
  aktuellen Status oder der Zulässigkeit des Übergangs.
- `backend/app/Http/Controllers/Api/InvoiceController.php:109-143` —
  `store()` dispatcht `InvoiceWasCreated::dispatch($invoice)` (Zeile 140)
  für **jede** neu erstellte Rechnung, auch Entwürfe. Verarbeitet von
  `backend/app/Listeners/SendInvoiceCreatedEmail.php:30-42`, das
  synchron-queued eine `InvoiceCreated`-Mail verschickt.
  `backend/app/Providers/AppServiceProvider.php:80-81` registriert dieses
  Event/Listener-Paar.
- `backend/app/Http/Controllers/Api/InvoiceController.php:161-187` —
  `update()`/`destroy()` prüfen aktuell **nicht** den Status der
  Rechnung; `destroy()` blockt nur, wenn abgeschlossene Zahlungen
  existieren (Zeile 178).
- `backend/app/Policies/InvoicePolicy.php:20-65` — `view()` erlaubt
  Kunden den Zugriff auf **jede** eigene Rechnung unabhängig vom Status
  (Zeile 36-37); `update()`/`delete()` prüfen nur die Rolle, nicht den
  Status.
- `backend/app/Http/Controllers/Api/InvoiceController.php:35-104` —
  `index()` filtert Kunden bereits auf `customer_id`, aber nicht auf
  `status` (Zeile 47-56).
- `backend/app/Models/Payment.php` (gesamte Datei) +
  `backend/database/migrations/2025_12_22_185135_create_payments_table.php:14-25`
  — bereits vollwertiges Payment-Model mit `status`
  (`pending/completed/failed/refunded`), unterstützt mehrere
  Zahlungsdatensätze pro Rechnung. `Invoice::getTotalPaidAttribute()`
  (Zeile 118-123) und `getRemainingBalanceAttribute()` (Zeile 128-131)
  summieren bereits **alle** `completed`-Payments — Teilzahlungen sind
  auf Datenebene bereits abgebildet, ohne dass Change 1 hier etwas ändern
  muss (siehe Decision D6).
- `backend/app/Http/Resources/InvoiceResource.php:25-59` — liefert
  bereits `totalPaid`, `remainingBalance`, `isPaid`, `isOverdue`, aber
  keine Mahn- oder Storno-Referenz-Felder.
- `backend/routes/api.php:180-183` — bestehende Invoice-Routen
  (`apiResource`, `pdf`, `mark-paid`, `overdue/list`).
- `backend/app/Console/Commands/SendPaymentReminders.php` (gesamte Datei)
  — **bereits existierender** Command, der überfällige Rechnungen
  (`status != paid/cancelled` + `due_date < now()-Ndays`) findet und eine
  `PaymentReminder`-Mail verschickt, **ohne** einen Mahnstatus oder ein
  Mahndatum zu persistieren. Dieser Command wird von Change 1 **nicht**
  angefasst; er ist funktional unabhängig vom neuen `reminded`-Status und
  dem `invoice_dunnings`-Datenmodell. Change 4
  (`add-invoice-dunning-dashboard`) muss beim Entwurf des
  Mahnungs-Triggers klären, ob dieser Command ersetzt, wiederverwendet
  oder parallel weiterbetrieben wird — **außerhalb des Scopes von Change
  1**, hier nur als Kontext-Hinweis für die Datenmodell-Entscheidung
  (Decision D7) vermerkt.
- `backend/database/migrations/2026_05_04_110001_add_cancellation_requested_status_to_bookings_table.php`
  (gesamte Datei) — **Präzedenzfall** im selben Repo für eine additive
  Enum-Erweiterung, treiberspezifisch für `mysql` (raw `ALTER TABLE ...
  MODIFY COLUMN ... ENUM(...)`), `pgsql` (CHECK-Constraint neu setzen)
  und `sqlite` (Tabelle neu anlegen, da SQLite `ALTER COLUMN` nicht
  unterstützt). Dieses Muster wird 1:1 für die Erweiterung von
  `invoices.status` um `reminded` übernommen.
- `backend/phpunit.xml:25-26` — Tests laufen gegen `DB_CONNECTION=sqlite`,
  `DB_DATABASE=:memory:`. Jede Migration in diesem Change muss daher
  **zwingend** einen SQLite-Pfad haben, sonst schlägt die komplette
  Test-Suite fehl (nicht nur die MySQL/Postgres-Matrix aus CLAUDE.md
  Abschnitt 4.2).
- `backend/composer.json` — `laravel/framework: ^11.31`, `php: ^8.2`.
  Laravel 11 benötigt für `Schema::table(...)->nullable()->change()` auf
  einfachen Spaltentypen **kein** `doctrine/dbal` mehr (das Paket taucht
  in `composer.json` `require`/`require-dev` nicht auf) — für die
  Nullable-Änderung von `invoice_number` kann daher der native
  `->change()`-Weg genutzt werden; für die Enum-Erweiterung wird
  trotzdem (konsistent mit dem Präzedenzfall) der treiberspezifische
  Raw-SQL-Weg gewählt, da Laravels Schema-Builder Enum-Wertelisten nicht
  granular ändern kann.
- `backend/app/Services/CourseSessionService.php` (Auszug) — etabliertes
  Muster für fachliche Services unter `app/Services/`, reines PHP ohne
  Interface-Zwang, ausführliche PHPDoc-Blöcke. `InvoiceNumberGenerator`
  folgt diesem Muster.

**Ist-Zustand Frontend:**

- `frontend/src/views/invoices/InvoicesView.vue:6-13` — Status-Filter-Select
  kennt bereits alle fünf bestehenden Werte (nicht `reminded`).
- `frontend/src/views/invoices/InvoicesView.vue:84-88` — Buttons: PDF
  (immer), Bearbeiten (`draft`), "Bezahlt" (`draft`/`sent`). Kein
  Löschen-, Senden- oder Stornieren-Button.
- `frontend/src/views/invoices/InvoicesView.vue:273-293` —
  `invoiceStatusClass()`/`invoiceStatusLabel()` als lokale Lookup-Maps,
  kennen nur die fünf bestehenden Statuswerte.
- `frontend/src/components/InvoiceDetailModal.vue:55-62,144-158,227-247`
  — identisches Muster (Status-Anzeige + Buttons + Lookup-Maps) im
  Detail-Modal, unabhängig von `InvoicesView.vue` gepflegt (Duplikation,
  die dieser Change **nicht** auflöst — siehe Non-Goals).
- `frontend/src/components/InvoiceFormModal.vue:57-65,184-193,200-217,
  284-293,295-327` — Status-Dropdown im Formular, `form.status` mit
  Default `draft`, wird beim Bearbeiten aus `invoice.status` befüllt
  (Zeile 206) und beim Absenden ungefiltert im Payload mitgeschickt
  (Zeile 303).

## Goals / Non-Goals

**Goals:**

- Rechnungsnummer wird ausschließlich beim Übergang Entwurf → Offen
  vergeben, fortlaufend und ohne Lücken (auch bei parallelen Freigaben).
- Eine offene oder bezahlte Rechnung ist inhaltlich unveränderlich
  (Bearbeiten/Löschen nur im Status `draft` möglich).
- Kein automatischer Mailversand mehr beim Erstellen einer Rechnung.
- Neuer Status `reminded` inkl. Datenmodell für mehrstufige Mahnungen mit
  Gebühren (Schema-Ebene, keine Trigger-Logik).
- Storno als eigenständiges, nummeriertes Korrekturdokument, das die
  Original-Rechnung ausgleicht und referenziert.
- Listen-/Detail-Buttons und Sichtbarkeit korrekt pro Status und Rolle,
  serverseitig durchgesetzt (nicht nur im Frontend versteckt).
- Bestehende Teilzahlungs-Fähigkeit des `Payment`-Models bleibt
  unangetastet nutzbar (kein rein binärer "bezahlt"-Status).

**Non-Goals (bewusst außerhalb dieses Change):**

- Kein Versand-Dialog (App-Mail vs. manueller Download) —
  `add-invoice-send-flow`.
- Keine Zahlungseingangs-Eingabemaske — `add-invoice-payment-entry`.
- Kein Mahnungs-Trigger, keine Mahn-E-Mails, kein Dashboard-Widget —
  `add-invoice-dunning-dashboard`.
- Kein Cron/Scheduler für Überfällig-/Mahn-Erkennung (bindende
  Entscheidung 5: Überfällig bleibt zur Anzeigezeit berechnet).
- Keine Bereinigung/Migration bestehender Datensätze mit
  `status = 'overdue'` (siehe Decision D3).
- Keine Anpassung des PDF-Layouts für Stornorechnungen (kein
  "Stornorechnung"-Badge im PDF, siehe `proposal.md` "Offene Fragen"
  Punkt 3).
- Keine Konsolidierung der duplizierten Status-Label-/Klassen-Maps
  zwischen `InvoicesView.vue` und `InvoiceDetailModal.vue` in eine
  gemeinsame Composable — beide Dateien werden parallel, aber
  unabhängig ergänzt (kleinerer Diff, kein Refactoring-Risiko in einem
  ohnehin schon großen Change; YAGNI für dieses Change).
- Keine Anpassung von `SendPaymentReminders`/`PaymentReminder`
  (bestehende, unabhängige Mail-Erinnerungs-Funktion, siehe Context) —
  Klärung ist Aufgabe von Change 4.

## Decisions

**D1. Neuer "Freigeben"-Button im Entwurf-Status, um die Lücke im
Anforderungstext zu schließen.**
Der Anforderungstext listet für "Entwurf" nur PDF/Bearbeiten/Löschen,
beschreibt aber für "Offen" bereits eine Rechnung mit fester Nummer, bei
der der Senden-Button lediglich noch den Versandweg klärt. Ohne einen
expliziten Übergang Entwurf → Offen gäbe es in Change 1 keinen Weg, das
neue Nummernvergabe-Verhalten überhaupt zu testen oder zu nutzen.
Entscheidung: ein zusätzlicher Button "Freigeben" im Entwurf-Status löst
ausschließlich `POST /invoices/{invoice}/finalize` aus (Nummernvergabe +
Statuswechsel `draft` → `sent`), **ohne** Mailversand oder Dialog — das
bleibt vollständig Change 2 vorbehalten, der auf dem bereits offenen
Status aufsetzt. Als offene Frage an Skeptiker/User dokumentiert
(`proposal.md`), da dieser Button nicht wörtlich im Anforderungstext
steht.

**D2. Rechnungsnummern-Generierung als eigener Service, concurrency-safe.**
`StoreInvoiceRequest::generateInvoiceNumber()`
(`backend/app/Http/Requests/StoreInvoiceRequest.php:103-118`) wird nach
`App\Services\InvoiceNumberGenerator::generate(): string` extrahiert
(Single-Responsibility, wiederverwendbar für `finalize()` **und**
`cancel()` — beide brauchen eine neue fortlaufende Nummer aus demselben
Nummernkreis, siehe D5). Die Abfrage des letzten Werts läuft innerhalb
einer `DB::transaction()` mit `lockForUpdate()` auf der Query nach dem
höchsten `invoice_number` des laufenden Jahres — das verhindert
Nummern-Duplikate/-Lücken bei zwei nahezu gleichzeitigen Freigaben
(z. B. zwei Trainer klicken "Freigeben" für unterschiedliche Rechnungen
im selben Moment). `lockForUpdate()` wird von MySQL und PostgreSQL nativ
unterstützt; auf SQLite (Testumgebung) ist es ein No-Op ohne
Fehlerverhalten — portabel im Sinne von CLAUDE.md 4.2.

**D3. `overdue` bleibt im DB-Enum, wird aber nicht mehr aktiv geschrieben.**
Der Enum-Wert `overdue` wird laut Context **nirgends** in
Produktivpfaden gelesen (`isOverdue()`/`scopeOverdue()` sind rein
datumsbasiert) und nur von `InvoiceFactory::overdue()` geschrieben
(Test-Fixture). Bindende Entscheidung 5 legt fest, dass "Überfällig"
weiterhin zur Anzeigezeit berechnet wird. Eine Entfernung des Enum-Werts
würde eine weitere risikobehaftete Schema-Änderung erfordern (erneut
treiberspezifisches Raw-SQL wie in D-Migration M1), ohne fachlichen
Nutzen (YAGNI). Entscheidung: `overdue` bleibt als Enum-Wert bestehen
(Altlasten-Kompatibilität), aber **kein** neuer Code in diesem Change
schreibt ihn aktiv — `UpdateInvoiceRequest` verliert das `status`-Feld
ohnehin vollständig (siehe D4), sodass er über die API gar nicht mehr
setzbar ist. `InvoiceFactory::overdue()` bleibt unverändert gültig, da
Model-Factories nicht über `UpdateInvoiceRequest` laufen.

**D4. `status` wird aus `UpdateInvoiceRequest` entfernt, State-Machine
lebt vollständig in `InvoicePolicy` + dedizierten Endpunkten.**
Alternative geprüft: `status` im Update-Request behalten, aber serverseitig
validieren, welche Übergänge erlaubt sind (State-Machine-Validierung
innerhalb der FormRequest). Verworfen, weil das zu doppelter Logik führen
würde (dieselbe Zulässigkeitsprüfung müsste sowohl in
`UpdateInvoiceRequest` als auch in den neuen dedizierten Endpunkten
`finalize()`/`cancel()`/`markAsPaid()` existieren). Stattdessen: `status`
ändert sich **ausschließlich** über named Actions
(`finalize`, `cancel`, `markAsPaid`, künftig `remind`), jede mit eigener
Policy-Methode, die den aktuellen Status prüft. `update()` (PUT) bleibt
für `dueDate`, `notes` zuständig, aber nur solange `invoice.status ===
'draft'` (durchgesetzt in `InvoicePolicy::update()`, nicht nur im
Request) — das bildet das "festgeschrieben ab Offen"-Erfordernis aus dem
Anforderungstext direkt auf Policy-Ebene ab (State-Pattern-artig: jede
Aktion kennt ihre eigenen Vorbedingungen).

**D5. Storno-Rechnung nutzt denselben Nummernkreis wie reguläre
Rechnungen, kein separates Präfix.**
Der Anforderungstext gibt kein eigenes Nummernformat für Stornorechnungen
vor. Ein separates Präfix (z. B. `ST-`) wäre zusätzliche, nicht
geforderte Komplexität (YAGNI) und bräuchte eine eigene
Lückenlosigkeits-Garantie. Entscheidung: Stornorechnungen erhalten die
nächste reguläre Nummer aus `InvoiceNumberGenerator` (D2), sind aber über
`original_invoice_id` (neue Spalte, self-referencing FK) eindeutig als
Korrekturdokument identifizierbar. Die Rechnung wird direkt mit Status
`sent` angelegt (kein Entwurf-Zwischenschritt, da die Stornierung ein
abgeschlossener, sofortiger Vorgang ist, ausgelöst über einen expliziten
Bestätigungsdialog analog zum bestehenden `markAsPaid`-Confirm-Muster in
`frontend/src/views/invoices/InvoicesView.vue:246-261`).

**D6. Storno-Positionen: negierte Mengen, gleicher Steuersatz.**
Die Stornorechnung übernimmt alle `InvoiceItem`-Datensätze der
Original-Rechnung mit **negierter** `quantity` (Betrag/Steuer werden
dadurch automatisch negativ, da `amount = quantity * unit_price`,
identisch zur bestehenden Berechnung in
`InvoiceController::store():121-133`), unverändertem `unit_price` und
`tax_rate`. `total_amount` der Stornorechnung ergibt sich analog negativ.
Das gleicht die Original-Rechnung exakt aus, ohne die Steuersatz-Logik der
Kleinunternehmerregelung (`Setting::get('company_small_business')`)
erneut anzuwenden — sie wurde zum Zeitpunkt der Original-Rechnung bereits
korrekt berücksichtigt.

**D7. Mahnstufen als eigene Tabelle `invoice_dunnings`, nicht als
zusätzliche Spalten auf `invoices`.**
Bindende Entscheidung 3 fordert mehrstufige Mahnungen mit Gebühren.
Einzelne nullable Spalten (`dunning_date_1`, `dunning_fee_1`,
`dunning_date_2`, ...) würden mit jeder weiteren Stufe wachsen (Verstoß
gegen Open/Closed-Prinzip) und sind nicht abfragbar ("wie viele Stufen
gab es bisher?"). Stattdessen: `invoice_dunnings`-Tabelle
(`invoice_id`, `level` [unsignedTinyInteger], `dunning_date` [date],
`fee_amount` [decimal 10,2], Timestamps) — ein Datensatz pro
ausgelöster Mahnstufe, analog zum etablierten `Payment`-Muster (mehrere
Datensätze pro Rechnung, `backend/app/Models/Payment.php`). `Invoice`
erhält eine `dunnings(): HasMany`-Relation sowie berechnete Attribute
`getDunningLevelAttribute()` (höchste vorhandene `level`) und
`getRemindedAtAttribute()` (Datum der jüngsten Mahnung) — diese Attribute
werden von Change 1 **bereitgestellt**, aber von keinem
Produktivpfad **geschrieben** (kein Trigger in diesem Change, siehe
Non-Goals). Change 4 legt später fest, wie/wann Datensätze erzeugt werden
und wann `invoices.status` auf `reminded` wechselt.

**D8. Kunden-Sichtbarkeit serverseitig in zwei Schichten durchgesetzt.**
`InvoiceController::index()` filtert Kunden zusätzlich zur bestehenden
`customer_id`-Einschränkung (Zeile 47-56) per
`whereIn('status', ['sent','paid','overdue','reminded'])`.
`InvoicePolicy::view()` (Einzelabruf über `show()`/`downloadPdf()`)
erhält dieselbe Status-Prüfung, damit ein Kunde einen Entwurf oder eine
stornierte Rechnung nicht über eine direkt aufgerufene ID/URL einsehen
kann (Defense in Depth — Frontend-Filterung allein wäre unzureichend).

## Migrationen (DB-kritisch — MySQL/PostgreSQL/SQLite-Kompatibilität geprüft)

Alle vier Migrationen sind additiv (keine Datenverluste, keine
Entfernung bestehender Spalten/Werte) und folgen dem im Context
zitierten Präzedenzfall für treiberspezifische Enum-Änderungen.

- **M1 — `..._add_reminded_status_to_invoices_table.php`**
  Erweitert `invoices.status` um den Wert `reminded`.
  - MySQL: `ALTER TABLE invoices MODIFY COLUMN status
    ENUM('draft','sent','paid','overdue','cancelled','reminded') NOT NULL
    DEFAULT 'draft'` (Tabellenpräfix beachten, siehe Präzedenzfall).
  - PostgreSQL: `status` liegt dort als `VARCHAR` mit `CHECK`-Constraint
    vor (kein natives ENUM) — Constraint droppen und mit dem
    zusätzlichen Wert neu anlegen, exakt wie im Präzedenzfall für
    `bookings.status`.
  - SQLite: Tabelle `invoices` per Copy-Rename-Verfahren neu anlegen
    (analog `recreateTableSqlite()` im Präzedenzfall), da `ALTER COLUMN`
    dort nicht unterstützt wird. **Wichtig:** alle bestehenden Spalten
    der `invoices`-Tabelle (inkl. der in M2/M4 neu hinzukommenden, falls
    diese Migration nach M2/M4 einsortiert wird) müssen in der
    SQLite-Rebuild-Funktion vollständig nachgebildet werden — Reihenfolge
    der Migrationen daher bewusst M1 **zuerst** (nur Statuswert-Erweiterung
    auf dem ursprünglichen Schema), M2/M4 danach als einfache additive
    `Schema::table()`-Migrationen (auf SQLite unproblematisch, da reines
    Hinzufügen von Spalten dort nativ unterstützt ist).
- **M2 — `..._create_invoice_dunnings_table.php`**
  Neue Tabelle, keine Änderung an `invoices`. Unkritisch für alle drei
  Treiber (Standard-Migration mit `foreignId()->constrained()`).
- **M3 — `..._make_invoice_number_nullable_on_invoices_table.php`**
  `$table->string('invoice_number')->nullable()->change();` — nativ in
  Laravel 11 ohne `doctrine/dbal` möglich (siehe Context). Der bestehende
  `unique()`-Index bleibt unverändert gültig: sowohl MySQL als auch
  PostgreSQL als auch SQLite erlauben mehrere `NULL`-Werte in einem
  Unique-Index (kein Konflikt zwischen mehreren Entwürfen ohne Nummer).
- **M4 — `..._add_original_invoice_id_to_invoices_table.php`**
  `$table->foreignId('original_invoice_id')->nullable()
  ->after('invoice_number')->constrained('invoices')->nullOnDelete();`
  Selbstreferenzierender Fremdschlüssel, additiv, auf allen drei
  Treibern unproblematisch (Standard-Laravel-FK-Syntax).

**Migrations-Reihenfolge:** M1 → M2 → M3 → M4 (Datum-Präfixe entsprechend
aufsteigend vergeben). Die Tasks in `tasks.md` referenzieren diese
Kürzel.

## Ausblick auf Folge-Changes (nicht Teil dieses Change)

- `add-invoice-send-flow`: baut auf `finalize()` (dieser Change) auf,
  ersetzt den deaktivierten "Senden"-Button-Stub aus `InvoicesView.vue`
  durch einen echten Dialog (App-Mail vs. manueller PDF-Download) und
  verdrahtet `InvoiceWasCreated`/`SendInvoiceCreatedEmail` neu auf diesen
  expliziten Trigger.
- `add-invoice-payment-entry`: baut auf dem bestehenden `Payment`-Model
  und den in diesem Change unveränderten `getTotalPaidAttribute()`/
  `getRemainingBalanceAttribute()` auf, ergänzt die fehlende
  Frontend-Eingabemaske.
- `add-invoice-dunning-dashboard`: baut auf dem in D7 geschaffenen
  `invoice_dunnings`-Datenmodell und dem `reminded`-Status (M1) auf,
  ergänzt Trigger-Logik, Bestätigungsdialog und Dashboard-Widget; klärt
  das Verhältnis zum bestehenden `SendPaymentReminders`-Command (siehe
  Context).

## Risks / Trade-offs

- **Breaking Change für bestehende Workflows/Tests.** Die Verschiebung
  der Nummernvergabe und das Entfernen von `status` aus
  `UpdateInvoiceRequest` sind bewusste, vom User bestätigte
  Verhaltensänderungen (bindende Entscheidungen 1 und 4), betreffen aber
  mindestens `backend/tests/Feature/InvoiceApiTest.php:243-260` (siehe
  `proposal.md` Impact) und potenziell weitere, vom Tester zu
  identifizierende Stellen.
- **Zwei zusätzliche Enum-/Schema-Migrationen mit treiberspezifischem
  Raw-SQL (M1)** erhöhen die Komplexität gegenüber einer reinen
  Eloquent-Änderung, sind aber durch den Präzedenzfall im selben Repo
  gut abgesichert und in CLAUDE.md Abschnitt 4.2 explizit als zulässiger
  Ausnahmefall vorgesehen ("hinter eine Repository-Methode mit
  `DB::connection()->getDriverName()`-Switch").
  Reviewer prüft laut CLAUDE.md Abschnitt 7 (Projektspezifische
  Workflow-Regeln) jede neue Migration explizit auf Postgres-/
  MySQL-Spezifika.
- **"Freigeben"-Button (D1) ist eine Erweiterung über den wörtlichen
  Anforderungstext hinaus.** Risiko: User könnte einen anderen
  UX-Ansatz bevorzugen (z. B. Übergang direkt im — noch nicht
  spezifizierten — Senden-Dialog aus Change 2). Deshalb explizit als
  offene Frage in `proposal.md` markiert statt stillschweigend
  umgesetzt.
- **Konzeptioneller Bruch:** Eine Stornorechnung ist technisch eine ganz
  normale `Invoice`, nur mit `original_invoice_id` und negativen
  Beträgen. Reports/Auswertungen, die künftig `SUM(total_amount)` über
  alle Rechnungen bilden, gleichen sich dadurch automatisch korrekt aus —
  aber jede zukünftige Auswertung muss sich dieser Konvention bewusst
  sein (dokumentiert hier für Folge-Changes).

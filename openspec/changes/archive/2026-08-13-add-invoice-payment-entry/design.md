## Context

**Ist-Zustand Backend:**

- `backend/app/Models/Payment.php:37-44` — `$fillable` = `invoice_id,
  payment_date, amount, payment_method, transaction_id, status`. Kein
  `notes`-Feld.
- `backend/database/migrations/2025_12_22_185135_create_payments_table.php:14-27`
  — `payments`-Tabelle: `invoice_id` (FK, cascade), `payment_date` (date),
  `amount` (decimal 10,2), `payment_method` (enum: `cash, bank_transfer,
  paypal, stripe, credit_card`), `transaction_id` (nullable string),
  `status` (enum: `pending, completed, failed, refunded`, default
  `pending`), Timestamps, Indizes auf `invoice_id`, `payment_date`,
  `status`. Kein `notes`-Feld.
- `backend/app/Http/Controllers/Api/PaymentController.php:87-105`
  (`store()`) — legt einen `Payment`-Datensatz an (Status-Default
  `pending`, siehe `StorePaymentRequest::validatedSnakeCase()` Zeile
  59-71), liest danach `$invoice->payments()->completed()->sum('amount')`
  und setzt bei Erreichen/Überschreiten von `total_amount`
  `$invoice->update(['status'=>'paid','paid_date'=>now()])` — **ohne**
  Transaktion oder Zeilensperre.
- `backend/app/Http/Controllers/Api/PaymentController.php:154-178`
  (`markAsCompleted()`) — identisches Muster: liest, prüft, schreibt,
  ebenfalls ohne Transaktion/Lock.
- `backend/app/Services/PayPalService.php:100-134` (`captureOrder()`,
  Kernzeilen 122-125) — dasselbe Read-then-write-Muster ein drittes Mal,
  unabhängig implementiert, für den nutzerinitiierten
  PayPal-Capture-Pfad.
- `backend/app/Http/Controllers/Api/PaymentController.php:328-350`
  (`handlePaymentCaptureCompleted()`, Kernzeilen 338-347, private Methode,
  aufgerufen aus dem PayPal-Webhook-Handler `handleWebhook()` für das
  Event `PAYMENT.CAPTURE.COMPLETED`, Zeile 296-298) — ein **viertes**,
  strukturell identisches Vorkommen: liest `$invoice->remaining_balance`
  (das intern ebenfalls `payments()->where('status','completed')
  ->sum('amount')` aggregiert) und schreibt danach bedingt
  `status='paid'`/`paid_date=now()`, ohne Transaktion/Lock. Abweichend von
  den anderen drei Stellen prüft dieses Vorkommen `remaining_balance <=
  0.01` (Toleranzgrenze für Rundungsdifferenzen) statt `totalPaid >=
  total_amount` (exakter Vergleich) — funktional sehr ähnlich, aber nicht
  identisch. Liegt in derselben Datei, die T03 ohnehin auf den neuen
  Service umstellt (`store()`/`markAsCompleted()`), wird daher dort
  mitbehoben statt als vierter, unbehobener Fund zurückzubleiben (siehe
  Decision D2, `tasks.md` T03). Kein bestehender Test deckt diesen Pfad ab
  (verifiziert per Grep über `backend/tests/` nach `handleWebhook`/
  `handlePaymentCaptureCompleted`/`PAYMENT.CAPTURE.COMPLETED` — keine
  Treffer), daher keine Regressionsgefahr durch die Umstellung.
- `backend/app/Http/Controllers/Api/InvoiceController.php:218-240`
  (`markAsPaid()`) — setzt `status`/`paid_date` **direkt auf der
  Invoice**, ohne je einen `Payment`-Datensatz zu erzeugen. Route:
  `backend/routes/api.php:182` (`POST /invoices/{invoice}/mark-paid`).
  Policy: `backend/app/Policies/InvoicePolicy.php:110-127`
  (`markAsPaid()`, rollenbasiert, keine Statusprüfung — die liegt laut
  Klassenkommentar bewusst im Controller, analog zu `finalize()`).
- `backend/app/Models/Invoice.php:148-161` — `getTotalPaidAttribute()`/
  `getRemainingBalanceAttribute()` summieren ausschließlich
  `payments()->where('status','completed')->sum('amount')`. Beide
  Attribute sind bereits in `InvoiceResource`
  (`backend/app/Http/Resources/InvoiceResource.php:43-44`, Felder
  `totalPaid`/`remainingBalance`) exponiert und werden von Change 1 seit
  seiner Archivierung unverändert weitergereicht.
- `backend/app/Policies/PaymentPolicy.php:15-66` — `create(User $user):
  bool` prüft ausschließlich `$user->isAdminOrTrainer()`, ohne Bezug zur
  konkreten Rechnung. `viewAny()` liefert für jeden authentifizierten User
  `true`. **Korrektur einer ursprünglich falschen Annahme:**
  `PaymentController::index()` (`backend/app/Http/Controllers/Api/
  PaymentController.php:45-82`) filtert **überhaupt nicht** nach dem
  angemeldeten Nutzer — es gibt dort nur optionale Query-Parameter-Filter
  (`invoiceId`, `paymentMethod`, `status`, `completedOnly`,
  `startDate`/`endDate`), keinen einzigen Bezug zu `$request->user()`.
  Jeder authentifizierte Nutzer (auch ein Kunde) kann über
  `GET /api/v1/payments` grundsätzlich Zahlungen anderer Kunden einsehen —
  es gibt **keine** Filterung, weder für Kunden noch für Trainer. Das ist
  eine vorbestehende, von diesem Change unabhängige Sicherheitslücke
  (siehe `proposal.md` offene Frage 5); dieser Change ändert `index()`
  nicht (Non-Goal) und aktiviert auch keinen neuen UI-Pfad dorthin. Das
  fehlende Trainer-Scoping in `PaymentPolicy::create()` (behoben in
  Decision D4) ist davon unabhängig und wird durch diesen Change erstmals
  über eine echte UI erreichbar.
- `backend/app/Models/Customer.php:52,92` — `trainer_id` (FK) +
  `trainer(): BelongsTo`. `InvoiceController::index()`
  (`backend/app/Http/Controllers/Api/InvoiceController.php:70-74`) nutzt
  dieses Feld bereits, um Trainern nur Rechnungen ihrer zugewiesenen
  Kunden zu zeigen — dasselbe Scoping existiert für `PaymentPolicy`
  aktuell **nicht**.
- `backend/tests/Feature/PaymentApiTest.php` (333 Zeilen, Bestand,
  `test()`-Stil, keine `uses()->group(...)`-Zeile) — 23 grüne Tests,
  decken `store()`/`update()`/`destroy()`/`markAsCompleted()`/`index()`/
  `show()` bereits vollständig für den heutigen Funktionsumfang ab,
  inklusive `'invoice status updates to paid when fully paid'` (Zeile
  141-170) und `'marking payment completed updates invoice status if
  fully paid'` (Zeile 286-305) — beide Tests prüfen `paid_date` nur auf
  `not->toBeNull()`, nicht auf einen konkreten Wert, daher kompatibel mit
  der in Decision D2 vorgesehenen Änderung (`paid_date` = Datum der
  abschließenden Zahlung statt `now()`).
- **Konkreter, vorab bekannter Testkonflikt mit Decision D4:**
  `PaymentApiTest.php`s `beforeEach` (Zeile 13-22) erzeugt `$this->customer
  = Customer::factory()->create(['user_id' => $this->customerUser->id])`
  **ohne** `trainer_id` (`CustomerFactory::definition()`,
  `backend/database/factories/CustomerFactory.php:22-33`, setzt
  `trainer_id` nicht; Spalte ist nullable,
  `backend/database/migrations/2026_01_03_165018_add_trainer_id_to_customers_table.php:15`).
  Der Test `'trainer can create payment'` (Zeile 116-138) lässt
  `$this->trainer` eine Zahlung für die Rechnung von `$this->customer`
  erfassen und erwartet `assertCreated()`. Mit Decision D4s
  Trainer-Scoping (`$invoice->customer->trainer_id === $user->id`) ist
  `trainer_id` in diesem Test `null` → die Policy liefert `false` statt
  `true`, der Test schlägt mit 403 statt 201 fehl. Referenzmuster für die
  Korrektur: `backend/tests/Feature/InvoiceApiTest.php:159` setzt für
  einen analogen Trainer-Scoping-Test bewusst `Customer::factory()->create([
  'trainer_id' => $this->trainer->id])`. **T03 muss denselben Fix in
  `PaymentApiTest.php` nachziehen** (siehe `tasks.md` T03) — sonst
  bricht `composer test` garantiert nach Umsetzung von D4.
- `backend/tests/Feature/InvoiceApiTest.php:410-474` — vier Tests exakt
  für `markAsPaid()` (`'trainer can mark invoice as paid'`, `'customer
  cannot mark invoice as paid'`, `'cannot mark already paid invoice as
  paid'`, `'cannot mark a draft invoice as paid'`).
- `backend/app/Http/Resources/PaymentResource.php:29` — liefert bereits
  `'notes' => $this->notes`, obwohl das Attribut nirgends definiert ist
  (aspirationeller/toter Code, kein Laufzeitfehler).

**Ist-Zustand Frontend:**

- `frontend/src/components/InvoiceDetailModal.vue:134-144` — rendert
  `invoice.payments`, greift aber auf `payment.payment_date`/
  `payment.payment_method` zu (snake_case), obwohl `PaymentResource`
  camelCase liefert (`paymentDate`/`paymentMethod`) — dieser Block hat
  nie korrekt gerendert, weil bislang nie ein `Payment`-Datensatz über
  die UI erzeugt wurde (kein Konsument der `/payments`-API außer PayPal).
- `frontend/src/components/InvoiceDetailModal.vue:163-165,205,232-240`
  — "Als bezahlt markieren"-Button, `mark-paid`-Emit,
  `canMarkAsPaid(invoice)` (nur `status === 'sent'`, spiegelt bewusst
  `InvoiceController::markAsPaid()`s Draft-Ablehnung, siehe Kommentar
  Zeile 232-237).
- `frontend/src/views/invoices/InvoicesView.vue:134,332-347` —
  `@mark-paid="markAsPaid"`, `markAsPaid()`-Handler mit `confirm()`-Dialog
  und `POST /invoices/{id}/mark-paid`. **Kein** Listenzeilen-Button dafür
  (nur im Detail-Modal erreichbar) — die Tabellenzeile
  (`InvoicesView.vue:96-103`) kennt PDF/Bearbeiten/Löschen/Freigeben/
  Senden/Stornieren, aber keinen "Bezahlt"-Button.
- `frontend/src/views/invoices/InvoicesView.vue:88-90` — zeigt bereits
  `Bezahlt am {{ formatDate(invoice.paidDate) }}` für `status === 'paid'`
  — bleibt unverändert bestehen, profitiert aber von der in D2
  korrigierten `paid_date`-Berechnung.
- `frontend/src/components/InvoiceSendDialog.vue` (98 Zeilen) —
  etabliertes Referenzmuster für einen neuen, eigenständigen Dialog:
  `@headlessui/vue` (`TransitionRoot`/`Dialog`/`DialogPanel`/
  `DialogTitle`), reine Props (`isOpen`, `invoice`) + Events, kein
  `apiClient`-Import im Dialog selbst. `InvoicePaymentDialog.vue`
  übernimmt dieses Muster unverändert.
- `frontend/src/views/invoices/InvoicesView.vue:172-179` —
  `sendDialogInvoice` als **eigener** Ref, bewusst getrennt von
  `selectedInvoice`, weil der Send-Dialog über dem weiterhin geöffneten
  Detail-Modal geöffnet werden kann (Review-Fix aus Change 2). Dasselbe
  Muster gilt für den neuen `InvoicePaymentDialog`.
- `frontend/src/components/PaymentModal.vue` — bestehende, **kundenseitige**
  PayPal-Checkout-Komponente (`Teleport`+`Transition`-Pattern, nicht
  `@headlessui/vue`). Andere Zielgruppe (Kunde zahlt selbst per PayPal),
  andere Datenquelle (`PayPalButton.vue` → `/payments/paypal/*`). Bleibt
  unverändert; wird **nicht** wiederverwendet oder umgebaut.
- `frontend/src/api/paypal.ts` — einziger bestehender Frontend-Konsument
  von `/api/v1/payments/*`, ausschließlich der beiden PayPal-Routen.
  `POST /api/v1/payments` (generischer `apiResource`-Store) wird
  **nirgends** im Frontend aufgerufen (verifiziert per Grep) — die neue
  Eingabemaske wird dessen erster echter UI-Konsument.

## Goals / Non-Goals

**Goals:**

- Admin/Trainer können über eine echte Eingabemaske (Betrag, Datum,
  Zahlungsart, optionale Referenz) einen Zahlungseingang für eine offene
  Rechnung erfassen.
- Mehrere Teilzahlungen pro Rechnung sind möglich; die Rechnung wechselt
  automatisch zu `paid`, sobald die Summe abgeschlossener Zahlungen den
  Gesamtbetrag erreicht — kein manueller "Als bezahlt markieren"-Klick
  mehr.
- Liste und Detailansicht zeigen den Zahlungseingang: vollständig bezahlt
  weiterhin über das bestehende `paidDate`-Badge, teilweise bezahlt über
  eine neue Fortschrittsanzeige (bezahlt/Rest).
- Die neu erfasste Zahlung + der resultierende Statuswechsel sind atomar
  (keine Race Condition bei zwei nahezu gleichzeitigen Teilzahlungen).
- Die gefundene Dateninkonsistenz von `markAsPaid()` (Status `paid` ohne
  zugehörigen `Payment`-Datensatz) wird nicht fortgeschrieben.

**Non-Goals (bewusst außerhalb dieses Change):**

- Kein Mahnungs-Trigger, kein Dashboard-Widget (Change 4).
- Keine Änderung an `PayPalService::captureOrder()` (dieselbe Race
  Condition bleibt dort bestehen, siehe Decision D2 und Risiko-Abschnitt
  — eigener Folge-Change empfohlen). Die vierte, strukturell identische
  Fundstelle `PaymentController::handlePaymentCaptureCompleted()` ist
  dagegen **kein** Non-Goal — sie liegt in derselben Datei, die T03
  ohnehin auf den neuen Service umstellt, und wird dort mitbehoben.
- Keine Änderung an `PaymentController::index()` — filtert aktuell
  überhaupt nicht nach dem angemeldeten Nutzer (siehe Context, korrigierte
  Ist-Zustand-Beschreibung). Vorbestehende, unabhängige Lücke; dieser
  Change aktiviert keinen neuen UI-Pfad dorthin und ändert `index()`
  nicht. Dokumentiert als offene Frage 5 in `proposal.md`, kein impliziter
  Fix hier.
- Keine Korrektur-/Storno-UI für bereits erfasste Zahlungen
  (`PaymentController::update()`/`destroy()` bleiben unverändert und ohne
  UI-Anbindung, siehe `proposal.md` offene Frage 3).
- Keine Erweiterung von `PaymentPolicy::view()`/`update()`/`delete()` um
  Trainer-Scoping (nur `create()`, siehe Decision D4 — YAGNI für
  weiterhin ungenutzte Endpunkte).
- Keine Änderung an `PaymentController::update()`s fehlendem
  Statuswechsel-Trigger (eine über `PUT` nachträglich auf `completed`
  gesetzte Zahlung löst weiterhin keinen Rechnungsstatuswechsel aus —
  vorbestehende, unabhängige Lücke, da die neue Eingabemaske
  ausschließlich `store()` nutzt).
- Kein Überzahlungs-/Guthabenkonzept (siehe `proposal.md` offene Frage 1
  — Standardverhalten: Ablehnung mit 422).

## Decisions

**D1. `markAsPaid()` wird ersatzlos entfernt statt in-place gefixt.**
Alternative geprüft: `markAsPaid()` beibehalten, aber intern so
umgebaut, dass es einen "Schatten-`Payment`"-Datensatz in Höhe des vollen
`remainingBalance` anlegt, um das Invariant zu retten. Verworfen, weil das
zwei parallele, funktional identische Wege ("Formular ausfüllen" vs.
"ein Klick, Betrag wird im Hintergrund synthetisiert") für exakt dieselbe
fachliche Aktion hinterlassen würde — Verstoß gegen DRY/KISS, zusätzlicher
Wartungsaufwand (zwei Code-Pfade, zwei Berechtigungsprüfungen, zwei
Tests-Sets) für einen UI-Baustein, den der Anforderungstext explizit durch
eine Eingabemaske ersetzt sehen will ("Hierzu muss eine Eingabemaske
erstellt werden"). Der neue `InvoicePaymentDialog` bietet eine
"volle Restsumme"-Vorbelegung (Betragsfeld defaultet auf
`remainingBalance`) als gleichwertig schnellen Ein-Klick-Ersatz. Route,
Controller-Methode, Policy-Methode und die vier zugehörigen Tests werden
entfernt (siehe `tasks.md` T04). Als offene Frage 2 in `proposal.md`
markiert, da dies eine funktionale Entfernung ist, kein rein additiver
Zusatz.

**D2. Neuer Service `App\Services\InvoicePaymentRecorder`, atomar via
`lockForUpdate()`, `paid_date` = Zahlungsdatum statt `now()`.**
Aktuell dupliziert dasselbe ungesicherte Read-then-write-Muster
("Summe/Restbetrag abgeschlossener Zahlungen lesen, dann bedingt Status
setzen") an **vier Stellen über zwei Dateien**:
`PaymentController::store()`, `PaymentController::markAsCompleted()`,
`PaymentController::handlePaymentCaptureCompleted()` (Webhook-Pfad, alle
drei in derselben Datei) sowie unabhängig davon
`PayPalService::captureOrder()`. Das ist exakt dieselbe Fehlerklasse, die
Change 1 Decision D2 für die Rechnungsnummern-Vergabe bereits gefunden und
behoben hat: zwei nahezu gleichzeitige Teilzahlungen könnten beide
`totalPaid < total_amount` lesen, bevor eine von beiden committet, und die
Rechnung bliebe trotz voller Bezahlung im Status `sent`. Entscheidung: ein
neuer Service kapselt

```php
final class InvoicePaymentRecorder
{
    public function record(Invoice $invoice, array $paymentData): Payment
    {
        return DB::transaction(function () use ($invoice, $paymentData) {
            $locked = Invoice::query()->lockForUpdate()->findOrFail($invoice->id);
            $payment = $locked->payments()->create($paymentData);
            $this->syncStatus($locked, $payment);
            return $payment;
        });
    }

    public function completeExisting(Payment $payment): Payment
    {
        return DB::transaction(function () use ($payment) {
            $locked = Invoice::query()->lockForUpdate()->findOrFail($payment->invoice_id);
            $payment->update(['status' => 'completed']);
            $this->syncStatus($locked, $payment->fresh());
            return $payment->fresh();
        });
    }

    private function syncStatus(Invoice $invoice, Payment $justRecorded): void
    {
        $totalPaid = $invoice->payments()->completed()->sum('amount');
        if ($totalPaid >= $invoice->total_amount) {
            $invoice->update([
                'status' => 'paid',
                'paid_date' => $justRecorded->status === 'completed'
                    ? $justRecorded->payment_date
                    : $invoice->payments()->completed()->max('payment_date'),
            ]);
        }
    }
}
```

genutzt von `PaymentController::store()` (`record()`), `::markAsCompleted()`
**und** `::handlePaymentCaptureCompleted()` (beide Letztere über
`completeExisting()`, siehe `tasks.md` T03). Für
`handlePaymentCaptureCompleted()` entfällt dabei der bisherige
`remaining_balance <= 0.01`-Toleranzvergleich zugunsten des einheitlichen,
exakten `totalPaid >= total_amount`-Vergleichs aus `syncStatus()` —
geringfügige, aber konsistenzstiftende Verhaltensänderung, siehe
Risiko-Abschnitt. `lockForUpdate()` wird von MySQL und PostgreSQL nativ
unterstützt; auf SQLite (Testumgebung) ist es laut Laravel-Dokumentation
ein No-Op ohne Fehlerverhalten — portabel im Sinne von CLAUDE.md 4.2,
analog zur bereits etablierten Begründung in Change 1 Decision D2.
**`PayPalService::captureOrder()` wird bewusst nicht mitrefaktoriert**
(siehe Non-Goals) — das hätte den nutzerinitiierten
PayPal-Checkout-Flow angefasst, ein unabhängiges, höheres Risiko, das
nicht Teil dieser Anforderung ist; im Risiko-Abschnitt als
Folge-Empfehlung vermerkt (analog zur Empfehlung aus Change 2 für die
doppelte Event-Registrierung bei `BookingCreated`/`UserRegistered`).

**D3. Geschäftsregeln (offener Status, keine Überzahlung) leben im
Controller, nicht im `FormRequest`.**
Konsistent mit dem etablierten Split-Muster aus Change 1/2 ("Policy = darf
diese Rolle grundsätzlich handeln, Controller = ist die Aktion im
aktuellen Zustand gültig", `InvoicePolicy::finalize()`-Kommentar).
`PaymentController::store()` prüft nach `$this->authorize(...)`:

```php
private const PAYABLE_STATUSES = ['sent', 'reminded', 'overdue'];
```

- `! in_array($invoice->status, self::PAYABLE_STATUSES, true)` → 422
  "Für diese Rechnung können aktuell keine Zahlungen erfasst werden."
- `$data['amount'] > $invoice->remaining_balance` → 422 "Der
  Zahlungsbetrag übersteigt den offenen Restbetrag von {formatiert}."
  (siehe `proposal.md` offene Frage 1 für die Grundsatzentscheidung).

Beide Prüfungen laufen **vor** `InvoicePaymentRecorder::record()` und
sind bewusst nicht im `FormRequest`, weil sie den aktuellen
Datenbankzustand der referenzierten Rechnung benötigen (kein reiner
Eingabe-Constraint) — Konsistenz mit `sendEmail()`/`cancel()`/`finalize()`.

**D4. `PaymentPolicy::create()` erhält Rechnungs-Kontext und
Trainer-Scoping.**
`PaymentPolicy::create(User $user): bool` prüft aktuell nur die Rolle.
Solange `POST /payments` über keine UI erreichbar war, war eine fehlende
Kunden-Zuordnungsprüfung für Trainer folgenlos. Mit der neuen Eingabemaske
wird dieser Pfad erstmals aktiv von Trainern genutzt — dieselbe
Kundenzuordnungs-Regel, die `InvoiceController::index()` bereits für
Trainer durchsetzt (`Customer::trainer_id === $user->id`), muss hier
ebenfalls gelten, sonst könnte ein Trainer über die neue Maske eine
Zahlung für die Rechnung eines fremden Kunden erfassen. Entscheidung:

```php
public function create(User $user, Invoice $invoice): bool
{
    if ($user->isAdmin()) {
        return true;
    }

    return $user->isTrainer() && $invoice->customer->trainer_id === $user->id;
}
```

aufgerufen als `$this->authorize('create', [Payment::class, $invoice])`
in `PaymentController::store()` (Laravel löst zusätzliche Argumente nach
`$user` an die Policy-Methode durch). **Nur `create()`** wird angepasst
(siehe Non-Goals) — `view()`/`update()`/`delete()` bleiben unverändert,
weil sie weiterhin über keine UI erreichbar sind (YAGNI, kein
Scope-Kriechen).

**D5. `payments.notes` als neue, additive Spalte statt totem
`PaymentResource`-Feld.**
`PaymentResource::toArray()` liefert bereits `'notes' => $this->notes`,
ein Feld, das nie existiert hat. Der Anforderungstext nennt "ggf. …
Referenz" als Bestandteil der Eingabemaske. Statt das tote Feld aus der
Resource zu entfernen (kleinerer, aber rückwärtsgerichteter Diff) wird es
mit einer neuen Spalte real gemacht — die naheliegendere, additive
Lösung, die den bereits vom Ursprungscode intendierten Zustand herstellt:

```php
$table->text('notes')->nullable();
```

Rein additiv, kein Enum, kein Raw SQL — unkritisch für MySQL/PostgreSQL/
SQLite (CLAUDE.md 4.2). `Payment::$fillable` und `StorePaymentRequest`/
`UpdatePaymentRequest` (Feld `notes`, `nullable|string|max:1000`) werden
entsprechend ergänzt.

**D6. Frontend: `InvoicePaymentDialog.vue` als eigene Komponente, kein
Umbau von `PaymentModal.vue`.**
`PaymentModal.vue` ist bewusst PayPal-spezifisch und kundenseitig (Kunde
initiiert und bezahlt selbst). Die neue Eingabemaske ist administrativ
(Admin/Trainer erfasst eine bereits erhaltene Zahlung, beliebige
Zahlungsart). Eine gemeinsame Komponente müsste per Prop zwischen zwei
grundverschiedenen Formular-Inhalten und Autorisierungskontexten
umschalten (Verstoß gegen Single-Responsibility). `InvoicePaymentDialog.vue`
folgt stattdessen dem etablierten `InvoiceSendDialog.vue`-Muster
(`@headlessui/vue`, reine Props/Events, kein `apiClient`-Import),
konsistent mit den übrigen Invoice-Dialogen. `InvoicesView.vue` mountet
ihn analog zu `showSendDialog`/`sendDialogInvoice` mit einem eigenen
`paymentDialogInvoice`-Ref (nicht `selectedInvoice`, gleiche Begründung
wie beim Send-Dialog: könnte über dem geöffneten Detail-Modal geöffnet
werden) und führt den eigentlichen `POST /api/v1/payments`-Aufruf mit
`status: 'completed'` explizit im Payload aus (der API-Standard-Default
`pending` bleibt für andere, zukünftige Konsumenten unverändert — die
manuelle Erfassung durch einen Admin/Trainer bestätigt eine bereits
erhaltene Zahlung, keinen ausstehenden Vorgang).

## Migrationen (DB-kritisch — MySQL/PostgreSQL/SQLite-Kompatibilität geprüft)

- **M1 — `..._add_notes_to_payments_table.php`**
  `$table->text('notes')->nullable();` auf der bestehenden
  `payments`-Tabelle. Rein additiv, Standard-Laravel-Schema-Builder, kein
  Enum, kein Raw SQL, keine Locking-/Concurrency-Semantik — auf allen
  drei Treibern unproblematisch (kein treiberspezifischer Pfad nötig,
  im Unterschied zu Change 1s Enum-Erweiterung M1).

**Kein weiteres Schema-Risiko.** Die in Decision D2 eingeführte
`lockForUpdate()`-Logik ist reine Query-Semantik, keine Migration —
**muss aber laut CLAUDE.md-Hinweis zur Change-1-Lektion ("`composer qa`
läuft standardmäßig gegen SQLite und kann PostgreSQL-spezifische
Transaktions-/Retry-Bugs verdecken") zusätzlich gegen echtes PostgreSQL
getestet werden**, siehe `tasks.md` T02 Akzeptanzkriterien.

## Ausblick auf Folge-Changes (nicht Teil dieses Change)

- `add-invoice-dunning-dashboard`: unverändert wie in Change 1/2
  beschrieben — nutzt das `invoice_dunnings`-Datenmodell und den
  `reminded`-Status. Von diesem Change nicht berührt.
- Empfohlener, unabhängiger Folge-Change: `PayPalService`s identisches
  Race-Condition-Muster (siehe Decision D2) auf denselben
  `InvoicePaymentRecorder` umstellen.
- Möglicher Folge-Change: Korrektur-/Storno-UI für einzelne
  `Payment`-Datensätze (siehe `proposal.md` offene Frage 3).

## Risks / Trade-offs

- **Breaking Change: `markAsPaid()` entfällt.** Betrifft vier bestehende
  Backend-Tests (siehe Context) sowie den Frontend-Button — bewusste,
  zur Bestätigung markierte Entscheidung (siehe `proposal.md` offene
  Frage 2), analog zu den bewussten Breaking Changes in Change 1.
- **`PayPalService::captureOrder()` bleibt mit derselben Race Condition
  wie vor diesem Change.** Nicht verschlechtert (Status quo), aber auch
  nicht behoben — explizit als Folge-Empfehlung dokumentiert statt
  stillschweigend ignoriert.
- **`handlePaymentCaptureCompleted()` verliert die bisherige
  `remaining_balance <= 0.01`-Toleranzgrenze** zugunsten des exakten
  `totalPaid >= total_amount`-Vergleichs der übrigen drei Stellen. Für
  Beträge, die in `payment_date`-Kombinationen minimal von `total_amount`
  abweichen (Rundungsdifferenzen im Cent-Bereich), könnte dieser Pfad
  dadurch geringfügig strenger werden als zuvor. Kein bestehender Test
  deckt dieses Verhalten ab (siehe Context), daher kein Regressionsrisiko
  gegen die Bestandstest-Suite — aber im Betrieb zu beobachten.
- **Vorbestehende, unabhängige Sicherheitslücke in
  `PaymentController::index()` (keine Nutzerfilterung) bleibt bestehen.**
  Nicht verschlechtert und nicht Teil dieses Change (siehe Non-Goals,
  `proposal.md` offene Frage 5) — wird hier nur korrekt dokumentiert,
  damit sie nicht implizit als "bereits geschützt" missverstanden wird.
- **`lockForUpdate()` erhöht die Transaktionsdauer bei paralleler
  Zahlungserfassung geringfügig.** Für die erwartete Frequenz (seltene,
  manuelle Admin/Trainer-Aktion, kein Hochfrequenz-Pfad) unkritisch,
  analog zur bereits akzeptierten Begründung in Change 1 Decision D2.
- **Konservative Überzahlungs-Ablehnung könnte im Betrieb zu restriktiv
  sein** (z. B. Rundungsdifferenzen von wenigen Cent bei
  Fremdwährungs-Umrechnungen). Da der Anforderungstext dazu schweigt,
  bewusst als offene Frage 1 markiert statt einer stillen Toleranzgrenze
  (z. B. `+0.01`) — falls im Betrieb nötig, ein kleiner, gezielter
  Folge-Fix.

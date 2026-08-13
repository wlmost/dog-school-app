# Verification: add-invoice-payment-entry

**Gesamtstatus:** nacharbeit-am-design-nötig

`openspec validate add-invoice-payment-entry` → **valid** (strukturell ok,
siehe Schritt 0). Der inhaltliche Realitätsabgleich unten fand jedoch einen
konkreten, testbaren Widerspruch (Trainer-Scoping bricht einen Bestandstest)
sowie zwei Zitat-/Beleg-Fehler, die vor Freigabe korrigiert werden sollten.

## Bestätigt

- `proposal.md`/`design.md`: `InvoiceController::markAsPaid()` setzt
  `status='paid'`/`paid_date=now()` direkt auf der Invoice, ohne je einen
  `Payment`-Datensatz anzulegen → bestätigt in
  `backend/app/Http/Controllers/Api/InvoiceController.php:218-240`.
- `Invoice::getTotalPaidAttribute()`/`getRemainingBalanceAttribute()`
  summieren ausschließlich `payments()->where('status','completed')->sum('amount')`
  → bestätigt in `backend/app/Models/Invoice.php:148-161`.
- `Invoice::isPaid()` prüft nur `$this->status === 'paid'`
  (`backend/app/Models/Invoice.php:132-135`) → nach `markAsPaid()` liefert
  `isPaid() === true`, während `totalPaid === 0`/`remainingBalance ===
  total_amount` bleiben, weil `total_paid` unabhängig vom `status`-Feld aus
  `payments` berechnet wird. Der behauptete Invariant-Bruch ist real.
- Route `POST /invoices/{invoice}/mark-paid` → bestätigt in
  `backend/routes/api.php:182`. `InvoicePolicy::markAsPaid()` (rollenbasiert,
  keine Statusprüfung) → bestätigt in
  `backend/app/Policies/InvoicePolicy.php:124-127`.
- `paid_date` wird in `markAsPaid()` aus `now()` gesetzt, nicht aus einem
  echten Zahlungsdatum → bestätigt, `InvoiceController.php:237`.
- Bestehende Payment-API ohne generischen Frontend-Konsumenten: Grep über
  `frontend/src` findet `/api/v1/payments/*`-Zugriffe ausschließlich in
  `frontend/src/api/paypal.ts:30,43` (beide PayPal-spezifisch,
  `create-order`/`capture-order`). Kein Aufruf von `POST /api/v1/payments`
  im Frontend gefunden → bestätigt.
- `frontend/src/components/InvoiceDetailModal.vue:140` liest
  `payment.payment_date`/`payment.payment_method` (snake_case) →
  bestätigt exakt (Zeile 140). `PaymentResource::toArray()` liefert
  camelCase `paymentDate`/`paymentMethod` →
  bestätigt, `backend/app/Http/Resources/PaymentResource.php:25-26`.
  Der Zahlungen-Block kann also nie korrekt gerendert haben.
- `PaymentResource::toArray()` referenziert bereits `'notes' =>
  $this->notes` (Zeile 29) → bestätigt. Weder in der Migration
  `2025_12_22_185135_create_payments_table.php:14-27` noch in
  `Payment::$fillable` (`backend/app/Models/Payment.php:37-42`) existiert
  eine `notes`-Spalte → bestätigt, totes Feld.
- `PaymentPolicy::create(User $user): bool` prüft ausschließlich
  `$user->isAdminOrTrainer()`, kein Bezug zur Rechnung/zum Kunden →
  bestätigt, `backend/app/Policies/PaymentPolicy.php:39-43`.
  `InvoiceController::index()` etabliert das referenzierte
  Trainer-Scoping-Muster über `Customer::trainer_id` bereits →
  bestätigt, `InvoiceController.php:70-74`, `Customer::trainer_id`
  existiert (`backend/app/Models/Customer.php:52,92`). Fehlendes
  Trainer-Scoping in `PaymentPolicy::create()` ist eine reale
  Autorisierungslücke, sobald die neue UI diesen Pfad aktiviert.
- `PaymentController::store()` (Race Condition 1/4): liest
  `$invoice->payments()->completed()->sum('amount')` und schreibt
  danach bedingt `status='paid'`, ohne Transaktion/Lock → bestätigt,
  `backend/app/Http/Controllers/Api/PaymentController.php:87-104`
  (Kernzeilen 95-101, design.md nennt "93-102", minimal abweichend, aber
  inhaltlich korrekt).
- `PaymentController::markAsCompleted()` (Race Condition 2/4): identisches
  Read-then-write-Muster → bestätigt, `PaymentController.php:154-179`
  (Kernzeilen 168-174, design.md nennt "166-175", minimal abweichend,
  inhaltlich korrekt).
- `PayPalService::captureOrder()` (Race Condition 3/4): identisches Muster
  → bestätigt, `backend/app/Services/PayPalService.php:100-134`
  (Kernzeilen 122-125; design.md nennt "112-125", inhaltlich korrekt).
- Alle drei Vorkommen sind strukturell identisch: "Summe abgeschlossener
  Zahlungen lesen → wenn `>= total_amount` → `Invoice::update(['status' =>
  'paid', 'paid_date' => now()])`", jeweils ohne `DB::transaction()`/
  `lockForUpdate()`. Bestätigt für die drei explizit benannten Stellen.
- `lockForUpdate()`-Präzedenzfall aus Change 1 existiert bereits:
  `backend/app/Services/InvoiceNumberGenerator.php:28-45` nutzt dasselbe
  Muster inkl. SQLite-No-Op-Doku-Kommentar → bestätigt, Analogie in
  Decision D2 ist stichhaltig.
- Invoice-Status-Enum: `draft, sent, paid, overdue, cancelled, reminded`
  → bestätigt, `backend/database/migrations/2026_08_12_130001_add_reminded_status_to_invoices_table.php:42`.
  `PAYABLE_STATUSES = ['sent','reminded','overdue']` deckt exakt die
  offenen Nicht-Endzustände ab.
- `PaymentApiTest.php` hat 23 Tests im `test()`-Stil, keine
  `uses()->group(...)`-Zeile → bestätigt (`grep -c "^test("` = 23,
  `backend/tests/Feature/PaymentApiTest.php`).
  `'invoice status updates to paid when fully paid'` (Zeile 141) und
  `'marking payment completed updates invoice status if fully paid'`
  (Zeile 286) prüfen `paid_date` nur mit `not->toBeNull()` → bestätigt
  (Zeilen 169, 304).
- `InvoiceApiTest.php:410-474` enthält exakt vier `mark-paid`-Tests
  (Zeilen 417, 436, 450, 467 nutzen die Route) → bestätigt.
- Kein weiterer Frontend- oder Backend-Aufruf von `markAsPaid`/`mark-paid`
  außerhalb der bereits von `tasks.md` T04/T06/T07 erfassten Stellen
  gefunden (Grep über `frontend/src` und `backend/app`, `backend/routes`,
  `backend/tests`) → Entfernung ist konsistent geplant, keine verwaiste
  Referenz übersehen. (Hinweis: `InvoicePolicy.php:100,116,122,154` und
  `InvoiceDetailModal.vue:232` enthalten nur Doc-Kommentare, die nach der
  Entfernung veraltet wären — kein funktionaler Bruch, aber nicht explizit
  in `tasks.md` als Aufräumarbeit erwähnt.)
- Migration M1 (`payments.notes`, `$table->text('notes')->nullable()`) ist
  rein additiv, keine Enum-/Raw-SQL-Konstrukte → CLAUDE.md 4.2-konform.
  Keine Datei mit dem geplanten Namen
  `2026_08_13_100001_add_notes_to_payments_table.php` existiert bereits
  (kein Namenskonflikt).
- Code-Beispiel `InvoicePaymentRecorder` in `design.md` (D2) verwendet
  keine PHP-8.3/8.4-Features (keine Property Hooks, kein
  `#[\Override]`, keine typed class constants, kein `new
  Foo()->bar()`) → CLAUDE.md 4.1-konform.

## Widerlegt

- **`design.md` Context, Z.44-47: "`viewAny()` liefert für jeden
  authentifizierten User `true` (nur `index()`-Query filtert Kunden auf
  eigene Rechnungen, siehe `PaymentController::index()` — kein
  Trainer-Scoping dort)."** Tatsächlich filtert
  `PaymentController::index()` (`backend/app/Http/Controllers/Api/PaymentController.php:45-80`)
  **überhaupt nicht** nach dem angemeldeten Nutzer — es gibt nur
  Query-Parameter-Filter (`invoiceId`, `paymentMethod`, `status`,
  `completedOnly`, `startDate`/`endDate`), keinen einzigen Bezug zu
  `$request->user()`. Ein Kunde kann über `GET /api/v1/payments` (ohne
  oder mit fremder `invoiceId`) grundsätzlich Zahlungen anderer Kunden
  sehen; der einzige Test, der in die Nähe kommt
  (`backend/tests/Feature/PaymentApiTest.php:46-55`, `'customer can list
  payments for their invoices'`), testet nur den positiven Filterfall,
  nicht die Isolation gegen fremde Zahlungen. Die Behauptung "Kunden
  werden auf eigene Rechnungen gefiltert" ist falsch — es gibt **keine**
  Filterung, weder für Kunden noch für Trainer. Das ist zwar außerhalb
  des Scopes dieses Change (Non-Goal: `index()` bleibt unverändert), aber
  die fehlerhafte Ist-Zustand-Beschreibung sollte korrigiert werden, weil
  sie die Risikoeinschätzung der bewussten Nicht-Änderung verzerrt
  (der bestehende Zugriffsschutz ist schwächer als dargestellt).
- **`tasks.md` T03, Z.93-96 / `design.md` Context, Z.54-63: "Alle 23
  bestehenden Tests in `PaymentApiTest.php` bleiben grün, unverändert im
  Testinhalt … keine der bestehenden Assertions widerspricht den neuen
  Regeln."** Widerlegt für den Test `'trainer can create payment'`
  (`backend/tests/Feature/PaymentApiTest.php:116-138`). Das `beforeEach`
  (Zeile 13-22) erzeugt `$this->customer = Customer::factory()->create([
  'user_id' => $this->customerUser->id])` **ohne** `trainer_id`.
  `CustomerFactory::definition()`
  (`backend/database/factories/CustomerFactory.php:22-32`) setzt
  `trainer_id` nicht, die Spalte ist nullable
  (`backend/database/migrations/2026_01_03_165018_add_trainer_id_to_customers_table.php:15`).
  Nach Decision D4 (`PaymentPolicy::create(User $user, Invoice $invoice):
  bool` mit `$user->isTrainer() && $invoice->customer->trainer_id ===
  $user->id`) ist `$invoice->customer->trainer_id` in diesem Test `null`,
  `$user->id` ungleich `null` → die Policy liefert `false`, der Test
  erwartet aber `->assertCreated()`. Ohne Anpassung des Tests
  (`trainer_id` im `beforeEach` oder im Test selbst setzen) bricht dieser
  Bestandstest durch die neue Trainer-Scoping-Regel. Zum Vergleich:
  `backend/tests/Feature/InvoiceApiTest.php:159` setzt für einen
  analogen Trainer-Scoping-Test bewusst `Customer::factory()->create([
  'trainer_id' => $this->trainer->id])` — dieses Muster fehlt in
  `PaymentApiTest.php`s Setup und wird in `tasks.md` T03 nicht als
  nötige Anpassung erwähnt (T03 listet `PaymentApiTest.php` nicht einmal
  unter "Dateien", nur `InvoicePaymentApiTest.php` als neue Datei).

## Nicht auffindbar / ungenau belegt

- **`design.md` Context, Z.25-27 / `proposal.md` Non-Goals: "dasselbe
  Read-then-write-Muster ein drittes Mal … `PayPalService.php:112-125,
  341-348`".** Die Datei `backend/app/Services/PayPalService.php` hat nur
  173 Zeilen insgesamt — Zeilen 341-348 existieren dort nicht. Das
  tatsächliche vierte Vorkommen desselben Musters liegt in
  `backend/app/Http/Controllers/Api/PaymentController.php:328-350`
  (private Methode `handlePaymentCaptureCompleted()`, aufgerufen aus dem
  Webhook-Handler `handleWebhook()`, Zeile 297) — **derselben Datei**, die
  T03 ohnehin bearbeitet (`store()`/`markAsCompleted()` werden umgestellt).
  Die "dreifache Duplikation" ist tatsächlich eine **vierfache**
  Duplikation über zwei Dateien (`PaymentController.php`: 3 Vorkommen,
  `PayPalService.php`: 1 Vorkommen). Nach diesem Change bleibt die
  Race Condition also nicht nur in `PayPalService.php` bestehen (wie
  Non-Goals/Risks es darstellen), sondern **auch weiterhin in
  `PaymentController::handlePaymentCaptureCompleted()`** — derselben
  Datei, die T03 bereits anfasst. `tasks.md` T03 erwähnt diese Methode
  nicht; ob sie bewusst oder versehentlich ausgenommen bleibt, ist nicht
  dokumentiert.
- **`proposal.md` Z.24-28: wörtliches Zitat "Teilzahlungen werden
  unterstützt … Rechnung bleibt … bis die Summe der abgeschlossenen
  Zahlungen dem Gesamtbetrag entspricht" mit Beleg "(Triage
  `openspec/triage/20260812-rechnungsworkflow.md`)".** Der Wortlaut ist
  in der Triage-Datei nicht auffindbar (Grep nach "abgeschlossenen
  Zahlungen dem Gesamtbetrag entspricht" und "Teilzahlungen werden
  unterstützt" liefert keinen Treffer). Die Triage-Datei formuliert die
  Teilzahlungsfrage stattdessen als **offene Frage**
  (`openspec/triage/20260812-rechnungsworkflow.md:195-198`: "Verlangt die
  Anforderung … nur eine einzelne Vollzahlung, oder sollen Teilzahlungen
  … unterstützt werden?"). Die tatsächlich bindende Entscheidung findet
  sich in `openspec/changes/archive/2026-08-12-add-invoice-status-lifecycle/acceptance.md:139-141`
  ("6. Teilzahlungen unterstützt. … Statusmodell kompatibel, UI folgt in
  Change 3."), ebenfalls nicht wortgleich mit dem Zitat in `proposal.md`.
  Der Beleg zeigt auf die falsche Quelle (Triage statt Change-1-Acceptance)
  und das Anführungszeichen suggeriert ein wörtliches Zitat, das so nicht
  existiert — inhaltlich ist die Kernaussage (Teilzahlungen sind
  bindend vereinbart) aber durch `acceptance.md` Punkt 6 gedeckt.

## Neue Elemente (Plausibilität)

- `backend/database/migrations/2026_08_13_100001_add_notes_to_payments_table.php`
  — Pfad/Namensschema konsistent mit bestehenden Migrationen
  (`2026_08_12_130001_add_reminded_status_to_invoices_table.php`), kein
  Konflikt, additive Spalte plausibel.
- `backend/app/Services/InvoicePaymentRecorder.php` — `app/Services/`
  existiert bereits (`PayPalService.php`, `InvoiceNumberGenerator.php`,
  `PayPalWebhookValidator.php`), Namensmuster passt.
- `backend/tests/Feature/Domain/Payment/InvoicePaymentRecorderTest.php`
  — Verzeichnis `backend/tests/Feature/Domain/` konnte nicht verifiziert
  werden (nicht Teil dieser Prüfung, da laut Methodik nur explizit
  referenzierte Pfade geprüft werden); falls `Domain/` als Unterordner
  noch nicht existiert, ist das unproblematisch (neues Verzeichnis).
- `backend/tests/Feature/Api/InvoicePaymentApiTest.php` — analog, neuer
  Pfad, kein Konflikt erkennbar.
- `frontend/src/components/InvoicePaymentDialog.vue` — `frontend/src/components/InvoiceSendDialog.vue`
  existiert als Referenzmuster, kein Namenskonflikt mit
  `PaymentModal.vue` (bestätigt vorhanden unter
  `frontend/src/components/PaymentModal.vue`).

## Empfehlung

Der Change ist inhaltlich weit überwiegend fundiert und die Kernbehauptung
(gebrochenes `paid`/`totalPaid`-Invariant) ist zweifelsfrei bestätigt. Vor
User-Gate 1 sollte der Architekt jedoch drei Punkte nacharbeiten: (1) T03s
Akzeptanzkriterium "23 Bestandstests bleiben unverändert grün" korrigieren
bzw. `PaymentApiTest.php` als zu ändernde Datei aufnehmen (Test `'trainer
can create payment'` braucht `trainer_id`-Setup, sonst bricht `composer
test` nach T03) — das ist ein konkreter, vorab bekannter QA-Fehlschlag,
kein hypothetisches Risiko. (2) Die Zeilenangabe zur vierten
Race-Condition-Stelle korrigieren (`PaymentController::handlePaymentCaptureCompleted()`
statt `PayPalService.php:341-348`) und explizit entscheiden, ob diese
vierte, in derselben Datei liegende Fundstelle in T03 mitgefixt oder
bewusst als Non-Goal ausgewiesen wird. (3) Die "Ist-Zustand"-Beschreibung
zu `PaymentController::index()` korrigieren (keine Kundenfilterung
vorhanden, nicht nur fehlendes Trainer-Scoping) — ändert nichts am Scope
dieses Change, aber die Risikodarstellung sollte korrekt sein.

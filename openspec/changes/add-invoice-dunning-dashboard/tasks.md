# Tasks für add-invoice-dunning-dashboard

Reihenfolge: T01 (Fundament) → T02/T03 (Backend-Kernlogik, T03 kann
parallel zu T02 starten, beide hängen nur von T01 ab) → T04 (Endpunkt,
braucht T02+T03) → T05 (Cron-Ablösung, braucht T03 für die neue
Mail-Klasse) → T06 (Dashboard-Backend, unabhängig von T02-T05, kann
parallel laufen, hängt nur von T01 ab) → T07/T08 (Frontend
Rechnungsliste/-Detail, brauchen T04) → T09 (Frontend Dashboard, braucht
T06) → T10 (Cross-Cutting QA, braucht alle).

## T01: Schema-Fundament — `document_type`, `fee_invoice_id`, Config, `DunningFeeSchedule`

- **Agent:** dev-php
- **Dateien:**
  `backend/database/migrations/2026_08_14_140001_add_document_type_to_invoices_table.php`
  (neu),
  `backend/database/migrations/2026_08_14_140002_add_fee_invoice_id_to_invoice_dunnings_table.php`
  (neu), `backend/config/invoicing.php` (neu),
  `backend/app/Support/DunningFeeSchedule.php` (neu),
  `backend/app/Models/Invoice.php`, `backend/app/Models/InvoiceDunning.php`,
  `backend/tests/Feature/DatabaseStructureTest.php`
- **Abhängigkeiten:** keine
- **Beschreibung:** Siehe `design.md` Decision D1/D4/Migrationen. M1:
  `invoices.document_type` (nullable `string`, additiv, Index) inkl.
  Backfill bestehender Stornorechnungen
  (`WHERE original_invoice_id IS NOT NULL → 'cancellation'`) im selben
  `up()`. M2: `invoice_dunnings.fee_invoice_id` (nullable FK auf
  `invoices`, `nullOnDelete()`, additiv, Index). `config/invoicing.php`
  mit `dunning_fees` (Level 1/2/3, env-überschreibbar
  `DUNNING_FEE_LEVEL_1/2/3`, Standard 5.00/10.00/15.00) und
  `max_dunning_level = 3`. `App\Support\DunningFeeSchedule`:
  `feeForLevel(int $level): ?float`, `nextLevel(?int $currentLevel): ?int`
  (liefert `null`, wenn die nächste Stufe > `max_dunning_level` wäre).
  `Invoice::$fillable` um `document_type` ergänzen;
  `cancellationInvoice(): HasOne` erhält `->where('document_type',
  'cancellation')`; neue `dunningFeeInvoices(): HasMany` mit
  `->where('document_type', 'dunning_fee')`; neue Attribute
  `getNextDunningLevelAttribute(): ?int` und
  `getNextDunningFeeAmountAttribute(): ?float`, beide über
  `DunningFeeSchedule` berechnet aus `dunning_level`.
  `InvoiceDunning::$fillable` um `fee_invoice_id` ergänzen, neue
  `feeInvoice(): BelongsTo`-Relation (`Invoice::class,
  'fee_invoice_id'`).
- **Akzeptanzkriterien:**
  - [x] Beide Migrationen laufen fehlerfrei gegen SQLite
    (`composer test`) — rein additiv, kein treiberspezifischer Pfad
    nötig (siehe `design.md`).
  - [x] Migrationstest mit **vorab per Factory erzeugter
    Stornorechnung** (`original_invoice_id` gesetzt, `document_type`
    noch `null` vor der Migration) bestätigt: nach `migrate` liefert
    diese Rechnung `document_type === 'cancellation'`.
  - [x] `Invoice::cancellationInvoice()` liefert weiterhin ausschließlich
    die echte Stornorechnung, auch wenn zusätzlich ein Datensatz mit
    `original_invoice_id` und `document_type = 'dunning_fee'` für
    dieselbe Original-Rechnung existiert (Regressionstest für Decision
    D1).
  - [x] `DunningFeeSchedule::nextLevel(null) === 1`,
    `nextLevel(3) === null`, `feeForLevel(4) === null`.
  - [x] `DatabaseStructureTest.php` prüft `document_type` auf `invoices`
    und `fee_invoice_id` auf `invoice_dunnings`.
  - [x] `composer stan`/`composer compat-check` grün.

## T02: `App\Services\InvoiceDunningRecorder`

- **Agent:** dev-php
- **Dateien:** `backend/app/Services/InvoiceDunningRecorder.php` (neu),
  `backend/app/Exceptions/InvoiceDunningNotEligibleException.php` (neu),
  `backend/app/Exceptions/InvoiceDunningLevelExceededException.php`
  (neu)
- **Abhängigkeiten:** T01
- **Beschreibung:** Siehe `design.md` Decision D2/D3/D5.
  `trigger(Invoice $invoice): InvoiceDunning`, innerhalb eines äußeren
  `DB::transaction()`: `Invoice::query()->lockForUpdate()->findOrFail()`,
  danach (innerhalb der gesperrten Sektion, siehe Decision D3):
  Eligibility-Prüfung (`document_type === null` und `status` in
  `['sent', 'reminded', 'overdue']`, sonst
  `InvoiceDunningNotEligibleException`), Stufen-Prüfung
  (`DunningFeeSchedule::nextLevel($locked->dunning_level)`, `null` →
  `InvoiceDunningLevelExceededException`). Danach: Gebührendokument
  erzeugen über private `createFeeInvoiceWithRetry()` (Unique-Constraint-
  Retry-Schleife, `self::FEE_INVOICE_MAX_ATTEMPTS = 3`, jeder Versuch
  eigene genestete `DB::transaction()`, 1:1-Muster wie
  `InvoiceController::createCancellationInvoiceWithRetry()`), inkl.
  einer `InvoiceItem`-Zeile ("Mahngebühr Stufe {level} zu Rechnung
  {invoice_number}", `quantity=1`, `unit_price=amount=Gebühr`,
  `tax_rate=0`). Danach `InvoiceDunning::create([...])` mit
  `fee_invoice_id`. Danach `$locked->update(['status' => 'reminded'])`.
  Rückgabe: `$dunning->fresh(['invoice', 'feeInvoice'])`. Beide
  Exceptions analog zu `InvoiceOverpaymentException` gestaltet
  (`\RuntimeException`, öffentliche readonly Properties für Kontext,
  sprechende `parent::__construct()`-Nachricht).
- **Akzeptanzkriterien:**
  - [ ] Neue Pest-Testdatei `backend/tests/Feature/Domain/Invoice/
    InvoiceDunningRecorderTest.php` (`uses()->group('domain',
    'invoice')`, `it()`-Stil gemäß TESTING.md) deckt ab: (a) erste
    Mahnung erzeugt Level 1 + Gebührendokument mit korrektem Betrag +
    Statuswechsel zu `reminded`; (b) zweite Mahnung auf bereits
    gemahnter Rechnung erzeugt Level 2; (c) vierter Trigger-Versuch nach
    Level 3 wirft `InvoiceDunningLevelExceededException`; (d) Trigger auf
    `draft`/`paid`/`cancelled`-Rechnung wirft
    `InvoiceDunningNotEligibleException`; (e) Trigger auf einem
    Gebührendokument selbst (`document_type = 'dunning_fee'`) wirft
    `InvoiceDunningNotEligibleException`; (f) `total_amount` der
    Original-Rechnung bleibt nach dem Trigger unverändert (Kernkriterium
    der bindenden Entscheidung 1).
  - [ ] **DB-kritisch — gegen echtes PostgreSQL getestet:** ein
    Concurrency-Test (zwei nahezu gleichzeitige `trigger()`-Aufrufe für
    dieselbe Rechnung) bestätigt genau einen Übergang auf Level 1, keine
    doppelte Stufe (analog zum PostgreSQL-Concurrency-Test aus
    `add-invoice-payment-entry` T02). Ergebnis in `task-T02.notes.md`
    dokumentiert.
  - [ ] `composer stan`/`composer compat-check` grün.

## T03: Mahn-E-Mail (Event/Listener/Mailable/View)

- **Agent:** dev-php
- **Dateien:** `backend/app/Events/InvoiceDunningTriggered.php` (neu),
  `backend/app/Listeners/SendInvoiceDunningEmail.php` (neu),
  `backend/app/Mail/InvoiceDunningNotice.php` (neu),
  `backend/resources/views/emails/invoice-dunning-notice.blade.php`
  (neu)
- **Abhängigkeiten:** T01 (benötigt `InvoiceDunning`/`feeInvoice`, nicht
  aber den Recorder aus T02 — kann parallel zu T02 entwickelt werden)
- **Beschreibung:** Siehe `design.md` Decision D7. 1:1 nach dem
  `InvoiceWasSent`/`SendInvoiceEmail`/`InvoiceSent`-Muster:
  `InvoiceDunningTriggered` (`Dispatchable`, `public InvoiceDunning
  $dunning`). `SendInvoiceDunningEmail::handle()` lädt
  `$event->dunning->loadMissing(['invoice.customer.user',
  'feeInvoice.items'])` und versendet synchron (`Mail::to(...)
  ->send(...)`, **kein** `ShouldQueue`) `new
  InvoiceDunningNotice($event->dunning)`; `failed()`-Methode loggt
  analog zu `SendInvoiceEmail::failed()`. `InvoiceDunningNotice`:
  `envelope()` identisch zum `Setting`/`Cache::remember(...)`-Muster aus
  `InvoiceSent`, Betreff "Zahlungserinnerung – Mahnung Stufe {level} zu
  Rechnung {invoice_number}"; `content()` rendert
  `emails.invoice-dunning-notice`; `attachments()` rendert **das
  Gebührendokument** (`$this->dunning->feeInvoice`) über
  `app(InvoicePdfRenderer::class)` als PDF-Anhang (identisches
  `Attachment::fromData(...)`-Muster wie `InvoiceSent::attachments()`).
  Die Blade-Vorlage nennt Rechnungsnummer/Fälligkeitsdatum/Restbetrag
  der Original-Rechnung sowie Stufe und Gebührenbetrag im Text, ohne die
  Original-Rechnung erneut anzuhängen (siehe `proposal.md` offene Frage
  3). **Keine** manuelle `Event::listen()`-Registrierung in
  `AppServiceProvider` (automatische Discovery, siehe
  `design.md`/`AppServiceProvider.php:66-74`).
- **Akzeptanzkriterien:**
  - [x] Neuer Test (Teil von `InvoiceDunningApiTest.php` aus T04 oder
    eigene Datei `InvoiceDunningNoticeMailTest.php`) prüft mit
    `Mail::fake()`: `InvoiceDunningTriggered::dispatch($dunning)` löst
    genau eine `InvoiceDunningNotice` an die Kunden-E-Mail-Adresse aus,
    mit dem Gebührendokument als PDF-Anhang.
  - [x] Mail-Inhaltstest (analog `InvoiceCreatedMailBankDetailsTest.php`/
    `InvoiceSentMailBankDetailsTest.php`-Muster): `(new
    InvoiceDunningNotice($dunning))->render()` enthält Stufe,
    Gebührenbetrag und die Rechnungsnummer der Original-Rechnung.
  - [x] `composer stan`/`composer compat-check` grün.

## T04: `InvoiceController::remind()` + Policy + Route + Resource-Erweiterung

- **Agent:** dev-php
- **Dateien:** `backend/app/Http/Controllers/Api/InvoiceController.php`,
  `backend/app/Policies/InvoicePolicy.php`, `backend/routes/api.php`,
  `backend/app/Http/Resources/InvoiceResource.php`,
  `backend/app/Http/Resources/InvoiceDunningResource.php` (neu),
  `backend/tests/Feature/Api/InvoiceDunningApiTest.php` (neu)
- **Abhängigkeiten:** T02, T03
- **Beschreibung:** Siehe `design.md` Decision D3/D6/D7. Neue Route
  `Route::post('/invoices/{invoice}/remind', [InvoiceController::class,
  'remind']);` (neben `finalize`/`cancel`/`send-email`).
  `InvoicePolicy::remind()`: rein rollenbasiert
  (`$user->isAdminOrTrainer()`), analog zu `finalize()`/`send()`.
  `InvoiceController::remind(Invoice $invoice, InvoiceDunningRecorder
  $recorder)`: `$this->authorize('remind', $invoice)`, dann
  `try { $dunning = $recorder->trigger($invoice); } catch
  (InvoiceDunningNotEligibleException|InvoiceDunningLevelExceededException
  $e) { return response()->json(['message' => ...], 422); }`
  (unterschiedliche, sprechende Nachrichten je Exception-Typ), danach
  `try { InvoiceDunningTriggered::dispatch($dunning); } catch
  (\Throwable $e) { logger()->error(...); return response()->json([...],
  502); }`, abschließend `return new
  InvoiceResource($invoice->fresh([...]))` mit erweiterten Eager-Loads.
  `index()`/`show()`/`finalize()`/`cancel()` erweitern ihre bestehenden
  `with([...])`-Aufrufe von `'dunnings'` auf `'dunnings.feeInvoice'`
  (N+1-Vermeidung, siehe Decision D6). `InvoiceResource` ergänzt
  `documentType`, `nextDunningLevel`, `nextDunningFeeAmount`,
  `'dunnings' => InvoiceDunningResource::collection($this->whenLoaded('dunnings'))`.
  Neue `InvoiceDunningResource` exakt wie in `design.md` Decision D6
  spezifiziert.
- **Akzeptanzkriterien:**
  - [ ] `InvoiceDunningApiTest.php` (`uses()->group('api', 'invoice')`,
    `it()`-Stil) deckt ab: Admin/Trainer können mahnen (201/200 +
    Statuswechsel), Kunde kann nicht mahnen (403), Mahnung auf `draft`/
    `paid`/`cancelled` liefert 422, vierte Mahnung nach Level 3 liefert
    422 mit spezifischer Nachricht, `total_amount` der Original-Rechnung
    bleibt unverändert, Response enthält `nextDunningLevel`/
    `nextDunningFeeAmount` korrekt (bzw. `null`, wenn Level 3 erreicht),
    Response enthält die vollständige Mahnhistorie inkl.
    `feeInvoiceNumber`.
  - [ ] `composer qa` grün (einzeln: `composer test`, `composer lint`,
    `composer stan`, `composer compat-check`).

## T05: Alten Cron-Mailer entfernen

- **Agent:** dev-php
- **Dateien:** entfernt: `backend/app/Console/Commands/
  SendPaymentReminders.php`, `backend/app/Mail/PaymentReminder.php`,
  `backend/resources/views/emails/payment-reminder.blade.php`,
  `backend/tests/Feature/PaymentReminderEmailTest.php`; geändert:
  `backend/routes/console.php`,
  `backend/tests/Feature/EmailNotificationTest.php`,
  `backend/app/Console/Commands/SendTestEmail.php`
- **Abhängigkeiten:** T03 (benötigt `InvoiceDunningNotice` für den
  `SendTestEmail.php`-Umbau)
- **Beschreibung:** Siehe `design.md` Decision D8. Die beiden
  `Schedule::command('invoices:send-reminders ...')`-Blöcke in
  `routes/console.php:21-39` entfernen (der `queue:prune-failed`-Block
  bleibt unverändert). `PaymentReminderEmailTest.php` vollständig
  löschen. In `EmailNotificationTest.php`: `describe('Payment Reminder
  Emails', ...)`-Block (Zeile 198-345, 8 Tests) sowie den `use
  App\Mail\PaymentReminder;`-Import entfernen — die übrigen `describe`-
  Blöcke bleiben unverändert. `SendTestEmail.php`: `--type=reminder` →
  `--type=dunning`, `sendReminderEmail()` → `sendDunningEmail()`, nutzt
  eine Beispiel-`InvoiceDunning`-Instanz (per Factory oder erste
  vorhandene Mahnung) mit `new InvoiceDunningNotice($dunning)` statt
  `new PaymentReminder($invoice, 7)`; `match`-Ausdruck und
  `sendAllEmails()` entsprechend anpassen.
- **Akzeptanzkriterien:**
  - [ ] `grep -rn "SendPaymentReminders\|PaymentReminder\|invoices:send-reminders"`
    in `backend/app/` und `backend/routes/` liefert keine Treffer mehr.
  - [ ] `php artisan schedule:list` (oder Äquivalent) zeigt keinen
    `invoices:send-reminders`-Eintrag mehr, `queue:prune-failed` bleibt
    sichtbar.
  - [ ] `composer qa` grün, keine verwaisten Referenzen.

## T06: Dashboard-Backend — überfällige/gemahnte Rechnungen

- **Agent:** dev-php
- **Dateien:** `backend/app/Http/Controllers/Api/DashboardController.php`,
  `backend/tests/Feature/DashboardApiTest.php`
- **Abhängigkeiten:** T01
- **Beschreibung:** `getAdminDashboard()` und `getTrainerDashboard()`
  erhalten je einen neuen Schlüssel `overdueOrRemindedInvoices`:
  `Invoice::query()->with('customer.user')->whereNull('document_type')
  ->whereNotIn('status', ['draft', 'paid', 'cancelled'])
  ->where(fn ($q) => $q->where('status', 'reminded')
  ->orWhere('due_date', '<', now()))->orderBy('due_date')->limit(10)
  ->get()->map(fn (Invoice $i) => [...])` — Felder: `id`,
  `invoiceNumber`, `customerName`, `dueDate` (formatiert `d.m.Y`),
  `status`, `dunningLevel`, `remainingBalance`.
  `whereNull('document_type')` schließt Storno-/Gebührendokumente
  explizit aus (siehe `design.md` Kontext zur Vermeidung von "Rauschen"
  durch kurzlebige Gebührendokumente). Trainer-Variante zusätzlich auf
  `whereIn('customer_id', $assignedCustomers)` gescoped (identisches
  Muster wie die bestehende `invoices`-Stat in `getTrainerDashboard()`).
  `getCustomerDashboard()` bleibt unverändert (kein Widget für Kunden,
  siehe `proposal.md`/`design.md`).
- **Akzeptanzkriterien:**
  - [x] `DashboardApiTest.php` (Bestand erweitert) deckt ab: Admin sieht
    überfällige und gemahnte Rechnungen aller Kunden; Trainer sieht nur
    Rechnungen seiner zugewiesenen Kunden; ein Gebührendokument
    (`document_type = 'dunning_fee'`) taucht **nicht** in der Liste auf,
    auch wenn dessen `due_date` in der Vergangenheit liegt; eine
    bezahlte oder stornierte Rechnung taucht nicht auf; Kunde erhält
    weiterhin keinen entsprechenden Schlüssel im Response.
  - [x] `composer qa` grün.

## T07: `InvoicesView.vue` — "Mahnen"-Button

- **Agent:** dev-typescript
- **Dateien:** `frontend/src/views/invoices/InvoicesView.vue`,
  `frontend/src/views/invoices/InvoicesView.test.ts`
- **Abhängigkeiten:** T04
- **Beschreibung:** Neue Konstante
  `REMINDABLE_STATUSES = ['sent', 'reminded', 'overdue']` (analog zu
  `SENDABLE_STATUSES`). Neuer Helper `canRemind(invoice)`:
  `!authStore.isCustomer && REMINDABLE_STATUSES.includes(invoice.status)
  && !invoice.originalInvoiceId && invoice.nextDunningLevel !== null`.
  Neuer Listenzeilen-Button "Mahnen" mit `@click="remindInvoice(invoice)"`.
  Neuer Handler `remindInvoice(invoice)`:
  `window.confirm(`Mahnung Stufe ${invoice.nextDunningLevel} für
  Rechnung ${invoice.invoiceNumber} auslösen? Es wird eine Mahngebühr
  von ${formatCurrency(invoice.nextDunningFeeAmount)} berechnet und
  automatisch eine E-Mail an den Kunden verschickt.`)`, danach `POST
  /api/v1/invoices/{id}/remind`, `try/catch` mit
  `handleApiError()`/`showSuccess()`, `loadInvoices()` + Detail-Modal
  schließen falls offen (Muster wie `cancelInvoice()`). Neuer
  `@remind="remindInvoice"`-Listener auf `<InvoiceDetailModal>`.
- **Akzeptanzkriterien:**
  - [ ] `InvoicesView.test.ts`: Tests für `canRemind()`-Sichtbarkeit je
    Status/`nextDunningLevel`, für den Bestätigungsdialog-Inhalt
    (Stufe + Gebühr), für `remindInvoice()` (Erfolgsfall: Reload +
    `showSuccess`; 422-Fall: Fehlermeldung via `handleApiError`, kein
    Absturz).
  - [ ] `npm run lint`, `npx vitest run`, `npm run build` grün.

## T08: `InvoiceDetailModal.vue` — "Mahnen"-Button + Mahnhistorie

- **Agent:** dev-typescript
- **Dateien:** `frontend/src/components/InvoiceDetailModal.vue`,
  `frontend/src/components/InvoiceDetailModal.test.ts`
- **Abhängigkeiten:** T04
- **Beschreibung:** `REMINDABLE_STATUSES`/`canRemind(invoice)` lokal
  dupliziert (gleiche Bedingung wie T07, etabliertes
  Nicht-Konsolidierungs-Muster dieser beiden Dateien, siehe
  `design.md`). Neuer Button "Mahnen" (`v-if="canRemind(invoice)"`),
  emittiert `remind`. Neuer `remind`-Eintrag in `defineEmits`. Neuer
  "Mahnungen"-Block (analog zum bestehenden "Zahlungen"-Block, Zeile
  133-148): sichtbar wenn `invoice.dunnings?.length > 0`, listet je
  Mahnung Stufe, Datum (`formatDate(dunning.dunningDate)`), Gebühr
  (`formatCurrency(dunning.feeAmount)`) und die Rechnungsnummer des
  verlinkten Gebührendokuments (`dunning.feeInvoiceNumber`).
- **Akzeptanzkriterien:**
  - [ ] `InvoiceDetailModal.test.ts`: Test für Sichtbarkeit des neuen
    "Mahnen"-Buttons je Status/`nextDunningLevel` (inkl. Abwesenheit bei
    bereits erreichter Stufe 3), Test für den neuen `remind`-Emit, Test
    für die Mahnhistorie-Anzeige (Stufe/Datum/Gebühr/Dokumentnummer),
    Test für die Abwesenheit des Blocks ohne Mahnungen.
  - [ ] `npm run lint`, `npx vitest run`, `npm run build` grün.

## T09: `DashboardView.vue` — Widget "Überfällige & gemahnte Rechnungen"

- **Agent:** dev-typescript
- **Dateien:** `frontend/src/views/DashboardView.vue`,
  `frontend/src/views/DashboardView.test.ts` (neu, falls noch nicht
  vorhanden — Bestand vor Beginn prüfen)
- **Abhängigkeiten:** T06
- **Beschreibung:** Neuer Kartenblock (analog zum bestehenden
  "Ausstehende Stornierungsanfragen"-Block, Zeile 208-272), sichtbar für
  `user?.role === 'trainer' || user?.role === 'admin'`. Zeigt
  `overdueOrRemindedInvoices` (neuer `ref<any[]>([])`, befüllt in
  `loadDashboard()` aus `response.data.overdueOrRemindedInvoices ?? []`):
  je Zeile Rechnungsnummer, Kundenname, Fälligkeitsdatum, Status-Badge
  (Wiederverwendung von `bookingStatusClass`-ähnlichem Mapping oder
  einfacher Text), Mahnstufe (falls vorhanden), Restbetrag. Kein
  Aktions-Button in der Zeile (siehe `design.md` Non-Goals) — stattdessen
  ein `<router-link :to="{ name: 'Invoices' }">Zur Rechnungsübersicht</router-link>`-
  Link am Kartenende. Leerer Zustand: "Keine überfälligen oder gemahnten
  Rechnungen".
- **Akzeptanzkriterien:**
  - [ ] Neuer/erweiterter Test: Karte sichtbar für `admin`/`trainer`,
    nicht für `customer`; rendert Einträge aus
    `overdueOrRemindedInvoices`; zeigt Leer-Zustand bei leerer Liste;
    Link zur Rechnungsliste vorhanden.
  - [ ] `npm run lint`, `npx vitest run`, `npm run build` grün.

## T10: Cross-Cutting QA-Durchlauf

- **Agent:** dev-php
- **Dateien:** keine Code-Änderungen — reiner Verifikationstask
- **Abhängigkeiten:** T01-T09
- **Beschreibung:** Vollständiger Pre-Flight-Check gemäß CLAUDE.md
  Abschnitt 7.1 nach Abschluss aller Tasks: `composer qa`, `npm run
  lint`, `npm run test`, `npm run build`, sowie der MySQL/PostgreSQL-
  Migrations-Testlauf (`docker compose -f docker-compose.yml -f
  docker-compose.mysql.yml up -d && php artisan migrate:fresh &&
  composer test`) inklusive des in T02 geforderten
  PostgreSQL-Concurrency-Tests und eines expliziten Laufs mit
  vorbefüllten Bestandsdaten für den `document_type`-Backfill (T01).
  Ergebnisse in `task-T10.notes.md` dokumentieren.
- **Akzeptanzkriterien:**
  - [ ] Alle vier Backend-Kommandos (`composer test`, `composer lint`,
    `composer stan`, `composer compat-check`) grün, einzeln ausgeführt
    dokumentiert.
  - [ ] `npm run lint`, `npx vitest run`, `npm run build` grün, keine
    neuen Warnings gegenüber dem Bestand.
  - [ ] MySQL/PostgreSQL-Migrationslauf inkl. Concurrency-Test und
    Backfill-Verifikation grün, Ergebnis dokumentiert.

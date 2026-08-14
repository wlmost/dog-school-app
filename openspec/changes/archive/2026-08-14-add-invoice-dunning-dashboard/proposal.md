## Why

Change 4 von 4 im Rechnungsworkflow-Umbau (`add-invoice-status-lifecycle`
→ `add-invoice-send-flow` → `add-invoice-payment-entry` →
**`add-invoice-dunning-dashboard`**). Der Anforderungstext
(`Anforderung-Rechnungsworkflow.txt`, Abschnitte "Überfällig"/"Mahnung")
verlangt: "Die App soll im Dashboard die Rechnungen die überfällig oder
angemahnt sind darstellen. Wenn eine Mahnung ausgelöst werden soll, soll
der Trainer/Admin entsprechend informiert und gefragt werden, ob das
ausgeführt werden soll." sowie "In der Rechnungs-Listenansicht wird die
Rechnung als gemahnt angezeigt, sowie das Datum der Mahnung." Die
bindende User-Entscheidung vom 2026-08-12
(`openspec/triage/20260814-invoice-dunning-dashboard.md`) erweitert dies
um ein **mehrstufiges** Mahnwesen mit Mahngebühren (drei Stufen, feste
Beträge).

**Ist-Zustand — Datenmodell existiert, Trigger-Logik fehlt vollständig:**

- Change 1 (`add-invoice-status-lifecycle`) hat bereits die Tabelle
  `invoice_dunnings` (`invoice_id`, `level`, `dunning_date`,
  `fee_amount`), das `InvoiceDunning`-Model, die `dunnings()`-Relation
  sowie `Invoice::dunning_level`/`reminded_at` und den Status `reminded`
  geschaffen — **ausdrücklich ohne** Trigger-Logik (siehe
  `openspec/changes/archive/2026-08-12-add-invoice-status-lifecycle/design.md`
  Decision D7: "Change 4 legt später fest, wie/wann Datensätze erzeugt
  werden").
- Es gibt **keinen** Endpunkt, keine Route, keine Policy-Methode und
  keinen Service, der einen `InvoiceDunning`-Datensatz tatsächlich
  anlegt (verifiziert per Grep, siehe Triage-Datei Zeilen 58-68). Kein
  Test erzeugt eine Mahnung über einen Endpunkt.
- Es existiert ein **unabhängiger, bereits produktiver automatischer**
  Cron-Mailer (`backend/app/Console/Commands/SendPaymentReminders.php` +
  `backend/routes/console.php:21-39`, täglich per
  `Schedule::command('invoices:send-reminders ...')`), der ohne
  Trainer-/Admin-Bestätigung E-Mails verschickt, **keinen** Status setzt
  und **keine** `InvoiceDunning`-Datensätze anlegt. Das widerspricht dem
  im Anforderungstext geforderten manuellen Bestätigungsschritt. Die
  bindende User-Entscheidung legt fest: **ablösen, kein
  Parallelbetrieb.**
- `frontend/src/views/DashboardView.vue` zeigt aktuell nur eine
  Gesamt-Rechnungszahl-Kachel — keine Liste überfälliger/gemahnter
  Rechnungen, obwohl `DashboardController` (`backend/app/Http/
  Controllers/Api/DashboardController.php:57-68`) bereits ein etabliertes
  Muster für ähnliche Kennzahlen-Listen liefert
  (`pendingDogRegistrations`, `pendingCancellationRequests`).
- `frontend/src/views/invoices/InvoicesView.vue` kennt bereits das
  Bestätigungsdialog-Muster (`window.confirm(...)`, Zeilen 411, 428,
  445) für Löschen/Freigeben/Stornieren — direkt wiederverwendbar für
  den neuen "Mahnen"-Button.
- Eine Mahngebühr, die `total_amount` einer bereits versendeten
  (`sent`/`paid`/`reminded`/`overdue`) Rechnung mutieren würde, widerspräche
  der in Change 1 getroffenen Grundsatzentscheidung "Rechnung ist ab
  Status Offen inhaltlich unveränderlich" — dieselbe Problematik wurde
  dort bereits für Stornierungen gelöst (eigenständiges Korrekturdokument
  statt Mutation). Die bindende User-Entscheidung überträgt dieses
  Muster explizit auf Mahngebühren.

Dieser Change baut die fehlende Trigger-Logik (Service, Endpunkt,
Policy, E-Mail-Versand), ersetzt den bestehenden automatischen Cron-
Mailer durch den geforderten Bestätigungs-Flow und ergänzt das
Dashboard-Widget.

## What Changes

- **Neue Spalte `invoices.document_type`** (nullable, additiv) als
  Diskriminator zwischen regulärer Rechnung, Stornorechnung
  (`'cancellation'`, per Migration aus bestehenden Datensätzen
  zurückgeschrieben) und dem neuen Mahngebühren-Dokument
  (`'dunning_fee'`) — siehe `design.md` Decision D1. Ohne diese Spalte
  könnte `Invoice::cancellationInvoice()` ein Mahngebühren-Dokument
  fälschlich als Stornorechnung ausgeben.
- **Neue Spalte `invoice_dunnings.fee_invoice_id`** (nullable FK auf
  `invoices`, additiv) verknüpft jeden Mahn-Datensatz mit seinem
  eigenständigen Gebührendokument.
- **Neuer Service `App\Services\InvoiceDunningRecorder`** (Vorbild:
  `InvoicePaymentRecorder`) erzeugt atomar (`lockForUpdate()` +
  `DB::transaction()`, Unique-Constraint-Retry analog zu
  `InvoiceController::cancel()`): ein neues Gebührendokument (eigene
  Rechnungsnummer, `original_invoice_id`, `document_type =
  'dunning_fee'`, eine Position "Mahngebühr Stufe X", `tax_rate = 0`),
  einen `InvoiceDunning`-Datensatz (Stufe, Datum, Gebühr,
  `fee_invoice_id`) und den Statuswechsel der Original-Rechnung auf
  `reminded`. Feste Gebühren je Stufe (1/2/3) kommen aus der neuen
  `config/invoicing.php` (env-überschreibbar), nicht frei eingebbar
  (bindende Entscheidung 3). Nach Stufe 3 wirft der Service eine
  domänenspezifische Exception, die der Controller als HTTP 422
  beantwortet.
- **Neuer Endpunkt `POST /invoices/{invoice}/remind`** +
  `InvoicePolicy::remind()` (rollenbasiert: Admin/Trainer).
- **Neue Mahn-E-Mail** (`InvoiceDunningTriggered`-Event,
  `SendInvoiceDunningEmail`-Listener, `InvoiceDunningNotice`-Mailable,
  1:1 nach dem `InvoiceWasSent`-Muster aus `add-invoice-send-flow`):
  wird automatisch beim Auslösen einer Mahnung synchron versendet, mit
  dem Gebührendokument als PDF-Anhang und einem Gebührenhinweis im
  Text. Mail-Fehler werden wie bei `sendEmail()` als HTTP 502 mit
  Fallback-Hinweis zurückgemeldet, ohne die bereits erfasste Mahnstufe
  zurückzunehmen.
- **Breaking: bestehender Cron-Mailer entfällt vollständig.**
  `SendPaymentReminders.php`, `PaymentReminder.php`,
  `emails/payment-reminder.blade.php` sowie die beiden
  `Schedule::command('invoices:send-reminders ...')`-Blöcke in
  `routes/console.php` werden entfernt (kein Parallelbetrieb, bindende
  Entscheidung 2). `SendTestEmail.php`s `--type=reminder`-Option wird zu
  `--type=dunning` und nutzt die neue Mail-Klasse.
- **`InvoiceResource` erweitert** um `documentType`, `nextDunningLevel`,
  `nextDunningFeeAmount` (für den Bestätigungsdialog, ohne zusätzlichen
  Preview-Endpunkt) sowie eine geladene Mahnhistorie (`dunnings`, neue
  `InvoiceDunningResource` mit Stufe/Datum/Gebühr/verlinktem
  Gebührendokument).
- **Neuer "Mahnen"-Button** in `InvoicesView.vue`/`InvoiceDetailModal.vue`
  mit `window.confirm(...)`-Bestätigungsdialog (zeigt Stufe + Gebühr),
  sichtbar für Admin/Trainer bei mahnfähigem Status und solange die
  maximale Stufe nicht erreicht ist. `InvoiceDetailModal.vue` zeigt
  zusätzlich einen neuen "Mahnungen"-Block (analog zum bestehenden
  "Zahlungen"-Block) mit der vollständigen Mahnhistorie.
- **Neues Dashboard-Widget** für Admin (alle Kunden) und Trainer (nur
  eigene zugewiesene Kunden): Liste überfälliger und gemahnter
  Rechnungen, rein informativ mit Link zur Rechnungsliste — kein
  direkter Mahnungs-Trigger im Dashboard selbst (siehe `design.md`
  Non-Goals).

## Capabilities

### New Capabilities

- `invoice-dunning-trigger`: mehrstufiger Mahnungs-Trigger mit festen
  Gebühren, eigenständigem Gebührendokument, automatischem
  E-Mail-Versand, Obergrenze bei Stufe 3, und Ablösung des bestehenden
  automatischen Cron-Mailers.
- `invoice-overdue-dashboard`: Dashboard-Widget für Admin/Trainer mit
  einer Übersicht überfälliger und gemahnter Rechnungen.

### Modified Capabilities

- `invoice-status-lifecycle`: Das Requirement "Mahnstufen-Datenmodell"
  (`openspec/specs/invoice-status-lifecycle/spec.md:116-134`) wird um
  die tatsächliche Trigger-Erzeugung und die Obergrenze bei Stufe 3
  erweitert (bislang nur als Datenmodell-Fähigkeit ohne Trigger-Logik
  beschrieben). Das Requirement "Listen- und Detail-Buttons pro Status"
  (`openspec/specs/invoice-status-lifecycle/spec.md:70-96`) wird um ein
  Szenario für den Status `reminded` (inkl. "Mahnen"-Button bis Stufe 3)
  ergänzt.

## Impact

**Betroffener Bestandscode (Backend):**

- `backend/database/migrations/` — zwei neue, additive Migrationen
  (`document_type` auf `invoices` inkl. Backfill, `fee_invoice_id` auf
  `invoice_dunnings`), DB-unkritisch (siehe `design.md`).
- `backend/config/invoicing.php` — neu.
- `backend/app/Support/DunningFeeSchedule.php` — neu.
- `backend/app/Services/InvoiceDunningRecorder.php` — neu.
- `backend/app/Exceptions/InvoiceDunningNotEligibleException.php`,
  `InvoiceDunningLevelExceededException.php` — neu.
- `backend/app/Events/InvoiceDunningTriggered.php`,
  `backend/app/Listeners/SendInvoiceDunningEmail.php`,
  `backend/app/Mail/InvoiceDunningNotice.php`,
  `backend/resources/views/emails/invoice-dunning-notice.blade.php` —
  neu.
- `backend/app/Models/Invoice.php` — `document_type` in `$fillable`,
  `cancellationInvoice()`/`dunningFeeInvoices()`-Filterung, neue
  Attribute `nextDunningLevel`/`nextDunningFeeAmount`.
- `backend/app/Models/InvoiceDunning.php` — `fee_invoice_id` in
  `$fillable`, neue `feeInvoice(): BelongsTo`-Relation.
- `backend/app/Http/Resources/InvoiceResource.php`,
  `backend/app/Http/Resources/InvoiceDunningResource.php` (neu).
- `backend/app/Http/Controllers/Api/InvoiceController.php` — neue
  `remind()`-Methode, erweiterte Eager-Loads (`dunnings.feeInvoice`).
- `backend/app/Policies/InvoicePolicy.php` — neue `remind()`-Methode.
- `backend/routes/api.php` — neue Route `POST
  /invoices/{invoice}/remind`.
- `backend/app/Http/Controllers/Api/DashboardController.php` — neue
  Kennzahlen-Liste für Admin/Trainer.
- **Entfernt:** `backend/app/Console/Commands/SendPaymentReminders.php`,
  `backend/app/Mail/PaymentReminder.php`,
  `backend/resources/views/emails/payment-reminder.blade.php`, die
  beiden `invoices:send-reminders`-Scheduler-Blöcke in
  `backend/routes/console.php`.
- `backend/app/Console/Commands/SendTestEmail.php` — `--type=reminder`
  → `--type=dunning`, nutzt `InvoiceDunningNotice`.
- Neue Tests: `backend/tests/Feature/Domain/Invoice/
  InvoiceDunningRecorderTest.php`, `backend/tests/Feature/Api/
  InvoiceDunningApiTest.php`, Erweiterung von
  `backend/tests/Feature/DashboardApiTest.php`.
- **Entfernt:** `backend/tests/Feature/PaymentReminderEmailTest.php`
  (vollständig), der `describe('Payment Reminder Emails', ...)`-Block
  in `backend/tests/Feature/EmailNotificationTest.php:199-347` (7
  Tests) inkl. des zugehörigen `use App\Mail\PaymentReminder;`-Imports.
- `backend/tests/Feature/DatabaseStructureTest.php` — neue Assertions
  für `document_type`/`fee_invoice_id`.

**Betroffener Bestandscode (Frontend):**

- `frontend/src/views/invoices/InvoicesView.vue` — neuer
  "Mahnen"-Button, `REMINDABLE_STATUSES`, `remindInvoice()`-Handler.
- `frontend/src/components/InvoiceDetailModal.vue` — neuer
  "Mahnen"-Button/Emit, neuer "Mahnungen"-Historienblock.
- `frontend/src/views/DashboardView.vue` — neuer Kartenblock
  "Überfällige & gemahnte Rechnungen" (nur `trainer`/`admin`).
- Vitest: `InvoicesView.test.ts`, `InvoiceDetailModal.test.ts`,
  `DashboardView.test.ts` (neu, falls noch nicht vorhanden — Bestand
  prüfen) angepasst/erweitert.

**Nicht geändert (Non-Goals, siehe `design.md`):**

- Kein direkter Mahnungs-Trigger im Dashboard-Widget selbst.
- Keine Admin-UI zur Pflege der Mahngebühren-Beträge.
- Kein automatischer, zeitgesteuerter Mahnlauf.
- Keine Korrektur-/Storno-Möglichkeit für bereits erzeugte
  Gebührendokumente.
- Keine Umsatzsteuer-Berechnung auf die Mahngebühr.
- Kein PDF-Layout-Sonderfall für das Gebührendokument.

## Offene Fragen für Skeptiker/User

1. **Umsatzsteuer auf Mahngebühren:** Diese Spezifikation bucht die
   Mahngebühr grundsätzlich mit `tax_rate = 0` (gängige Praxis: echter
   Schadensersatz, nicht umsatzsteuerbar), unabhängig von
   `company_small_business`. Der Anforderungstext trifft dazu keine
   Aussage. Bitte bestätigen oder korrigieren — dies ist keine
   steuerrechtliche Beratung durch dieses Dokument.
2. **Kein Mahnungs-Trigger im Dashboard-Widget:** Das Widget zeigt
   überfällige/gemahnte Rechnungen rein informativ mit Link zur
   Rechnungsliste; der eigentliche "Mahnen"-Button samt
   Bestätigungsdialog existiert ausschließlich in `InvoicesView.vue`/
   `InvoiceDetailModal.vue` (keine Duplikation der Aktions-Logik in
   einer dritten Ansicht). Alternative: Mahnungs-Button direkt in der
   Dashboard-Liste — mehr Komfort, aber zusätzlicher Duplikations-Scope.
   Bitte bestätigen, dass der Umweg über die Rechnungsliste akzeptabel
   ist.
3. **Mahn-E-Mail hängt nur das Gebührendokument an, nicht erneut die
   Original-Rechnung.** Der Kunde hat die Original-Rechnung bereits über
   den bestehenden Sende-Flow (`add-invoice-send-flow`) erhalten. Bitte
   bestätigen, dass ein erneuter Anhang der Original-Rechnung nicht
   nötig ist (Alternative: beide PDFs anhängen, mehr Redundanz/
   Mail-Größe).
4. **Feste Mahngebühren-Beträge:** Diese Spezifikation schlägt 5,00 € /
   10,00 € / 15,00 € für Stufe 1/2/3 als Standardwerte in
   `config/invoicing.php` vor (env-überschreibbar). Der Anforderungstext
   nennt keine konkreten Beträge. Bitte bestätigen oder andere Beträge
   vorgeben.
5. **Mahnfähige Statuswerte:** Diese Spezifikation erlaubt das Auslösen
   einer Mahnung für die Statuswerte `sent`, `reminded` und `overdue`
   (Altlast-Wert, siehe Change 1 Decision D3) — nicht für `draft`,
   `paid`, `cancelled` oder ein bereits existierendes Storno-/
   Gebührendokument selbst. Bitte bestätigen, dass eine bereits
   vollständig bezahlte Rechnung (`remainingBalance === 0`, aber Status
   technisch noch nicht `paid` in einer Zwischenphase) nicht gemahnt
   werden soll — die Service-Ebene prüft dafür ausschließlich den
   Status, nicht zusätzlich den Restbetrag (Statuswechsel zu `paid`
   erfolgt laut `add-invoice-payment-entry` ohnehin automatisch, sobald
   der Restbetrag null erreicht).

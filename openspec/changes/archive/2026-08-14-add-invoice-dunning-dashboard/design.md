## Context

**Bindende User-Entscheidungen (2026-08-14, nicht Teil der Verhandlung
dieses Dokuments):**

1. Mahngebühren werden als **eigenes Dokument** verbucht (analog zur
   Stornorechnung aus `add-invoice-status-lifecycle`), **nicht** als
   Mutation von `total_amount` der bestehenden Rechnung.
2. Der bestehende Cron-Mailer (`backend/app/Console/Commands/
   SendPaymentReminders.php` + Scheduler-Einträge
   `backend/routes/console.php:21-39`) wird **abgeschaltet und entfernt**,
   kein Parallelbetrieb mit dem neuen manuellen Trigger.
3. Genau **3 Mahnstufen** mit **festen Beträgen je Stufe** (im Code/als
   Konfiguration hinterlegt, nicht frei im Dialog eingebbar). Nach Stufe 3
   ist keine weitere App-interne Mahnung mehr möglich.
4. Auslösen einer Mahnung verschickt automatisch eine E-Mail an den
   Kunden mit Gebührenhinweis, analog zum Send-Flow-Muster aus
   `add-invoice-send-flow`.

**Ist-Zustand Backend (verifiziert):**

- `backend/database/migrations/2026_08_12_130002_create_invoice_dunnings_table.php:14-23`
  — Tabelle `invoice_dunnings` existiert bereits: `invoice_id`, `level`
  (`unsignedTinyInteger`), `dunning_date`, `fee_amount` (`decimal(10,2)`,
  Default 0). Reines Datenmodell, keine Trigger-Logik.
- `backend/app/Models/InvoiceDunning.php:1-66` — Model mit `fillable`
  (`invoice_id, level, dunning_date, fee_amount`), Casts, `invoice()`-
  Relation (`BelongsTo`). Keine weiteren Relationen.
- `backend/app/Models/Invoice.php:52-62` — `$fillable` enthält bereits
  `original_invoice_id` (self-referencing FK, additiv aus Change 1 für
  die Stornorechnung), aber **keine** `document_type`-Spalte.
- `backend/app/Models/Invoice.php:108-111` — `dunnings(): HasMany`.
  `backend/app/Models/Invoice.php:116-127` — `originalInvoice(): BelongsTo`
  und `cancellationInvoice(): HasOne`, beide ausschließlich über
  `original_invoice_id` ohne weiteren Diskriminator.
- `backend/app/Models/Invoice.php:166-179` — `getDunningLevelAttribute()`
  (höchste `level` aus `dunnings`), `getRemindedAtAttribute()` (jüngstes
  `dunning_date`). Rein lesend, von keinem Produktivpfad geschrieben.
- `backend/app/Http/Resources/InvoiceResource.php:51-56` — exponiert
  bereits `remindedAt`, `dunningLevel`, `originalInvoiceId`/
  `originalInvoiceNumber`, `cancellationInvoiceId`/
  `cancellationInvoiceNumber`. Keine Felder für die nächste Mahnstufe/
  -gebühr, keine Mahnhistorie (`dunnings`-Array fehlt, im Unterschied zu
  `payments`, das bereits über `PaymentResource::collection(...)`
  exponiert wird).
- `backend/app/Http/Controllers/Api/InvoiceController.php:303-364`
  (`cancel()` + `createCancellationInvoiceWithRetry()`) — das **exakte
  Vorbild** für "eigenes Dokument": neue `Invoice` mit eigener
  `invoice_number` (`InvoiceNumberGenerator::generate()`,
  Unique-Constraint-Retry-Schleife, jeder Versuch eine eigene, genestete
  `DB::transaction()` → `SAVEPOINT`, siehe Docblock-Kommentar Zeile
  278-302 zur PostgreSQL-Transaktionsvergiftung), `original_invoice_id`
  auf die Ursprungsrechnung gesetzt, Status direkt `sent` (kein
  Entwurfs-Zwischenschritt). **Wichtiger Unterschied für Change 4:** die
  Stornorechnung ist die **einzige** Art von Kind-Dokument, die
  `original_invoice_id` heute befüllt — `cancellationInvoice(): HasOne`
  (`Invoice.php:124-127`) geht implizit davon aus, dass es pro
  Original-Rechnung höchstens ein Kind mit `original_invoice_id` gibt.
  Ein zweites Kind-Dokument (die neue Mahngebühr-Rechnung) würde diese
  Annahme brechen (siehe Decision D1).
- `backend/app/Services/InvoicePaymentRecorder.php:57-127` — das
  **Vorbild für den neuen `InvoiceDunningRecorder`-Service**:
  `DB::transaction()` + `Invoice::query()->lockForUpdate()->findOrFail()`,
  eine domänenspezifische Exception (`InvoiceOverpaymentException`,
  `backend/app/Exceptions/InvoiceOverpaymentException.php`), die **innerhalb
  der gesperrten kritischen Sektion** geworfen wird, weil die zugrunde
  liegende Race Condition (zwei nahezu gleichzeitige Aufrufe lesen beide
  denselben, noch nicht aktualisierten Zustand) nur dort zuverlässig zu
  schließen ist — ein reiner Controller-seitiger Vorab-Check (wie bei
  `finalize()`/`send()`) würde dasselbe Zeitfenster offen lassen. Für die
  Mahnstufe gilt strukturell dasselbe Problem: zwei nahezu gleichzeitige
  `remind()`-Aufrufe könnten beide `dunning_level = 1` lesen, bevor einer
  von beiden committet, und so fälschlich zweimal Stufe 2 erzeugen (siehe
  Decision D3).
- `backend/app/Events/InvoiceWasSent.php`,
  `backend/app/Listeners/SendInvoiceEmail.php`,
  `backend/app/Mail/InvoiceSent.php`,
  `backend/resources/views/emails/invoice-sent.blade.php` — das
  **Vorbild für die neue Mahn-E-Mail**: `Dispatchable`-Event mit
  öffentlicher Modell-Property, Listener lädt Relationen und versendet
  synchron (`Mail::to(...)->send(...)`, kein `ShouldQueue`), Mailable holt
  Absenderdaten aus `Setting`/`Cache::remember(...)`, PDF-Anhang über
  `App\Services\InvoicePdfRenderer` (`backend/app/Services/
  InvoicePdfRenderer.php`), im Controller synchron mit `try/catch
  (\Throwable)` → HTTP 502 bei Mail-Fehler (`InvoiceController::
  sendEmail():423-434`).
- `backend/app/Providers/AppServiceProvider.php:66-74` — Event-Listener
  werden **nicht** manuell über `Event::listen()` registriert (Laravels
  automatische Discovery reicht; eine doppelte Registrierung sendete in
  der Vergangenheit E-Mails doppelt, siehe `fix-duplicate-event-listener-
  registration`). Der neue `InvoiceDunningTriggered`/
  `SendInvoiceDunningEmail`-Pfad folgt demselben Muster (keine manuelle
  Registrierung).
- `backend/app/Console/Commands/SendPaymentReminders.php` (94 Zeilen) +
  `backend/app/Mail/PaymentReminder.php` +
  `backend/resources/views/emails/payment-reminder.blade.php` +
  `backend/routes/console.php:21-39` — der abzulösende automatische
  Cron-Mailer. Setzt **keinen** Status, legt **keinen**
  `InvoiceDunning`-Datensatz an, kennt keine Gebühr. Getestet in
  `backend/tests/Feature/PaymentReminderEmailTest.php` (109 Zeilen, testet
  ausschließlich den Mail-Inhalt direkt) und im
  `describe('Payment Reminder Emails', ...)`-Block von
  `backend/tests/Feature/EmailNotificationTest.php:199-347` (7 Tests, die
  den Cron-Befehl end-to-end über `$this->artisan('invoices:send-
  reminders', ...)` treiben).
- `backend/app/Console/Commands/SendTestEmail.php:7,138-165` —
  Entwickler-Werkzeug (`php artisan email:test {recipient} --type=...`),
  nutzt `App\Mail\PaymentReminder` für `--type=reminder`. Kein
  Produktivpfad, aber muss nach Entfernen von `PaymentReminder`
  weiterkompilieren.
- `backend/app/Policies/InvoicePolicy.php:104-144` — etabliertes
  Split-Muster ("Policy = darf diese Rolle grundsätzlich handeln,
  Controller/Service = ist die Aktion im aktuellen Zustand gültig") für
  `finalize()`/`cancel()`/`send()`. `cancel()` ist dabei die Ausnahme: dort
  liegt die Zustandsprüfung **in der Policy**, weil laut Kommentar (Zeile
  110-121) alle Ablehnungsgründe denselben HTTP-Code (403) teilen sollen.
- `backend/app/Http/Controllers/Api/DashboardController.php:104-141,204-221`
  — etabliertes Muster für rollenabhängige Kennzahlen-Listen
  (`pendingDogRegistrations`, `pendingCancellationRequests`, je `->with([...])
  ->limit(5)->get()->map(fn (...) => [...])`, Trainer-Varianten zusätzlich
  auf `Customer::where('trainer_id', $trainerId)` gescoped).
  `getCustomerDashboard()` (Zeile 234-320) hat **kein** Pendant zu
  `pendingCancellationRequests` — Kunden sehen dort ausschließlich eigene
  Buchungen/Sessions, kein Rechnungs-Drilldown.
- `backend/app/Models/Setting.php` + `Setting::get($key, $default)`
  (genutzt für `company_small_business`, `company_email`, `company_name`)
  — bestehender Mechanismus für Admin-konfigurierbare Werte **mit
  UI-Anbindung** (`SettingsController`). Für die drei festen
  Mahngebühren-Beträge gibt es aktuell **keine** entsprechenden
  Setting-Einträge und keine UI-Felder in der Settings-Ansicht — deren
  Ergänzung wäre zusätzlicher Frontend-Scope, den weder der
  Anforderungstext noch die bindende Entscheidung 3 verlangen (siehe
  Decision D4).

**Ist-Zustand Frontend (verifiziert):**

- `frontend/src/views/invoices/InvoicesView.vue:99-107` — Aktionsspalte
  der Tabelle mit `v-if="canX(invoice)"`-Buttons, `window.confirm(...)`-
  Bestätigungsdialoge für `deleteInvoice()` (Zeile 411), `finalizeInvoice()`
  (Zeile 428), `cancelInvoice()` (Zeile 445) — direkt wiederverwendbares
  Muster für den neuen "Mahnen"-Button.
- `frontend/src/views/invoices/InvoicesView.vue:257-275` —
  `SENDABLE_STATUSES`/`CANCELLABLE_STATUSES`/`PAYABLE_STATUSES` als lokale
  `const`-Arrays mit Kommentaren, die explizit auf die jeweils
  gespiegelte Backend-Konstante verweisen (bewusst dupliziert zwischen
  `InvoicesView.vue` und `InvoiceDetailModal.vue`, siehe Change 1
  Non-Goals "keine Konsolidierung").
- `frontend/src/components/InvoiceDetailModal.vue:133-148` — der
  "Zahlungen"-Block als Vorbild für einen neuen "Mahnungen"-Block (Liste
  von Mahn-Datensätzen mit Datum/Stufe/Gebühr/verlinktem Dokument).
- `frontend/src/views/DashboardView.vue:208-272` — der "Ausstehende
  Stornierungsanfragen"-Kartenblock (nur für `trainer`/`admin` sichtbar,
  eigener Ladezustand, eigene leere-Liste-Meldung) als direktes Vorbild
  für das neue Dashboard-Widget.

## Goals / Non-Goals

**Goals:**

- Admin/Trainer können pro Rechnung eine Mahnung auslösen
  (Bestätigungsdialog analog zu Stornieren/Freigeben), die App
  informiert vorab über die anfallende Gebühr.
- Jede ausgelöste Mahnung erzeugt einen `InvoiceDunning`-Datensatz
  (Stufe, Datum, Gebühr) **und** ein eigenständiges Gebührendokument
  (neue `Invoice` mit eigener Rechnungsnummer), ohne `total_amount` der
  Original-Rechnung zu verändern.
- Genau 3 Mahnstufen mit festen, im Code hinterlegten Beträgen; nach
  Stufe 3 ist über die App keine weitere Mahnung mehr möglich.
- Auslösen einer Mahnung verschickt automatisch eine E-Mail an den
  Kunden mit Gebührenhinweis und dem Gebührendokument als PDF-Anhang.
- Der bestehende automatische Cron-Mailer (`SendPaymentReminders`) wird
  vollständig entfernt, kein Parallelbetrieb.
- Dashboard zeigt Admin/Trainer eine Liste überfälliger und gemahnter
  Rechnungen (Trainer nur für eigene Kunden), rein informativ.
- Zwei nahezu gleichzeitige Mahnungs-Trigger für dieselbe Rechnung führen
  zu genau einem Stufen-Fortschritt, keiner doppelten Stufe.

**Non-Goals (bewusst außerhalb dieses Change):**

- Kein direkter "Mahnen"-Button im Dashboard-Widget selbst — das Widget
  ist reine Übersicht mit Link zur Rechnungsliste; der eigentliche
  Trigger bleibt ausschließlich in `InvoicesView.vue`/
  `InvoiceDetailModal.vue` (Duplikation der vollständigen
  Statusaktions-Logik in einer dritten Ansicht wäre YAGNI). Als offene
  Frage in `proposal.md` markiert, falls der User das anders erwartet.
- Keine Admin-UI zur Pflege der Mahngebühren-Beträge (kein neues
  `Setting`-Formularfeld) — Beträge sind Code-/Env-Konfiguration
  (`config/invoicing.php`), siehe Decision D4. Ein späteres
  Setting-basiertes UI ist ein möglicher eigener Folge-Change.
- Keine Umsatzsteuer-Berechnung auf die Mahngebühr — Mahngebühren gelten
  als echter Schadensersatz (Verzugsschaden) und sind nach gängiger
  Praxis nicht umsatzsteuerbar; das Gebührendokument wird mit
  `tax_rate = 0` gebucht, unabhängig von `company_small_business`. Als
  offene Frage in `proposal.md` markiert (keine steuerrechtliche
  Beratung durch dieses Dokument).
- Kein automatischer, zeitgesteuerter Mahnlauf (bindende Entscheidung:
  Überfällig-/Mahnfall-Erkennung bleibt informativ, Auslösung bleibt
  manuell mit Bestätigung).
- Keine Korrektur-/Storno-Möglichkeit für ein bereits erzeugtes
  Gebührendokument oder einen `InvoiceDunning`-Datensatz (kein
  "Mahnung zurücknehmen") — der Anforderungstext sieht das nicht vor;
  analog zu den bereits bestehenden Non-Goals aus
  `add-invoice-payment-entry` für Zahlungskorrekturen.
- Keine Änderung an `Invoice::isOverdue()`/`scopeOverdue()` — die
  bestehende, zur Anzeigezeit berechnete Überfällig-Logik aus Change 1
  bleibt unverändert; das Dashboard-Widget nutzt sie unverändert weiter.
- Kein PDF-Layout-Sonderfall für das Gebührendokument (kein
  "Mahnung"-Badge im PDF) — analog zur bereits getroffenen Entscheidung
  bei der Stornorechnung (`add-invoice-status-lifecycle` Non-Goals). Das
  bestehende `pdf.invoice`-Template referenziert weder `original_invoice_id`
  noch `document_type` (verifiziert per Grep, keine Treffer) und rendert
  das Gebührendokument bereits korrekt als eigenständige Rechnung mit
  einer Position ("Mahngebühr Stufe X").

## Decisions

**D1. Neue Spalte `invoices.document_type` (nullable string) als
Diskriminator, statt `original_invoice_id` unverändert für zwei
unterschiedliche Kind-Dokument-Arten wiederzuverwenden.**

`cancellationInvoice(): HasOne` (`Invoice.php:124-127`) filtert aktuell
ausschließlich über `original_invoice_id` und geht implizit von genau
einem möglichen Kind pro Original-Rechnung aus. Würde das neue
Gebührendokument ebenfalls nur `original_invoice_id` setzen, könnte
diese Relation je nach Datenlage fälschlich ein Gebührendokument statt
der echten Stornorechnung liefern (oder umgekehrt), und
`InvoicePolicy::cancel()`s Prüfung `$invoice->original_invoice_id ===
null` (Zeile 127) würde ein Gebührendokument fälschlich wie eine
Stornorechnung behandeln. Alternative geprüft: komplett neue,
eigenständige Tabelle für Mahngebühren-Dokumente (eigenes
Mini-Rechnungsmodell). Verworfen: das dupliziert die gesamte
Rechnungs-/Positions-/PDF-/Sende-Infrastruktur
(`InvoiceItem`, `InvoicePdfRenderer`, `InvoiceResource`,
`pdf.invoice`-Template) für ein Dokument, das sich fachlich nicht von
einer regulären Rechnung unterscheidet — Verstoß gegen DRY.

Entscheidung: additive Spalte `invoices.document_type` (nullable
`string`, Werte `null` = reguläre Rechnung, `'cancellation'`,
`'dunning_fee'`), rein applikationsseitig validiert (kein DB-Enum, daher
keine treiberspezifische Migration nötig, siehe Migrationen unten).
Bestehende Stornorechnungen (bereits produktiv über `cancel()` erzeugt)
werden in derselben Migration per `WHERE original_invoice_id IS NOT
NULL` auf `'cancellation'` zurückgeschrieben (sicher, weil bislang
`original_invoice_id` ausschließlich von `cancel()` gesetzt wird,
verifiziert per Grep). `cancellationInvoice(): HasOne` erhält
`->where('document_type', 'cancellation')`, eine neue
`dunningFeeInvoices(): HasMany` erhält `->where('document_type',
'dunning_fee')`. `InvoicePolicy::cancel()` bleibt unverändert (prüft
weiterhin nur `original_invoice_id === null`, was Gebührendokumente
weiterhin korrekt vom Stornieren ausschließt, da auch sie
`original_invoice_id` gesetzt haben) — kein Anpassungsbedarf.

**D2. `InvoiceDunningRecorder`-Service kapselt die Erzeugung von
Gebührendokument **und** `InvoiceDunning`-Datensatz **und** den
Statuswechsel atomar, mit demselben Unique-Constraint-Retry wie
`InvoiceController::cancel()`.**

Analog zu `InvoicePaymentRecorder` (Vorbild laut Auftrag) sperrt
`InvoiceDunningRecorder::trigger(Invoice $invoice): InvoiceDunning` die
Rechnung per `Invoice::query()->lockForUpdate()->findOrFail($invoice->id)`
innerhalb eines `DB::transaction()`. Anders als beim reinen
Zahlungs-Statuswechsel muss hier zusätzlich eine neue `invoice_number`
vergeben werden (wie bei `cancel()`) — dafür wird
`createCancellationInvoiceWithRetry()`s Retry-Muster (nested
`DB::transaction()` pro Versuch = `SAVEPOINT`, siehe
`InvoiceController.php:330-364`, Docblock-Begründung Zeile 278-302 zur
PostgreSQL-Transaktionsvergiftung) **in den Service verschoben** statt im
Controller dupliziert: private Methode
`createFeeInvoiceWithRetry(Invoice $locked, int $level, float $fee):
Invoice`, `self::FEE_INVOICE_MAX_ATTEMPTS = 3` (identischer Wert wie
`InvoiceController::CANCEL_MAX_ATTEMPTS`). Der Service ist damit die
einzige Stelle, die "neues Kind-Dokument mit garantiert eindeutiger
Nummer erzeugen" beherrscht — `InvoiceController::cancel()` bleibt
unverändert (kein Refactoring auf einen gemeinsamen Helper, um den Diff
dieses ohnehin großen Change nicht zusätzlich zu vergrößern; als
Folge-Empfehlung im Risiko-Abschnitt vermerkt).

**D3. Domänen-Exceptions statt Controller-seitiger Vorab-Prüfung für
Status-/Stufen-Zulässigkeit — Zustandsprüfung lebt **innerhalb** der
gesperrten kritischen Sektion.**

Im Unterschied zu `finalize()`/`send()` (Zustandsprüfung im Controller
**vor** der eigentlichen Mutation, siehe `InvoicePolicy`-Docblocks) kann
die Mahnstufen-Prüfung nicht rein im Controller vorab erfolgen: zwei
nahezu gleichzeitige `remind()`-Aufrufe für dieselbe Rechnung könnten
beide denselben `dunning_level` lesen, bevor einer von beiden committet
— identisches Race-Condition-Muster wie bei `InvoiceOverpaymentException`
(`InvoicePaymentRecorder.php:46-55`, Docblock-Begründung). Entscheidung:
zwei neue Exceptions, geworfen von `InvoiceDunningRecorder::trigger()`
**nach** dem Lock, aber **vor** jeder Schreiboperation:

- `App\Exceptions\InvoiceDunningNotEligibleException` — Rechnung ist
  selbst ein Storno-/Gebührendokument (`document_type !== null`) oder
  hat einen nicht-mahnfähigen Status (`draft`, `paid`, `cancelled`).
- `App\Exceptions\InvoiceDunningLevelExceededException` — die nächste
  Stufe würde 3 überschreiten (`DunningFeeSchedule::nextLevel(...) ===
  null`).

`InvoiceController::remind()` fängt beide und antwortet mit HTTP 422 und
sprechender Nachricht — derselbe Controller-seitige
Exception-zu-422-Übersetzungs-Stil wie
`PaymentController::store()`s Umgang mit `InvoiceOverpaymentException`.
`InvoicePolicy::remind()` bleibt bewusst rein rollenbasiert (nur
Admin/Trainer), analog zu `finalize()`/`send()`.

**D4. Feste Mahngebühren als neue `config/invoicing.php`
(env-überschreibbar), nicht als `Setting`-Model-Einträge.**

Bindende Entscheidung 3 erlaubt ausdrücklich beide Varianten ("im Code
oder als Setting hinterlegt"). Das bestehende `Setting`-Modell ist in
diesem Projekt durchgängig an eine Admin-UI gekoppelt
(`SettingsController` + zugehörige Frontend-Formulare) — drei neue
Settings ohne zugehörige UI-Felder wären entweder unsichtbarer toter
Code (nur per Tinker/DB änderbar) oder würden zusätzlichen,
nicht angeforderten Frontend-Scope in der Settings-Ansicht erfordern.
Da die bindende Entscheidung ausdrücklich **keine** freie Eingabe im
Dialog verlangt (nur "fix"), reicht eine Konfigurationsdatei:

```php
// backend/config/invoicing.php
return [
    'dunning_fees' => [
        1 => (float) env('DUNNING_FEE_LEVEL_1', 5.00),
        2 => (float) env('DUNNING_FEE_LEVEL_2', 10.00),
        3 => (float) env('DUNNING_FEE_LEVEL_3', 15.00),
    ],
    'max_dunning_level' => 3,
];
```

Neue Support-Klasse `App\Support\DunningFeeSchedule` (statische Methoden
`feeForLevel(int $level): ?float`, `nextLevel(?int $currentLevel): ?int`)
kapselt den Zugriff als einzige Quelle der Wahrheit — genutzt vom
Service (Betrag/Obergrenze), vom `Invoice`-Model (neue Attribute
`getNextDunningLevelAttribute()`/`getNextDunningFeeAmountAttribute()`,
analog zu `getDunningLevelAttribute()`) und damit transitiv von
`InvoiceResource` (siehe D6) sowie vom Frontend-Bestätigungsdialog (der
den Betrag anzeigt, aber nicht editierbar macht — bindende Entscheidung
3). Env-Overrides sind auf Shared Hosting über die dortige
`.env`-Datei/das Hoster-Panel setzbar (CLAUDE.md Abschnitt 6,
"Konfiguration ... niemals hardcoded").

**D5. Gebührendokument: eigene Rechnungsnummer, `status = 'sent'`,
`tax_rate = 0`, ein `InvoiceItem`.**

Analog zu `cancel()`s Entscheidung D5 (Change 1: kein separates
Präfix, gleicher Nummernkreis über `InvoiceNumberGenerator`) erhält das
Gebührendokument die nächste reguläre Rechnungsnummer. Es wird direkt
mit Status `sent` angelegt (kein Entwurf, analog zur Stornorechnung —
ein ausgelöster Mahnungs-Trigger ist ein abgeschlossener Vorgang) und
referenziert die Original-Rechnung über `original_invoice_id` +
`document_type = 'dunning_fee'`. Es erhält genau eine `InvoiceItem`-Zeile
("Mahngebühr Stufe {level} zu Rechnung {invoice_number}", `quantity = 1`,
`unit_price = amount = Gebühr aus DunningFeeSchedule`, `tax_rate = 0`,
siehe Non-Goals zur Umsatzsteuer-Frage). `total_amount` wird direkt aus
der Gebühr gesetzt (kein zusätzlicher Berechnungsschritt nötig, da nur
eine Position).

**D6. `InvoiceResource` erhält `documentType`, `nextDunningLevel`,
`nextDunningFeeAmount` sowie eine geladene `dunnings`-Liste über eine
neue `InvoiceDunningResource`.**

Ohne diese Felder könnte weder der Bestätigungsdialog (braucht Stufe +
Betrag **vor** dem Klick, ohne zusätzlichen Preview-Endpunkt — YAGNI) noch
die Detailansicht (Mahnhistorie mit Verweis auf das jeweilige
Gebührendokument) korrekt rendern. `InvoiceDunningResource` folgt dem
etablierten Muster von `PaymentResource`:

```php
[
    'id' => $this->id,
    'level' => $this->level,
    'dunningDate' => $this->dunning_date?->toDateString(),
    'feeAmount' => (float) $this->fee_amount,
    'feeInvoiceId' => $this->fee_invoice_id,
    'feeInvoiceNumber' => $this->whenLoaded('feeInvoice', fn () => $this->feeInvoice?->invoice_number),
]
```

`InvoiceController::index()`/`show()`/`finalize()`/`cancel()`/`remind()`
erweitern ihre bestehenden Eager-Loads um `'dunnings.feeInvoice'` (statt
nur `'dunnings'`), damit `feeInvoiceNumber` nicht pro Zeile eine
zusätzliche Query auslöst (N+1-Vermeidung, konsistent mit dem
bestehenden `with([...])`-Stil in `index()`/`show()`).

**D7. Mahn-E-Mail als eigenständiges Event/Listener/Mailable-Trio,
1:1 nach dem `InvoiceWasSent`-Muster, mit dem Gebührendokument (nicht der
Original-Rechnung) als PDF-Anhang.**

`InvoiceDunningTriggered` (Event, `public InvoiceDunning $dunning`) →
`SendInvoiceDunningEmail` (Listener, synchron, kein `ShouldQueue`,
identische Begründung wie `SendInvoiceEmail` — seltene,
bewusste Einzelaktion mit wartendem Admin/Trainer, siehe
`add-invoice-send-flow` Decision D4) → `InvoiceDunningNotice` (Mailable,
Betreff "Zahlungserinnerung – Mahnung Stufe {level} zu Rechnung
{invoice_number}", `envelope()`/`content()` identisch zum
`Setting`-basierten Absender-Muster aus `InvoiceSent`, `attachments()`
rendert **das Gebührendokument** — nicht die Original-Rechnung — über
das bereits vorhandene `InvoicePdfRenderer` als PDF-Anhang). Die
Mail-Vorlage (`emails/invoice-dunning-notice.blade.php`) nennt
Rechnungsnummer, Fälligkeitsdatum und Restbetrag der Original-Rechnung
im Text (informativ, ohne deren PDF erneut anzuhängen — Alternative
"beide PDFs anhängen" wurde geprüft und als unnötige Redundanz verworfen,
der Kunde hat die Original-Rechnung bereits über den Sende-Flow aus
`add-invoice-send-flow` erhalten; als offene Frage in `proposal.md`
markiert). `InvoiceController::remind()` dispatcht das Event **nach**
erfolgreichem `InvoiceDunningRecorder::trigger()`-Aufruf, außerhalb von
dessen Transaktion, mit identischem `try/catch (\Throwable)` → HTTP 502
("Mahnung erfasst, aber E-Mail-Versand fehlgeschlagen")-Muster wie
`sendEmail()` — die Datenmutation (Gebührendokument + Statuswechsel)
bleibt damit auch bei einem SMTP-Fehler bestehen (bewusst, siehe Risiko-
Abschnitt: ein Rollback der bereits vergebenen Rechnungsnummer bei einem
reinen Mail-Fehler wäre inkonsistent mit dem etablierten Verhalten von
`sendEmail()`, das ebenfalls keine Datenänderung zurücknimmt).

**D8. Vollständige Entfernung von `SendPaymentReminders`/
`PaymentReminder`, kein Umwidmen als "Vor-Erinnerung".**

Die Triage nannte drei Optionen (ablösen, umwidmen, parallel behalten).
Die bindende User-Entscheidung 2 wählt explizit "ablösen, kein
Parallelbetrieb". Entfernt werden: `SendPaymentReminders.php` (Command),
`PaymentReminder.php` (Mail), `emails/payment-reminder.blade.php`
(View), die beiden `Schedule::command('invoices:send-reminders ...')`-
Blöcke in `routes/console.php:21-39` (der dritte Block,
`queue:prune-failed`, bleibt unverändert bestehen — unabhängige
Wartungsaufgabe), `tests/Feature/PaymentReminderEmailTest.php`
(vollständig, testet ausschließlich die entfernte Mail-Klasse), sowie
der `describe('Payment Reminder Emails', ...)`-Block in
`tests/Feature/EmailNotificationTest.php:199-347` (7 Tests, testen
ausschließlich den entfernten Command). `SendTestEmail.php`s
`--type=reminder`-Zweig wird auf die neue `InvoiceDunningNotice`
umgestellt (`--type=dunning`, da die fachliche Bedeutung sich
grundlegend ändert — von "unverbindliche Erinnerung" zu "offizielle,
gebührenpflichtige Mahnstufe" — ein unveränderter Options-Name wäre
irreführend). Da `email:test` ein reines Entwickler-Werkzeug ohne
Produktivpfad ist (kein API-Endpunkt, keine UI), ist die Options-
Umbenennung risikolos.

## Migrationen (DB-kritisch — MySQL/PostgreSQL/SQLite-Kompatibilität geprüft)

Beide Migrationen sind rein additiv (neue nullable Spalte bzw. neue
nullable FK-Spalte auf einer bestehenden Tabelle) und benötigen — anders
als Change 1s Enum-Erweiterung (M1 dort) — **keinen**
treiberspezifischen Pfad: Laravels Schema-Builder erzeugt
`ADD COLUMN ... NULL` bzw. `ADD COLUMN ... NULL, ADD FOREIGN KEY ...` auf
MySQL, PostgreSQL und SQLite gleichermaßen ohne Sonderbehandlung
(anders als eine nachträgliche Spalten-**Änderung** oder eine
DB-native Enum-Wertliste).

- **M1 — `..._add_document_type_to_invoices_table.php`**
  `$table->string('document_type')->nullable()->after('original_invoice_id')`
  + Index. Im selben `up()` ein einmaliger Backfill
  (`DB::table('invoices')->whereNotNull('original_invoice_id')->update(['document_type' => 'cancellation'])`)
  für bereits produktiv existierende Stornorechnungen — sicher, weil
  `original_invoice_id` bislang ausschließlich von `cancel()` gesetzt
  wird (verifiziert per Grep, keine weiteren Schreibstellen).
- **M2 — `..._add_fee_invoice_id_to_invoice_dunnings_table.php`**
  `$table->foreignId('fee_invoice_id')->nullable()
  ->after('fee_amount')->constrained('invoices')->nullOnDelete()` + Index.
  Kein Backfill nötig (Tabelle enthält bislang nur Test-Fixtures, keine
  Produktivdaten, siehe Triage "kein Test erzeugt eine Mahnung über einen
  Endpunkt").

**Migrations-Reihenfolge:** M1 → M2 (M2 hat keine Abhängigkeit zu M1,
Reihenfolge ist aber sprechender, wenn `document_type` zuerst existiert).

## Ausblick auf Folge-Changes (nicht Teil dieses Change)

- Empfohlener, unabhängiger Folge-Change: `InvoiceController::cancel()`
  auf denselben in `InvoiceDunningRecorder` gekapselten
  Retry-Helper umstellen (aktuell zwei strukturell identische
  Implementierungen des Unique-Constraint-Retry-Musters, siehe Decision
  D2, Risiko-Abschnitt) — analog zur bereits offen dokumentierten
  Empfehlung aus `add-invoice-payment-entry` für
  `PayPalService::captureOrder()`.
- Möglicher Folge-Change: Admin-UI zur Pflege der Mahngebühren-Beträge
  über `Setting`, falls sich im Betrieb zeigt, dass Redeploys für
  Betragsänderungen zu unflexibel sind (siehe Decision D4).

## Risks / Trade-offs

- **Zwei strukturell ähnliche Unique-Constraint-Retry-Implementierungen**
  (`InvoiceController::cancel()` und `InvoiceDunningRecorder`) statt
  einer gemeinsamen Utility-Funktion — bewusst in Kauf genommen, um den
  Diff dieses bereits großen Change nicht zusätzlich um ein
  Controller-Refactoring zu vergrößern (siehe Decision D2, Ausblick).
- **`document_type`-Backfill-Migration** ändert bestehende Produktivdaten
  (sofern bereits Stornorechnungen erzeugt wurden). Risiko gering, da die
  Migration ausschließlich additiv-lesend über eine bereits eindeutige
  Bedingung (`original_invoice_id IS NOT NULL`) rückschreibt, keine
  Löschung/Umbenennung — aber die MySQL/PostgreSQL-Matrix (CLAUDE.md
  Abschnitt 7.1) muss diesen Backfill-Pfad explizit gegen eine
  Fixture mit mindestens einer bestehenden Stornorechnung testen (siehe
  `tasks.md` T01 Akzeptanzkriterien).
- **Kein Rollback der Datenmutation bei fehlgeschlagenem Mail-Versand**
  (Decision D7) — konsistent mit `sendEmail()`s etabliertem Verhalten,
  aber bedeutet: eine Mahnstufe kann "erfasst, aber nicht per Mail
  bestätigt" enden. Der 502-Response macht das für den auslösenden
  Admin/Trainer transparent (analog zu `sendEmail()`s Fallback-Hinweis),
  ein manueller PDF-Download des Gebührendokuments ist über die normale
  Rechnungsliste weiterhin möglich (das Dokument ist eine reguläre
  `Invoice`).
- **Steuerliche Behandlung der Mahngebühr (`tax_rate = 0`) ist eine
  fachliche Annahme dieses Dokuments, keine steuerrechtliche Prüfung** —
  explizit als offene Frage an User/Skeptiker markiert (siehe `proposal.md`).
- **Dashboard-Widget ohne direkten Trigger** könnte vom User als zu
  unpraktisch empfunden werden (zusätzlicher Klick zur Rechnungsliste
  nötig) — bewusste Scope-Begrenzung (siehe Non-Goals), als offene Frage
  markiert.

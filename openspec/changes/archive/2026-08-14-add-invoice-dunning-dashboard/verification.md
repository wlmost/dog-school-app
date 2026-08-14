# Verification: add-invoice-dunning-dashboard

**Gesamtstatus:** ok

`openspec validate add-invoice-dunning-dashboard` → "Change 'add-invoice-dunning-dashboard' is valid" (strukturell ok). Alle konkreten Codebasis-Behauptungen in `proposal.md`/`design.md`/`tasks.md` wurden gegen `main` geprüft. Bis auf zwei kleine Zeilen-/Zählungenauigkeiten (siehe "Widerlegt") und eine wörtliche Ungenauigkeit (`window.confirm` vs. `confirm`) sind alle geprüften Kernannahmen bestätigt. Keine der Abweichungen ist für die Implementierung blockierend — sie betreffen ausschließlich Beleg-Präzision, nicht die fachliche/technische Korrektheit der Entscheidungen.

## Bestätigt

### Datenmodell / Ausgangslage (Change 1)
- `design.md` Z.22-25: `invoice_dunnings`-Tabelle mit `invoice_id`, `level` (unsignedTinyInteger), `dunning_date`, `fee_amount` (decimal 10,2, Default 0) → bestätigt in `backend/database/migrations/2026_08_12_130002_create_invoice_dunnings_table.php:14-23`.
- `design.md` Z.26-28: `InvoiceDunning`-Model mit `$fillable` (`invoice_id, level, dunning_date, fee_amount`), Casts, `invoice(): BelongsTo`, keine weiteren Relationen → bestätigt in `backend/app/Models/InvoiceDunning.php:1-66` (Datei hat 67 Zeilen inkl. schließender Klammer).
- `design.md` Z.29-31: `Invoice::$fillable` enthält `original_invoice_id`, aber kein `document_type` → bestätigt in `backend/app/Models/Invoice.php:52-62`.
- `design.md` Z.32-35: `dunnings(): HasMany` bei `Invoice.php:108-111`, `originalInvoice(): BelongsTo`/`cancellationInvoice(): HasOne` bei `Invoice.php:116-127`, `cancellationInvoice()` filtert ausschließlich über `original_invoice_id` ohne weiteren Diskriminator → exakt bestätigt (`cancellationInvoice()` bei Zeile 124-127: `return $this->hasOne(self::class, 'original_invoice_id');`).
- `design.md` Z.36-38: `getDunningLevelAttribute()`/`getRemindedAtAttribute()`, rein lesend → bestätigt in `Invoice.php:166-179`.
- Proposal.md Z.29-30 / Triage-Datei: "keinen Endpunkt, keine Route, keine Policy-Methode und keinen Service, der einen `InvoiceDunning`-Datensatz tatsächlich anlegt" → bestätigt: `grep -rn "InvoiceDunning::create" backend/app` liefert keine Treffer; `backend/routes/api.php:182-187` kennt nur `finalize`, `cancel`, `send-email`, `overdue`, kein `remind`.

### Vorbild-Muster (InvoicePaymentRecorder, cancel(), Policy, E-Mail-Trio)
- `design.md` Z.61-64: `InvoicePaymentRecorder.php:57-127` als Vorbild (`DB::transaction()` + `lockForUpdate()->findOrFail()`, `InvoiceOverpaymentException`) → bestätigt, Klassenkörper exakt Zeile 57-127.
- `design.md` Z.46-58 / D2: `InvoiceController::cancel()` (Z.303-328) + `createCancellationInvoiceWithRetry()` (Z.336-364), Docblock Z.278-302 zur PostgreSQL-Transaktionsvergiftung → bestätigt exakt in `backend/app/Http/Controllers/Api/InvoiceController.php:278-364`.
- D1: `InvoicePolicy::cancel()`s Prüfung `$invoice->original_invoice_id === null` bei Zeile 127 → bestätigt exakt: `backend/app/Policies/InvoicePolicy.php:127`.
- `design.md` Z.111-116: etabliertes Split-Muster (Policy = Rolle, Controller/Service = Zustand) für `finalize()`/`cancel()`/`send()`, `cancel()` als Ausnahme mit Zustandsprüfung in der Policy → bestätigt in `InvoicePolicy.php:104-144` (Docblocks exakt wie beschrieben).
- `design.md` Z.75-86 / D7: `InvoiceWasSent`/`SendInvoiceEmail`/`InvoiceSent` als 1:1-Vorbild (Dispatchable-Event mit öffentlicher Modell-Property, Listener lädt Relationen und versendet synchron ohne `ShouldQueue`, Mailable holt Absender aus `Setting`/`Cache::remember()`, PDF via `InvoicePdfRenderer`) → bestätigt in `backend/app/Events/InvoiceWasSent.php`, `backend/app/Listeners/SendInvoiceEmail.php`, `backend/app/Mail/InvoiceSent.php` (keine `ShouldQueue`-Implementierung, `Mail::to(...)->send(...)` synchron).
- `design.md` Z.85-86 / `sendEmail():423-434` → bestätigt: `try`-Block ab Zeile 423, `catch (\Throwable $e)` mit HTTP-502-Response bis Zeile 434 in `InvoiceController.php`.
- `design.md` Z.87-93 / `AppServiceProvider.php:66-74`: keine manuelle `Event::listen()`-Registrierung, Kommentar zu doppelten E-Mails → bestätigt wortgleich in `backend/app/Providers/AppServiceProvider.php:66-74`.

### Cron-Mailer (abzulösen)
- `design.md` Z.94-96 / proposal.md Z.31-34: `SendPaymentReminders.php` (94 Zeilen) → bestätigt (`wc -l` = 94). Datei setzt keinen Status, legt keinen `InvoiceDunning`-Datensatz an, kennt keine Gebühr → bestätigt per Lesen der Datei.
- `design.md` Z.97 / proposal.md Z.33-34: `routes/console.php:21-39` — zwei `Schedule::command('invoices:send-reminders ...')`-Blöcke → bestätigt exakt (Block 1: Z.21-30, Block 2: Z.32-39), dritter Block `queue:prune-failed` bei Z.42-44 bleibt unberührt, wie in D8 beschrieben.
- `design.md` Z.106-110 / `SendTestEmail.php:7,138-165`: `use App\Mail\PaymentReminder;` bei Zeile 7, `sendReminderEmail()` bei Zeile 138-165, nutzt `new PaymentReminder($invoice, 7)` → bestätigt exakt.
- `design.md` Z.99-101: `PaymentReminderEmailTest.php` (109 Zeilen) → bestätigt (`wc -l` = 109).

### DunningFeeSchedule / config / Setting
- D4: `Setting`-Modell ist an `SettingsController` + Frontend-UI gekoppelt (`SettingsController`, `SettingsView.vue`) → bestätigt: `backend/app/Http/Controllers/Api/SettingsController.php` und `backend/app/Http/Controllers/SettingsController.php` sowie `frontend/src/views/SettingsView.vue` existieren, `Setting::get()` bestätigt in `backend/app/Models/Setting.php:62-72`.
- D5: `InvoiceItem` trägt `tax_rate` (nicht `Invoice` selbst) → bestätigt in `backend/app/Models/InvoiceItem.php` (`$fillable` enthält `tax_rate`, Cast `'tax_rate' => 'float'`), konsistent mit `cancel()`s Item-Erzeugung (`InvoiceController.php:315`, `'tax_rate' => $item->tax_rate`).
- Migrationen: keine vorhandene Migration setzt bereits `document_type` oder `fee_invoice_id` → bestätigt, `grep -rln "document_type\|fee_invoice_id" backend/database/migrations/` liefert keine Treffer. Keine Namenskollision mit `2026_08_14_140001…`/`…140002…` (kein existierendes File mit diesem Präfix).
- Backfill-Sicherheit: `original_invoice_id` wird im gesamten `backend/app/`-Baum ausschließlich in `InvoiceController.php:346` (`createCancellationInvoiceWithRetry()`) geschrieben → bestätigt, keine weitere Schreibstelle gefunden.

### Resource / Frontend-Vorbilder
- D6 / `design.md` Z.39-45: `InvoiceResource.php:51-56` exponiert bereits `remindedAt`, `dunningLevel`, `originalInvoiceId`/`originalInvoiceNumber`, `cancellationInvoiceId`/`cancellationInvoiceNumber`, kein `dunnings`-Array (im Unterschied zu `payments` bei Zeile 63) → bestätigt exakt Zeile für Zeile.
- D6: `InvoiceDunningResource` soll dem "etablierten Muster von `PaymentResource`" folgen → `PaymentResource.php` bestätigt einfaches flaches Array-Mapping mit `whenLoaded()`, strukturell übertragbar.
- `design.md` Z.137-141: `InvoicesView.vue:99-107` Aktionsspalte mit `v-if="canX(invoice)"`-Buttons → bestätigt exakt (Zeilen 99-107 zeigen PDF/Bearbeiten/Löschen/Freigeben/Senden/Zahlung erfassen/Stornieren-Buttons mit `v-if`).
- `design.md` Z.142-147: `SENDABLE_STATUSES`/`CANCELLABLE_STATUSES`/`PAYABLE_STATUSES` als lokale, kommentierte `const`-Arrays → bestätigt in `InvoicesView.vue:257,268,275` mit Kommentaren, die auf die Backend-Konstanten verweisen. `InvoiceController.php:56`: `SENDABLE_STATUSES = ['sent', 'reminded', 'overdue']` → identisch zur Frontend-Konstante.
- `design.md` Z.148-150 / `InvoiceDetailModal.vue:133-148` "Zahlungen"-Block → bestätigt exakt (Zeilen 133-148 zeigen `v-if="invoice.payments && invoice.payments.length > 0"`-Block mit Überschrift "Zahlungen").
- `design.md` Z.151-154 / `DashboardView.vue:208-272` "Ausstehende Stornierungsanfragen"-Block, sichtbar für `trainer`/`admin`, eigener Ladezustand, eigene Leer-Meldung → bestätigt exakt (Zeile 209: `v-if="user?.role === 'trainer' || user?.role === 'admin'"`, Zeile 224: `v-if="loading"`, Zeile 233-237: Leer-Zustand "Keine ausstehenden Stornierungsanfragen").
- `design.md` Non-Goals Z.210-213: `pdf.invoice`-Template referenziert weder `original_invoice_id` noch `document_type` → bestätigt, `grep` in `backend/resources/views/pdf/invoice.blade.php` liefert keine Treffer.
- Proposal.md Z.201-203 / T09: `DashboardView.test.ts` existiert noch nicht → bestätigt, `find` liefert nur `InvoicesView.test.ts` und `InvoiceDetailModal.test.ts`, kein `DashboardView.test.ts`.

### Change-1-Herleitung (D7 dort)
- `design.md` Z.19-26 / proposal.md Z.22-26: archivierte `add-invoice-status-lifecycle/design.md` Decision D7 sagt sinngemäß "Change 4 legt später fest, wie/wann Datensätze erzeugt werden" → bestätigt wortgleich in `openspec/changes/archive/2026-08-12-add-invoice-status-lifecycle/design.md:269-270`.
- `tasks.md` T02 Akzeptanzkriterium: PostgreSQL-Concurrency-Test "analog zum PostgreSQL-Concurrency-Test aus `add-invoice-payment-entry` T02" → bestätigt, `openspec/changes/archive/2026-08-13-add-invoice-payment-entry/task-T02.notes.md` enthält Abschnitt "PostgreSQL-Concurrency-Verifikation" (Zeile 143).

### DashboardController
- `design.md` Z.117-124: `getCustomerDashboard()` (Zeile 234-320) hat kein Pendant zu `pendingCancellationRequests` → bestätigt: `getCustomerDashboard()` beginnt exakt bei Zeile 234, endet bei Zeile 320 (schließende Klammer vor Klassenende), enthält keine Rechnungs-Drilldown-Liste.
- `design.md` Z.117-121: Trainer-Varianten zusätzlich auf `Customer::where('trainer_id', $trainerId)` gescoped → bestätigt in `DashboardController.php:151` (`$assignedCustomers = Customer::where('trainer_id', $trainerId)->pluck('id');`).

## Widerlegt

- `design.md` Z.117-118: "`DashboardController.php:57-68,148-168` — etabliertes Muster für rollenabhängige Kennzahlen-Listen (`pendingDogRegistrations`, `pendingCancellationRequests`, je `->with([...])->limit(5)->get()->map(fn (...) => [...])`)" → **Zeilenreferenz zeigt nicht auf dieses Muster.** `DashboardController.php:57-68` ist tatsächlich der `$stats`-Zählungs-Block von `getAdminDashboard()` (reine `::count()`-Aufrufe, u.a. `'pendingCancellationRequests' => Booking::where(...)->count()` als reine Zahl, kein `with()/limit()/get()/map()`). Ebenso ist `148-168` der `$stats`-Zählungs-Block von `getTrainerDashboard()` (nur die Zeile 151 `Customer::where('trainer_id', $trainerId)->pluck('id')` stützt die zweite Teilbehauptung zum Trainer-Scoping). Das tatsächliche `with([...])->limit(5)->get()->map(fn (...) => [...])`-Muster für `pendingDogRegistrations`/`pendingCancellationRequests` befindet sich in `DashboardController.php:104-141` (Admin) bzw. `204-221` (Trainer). Für die T06-Implementierung (dev-php) besteht Risiko, dass die falsche Codestelle als Vorlage kopiert wird, wenn nur der Zeilenverweis statt des tatsächlichen Musters gelesen wird — funktional ist das Muster an den korrekten Stellen aber vorhanden und dort inhaltlich exakt so wie beschrieben.
- `design.md` Z.102-105 und Z.428-430 (D8): "`describe('Payment Reminder Emails', ...)`-Block in `EmailNotificationTest.php:198-345` (8 Tests)" → tatsächlich beginnt der Block bei Zeile 199 (Zeile 198 ist eine Leerzeile) und endet bei Zeile 347 (`});`), nicht 345. Zudem enthält der Block **7** `it(...)`-Tests, nicht 8 (verifiziert per Zeilen-genauem Grep: Zeilen 200, 223, 242, 260, 279, 308, 322). Für T05 (Entfernen des Blocks) ist die Abweichung unkritisch, da der Task ohnehin per Beschreibung ("Block ... entfernen") und nicht per exaktem Zeilenbereich vorgeht — aber die numerische Behauptung "8 Tests" ist falsch und sollte in `design.md`/`proposal.md` auf "7 Tests, Zeilen 199-347" korrigiert werden.

## Nicht auffindbar

- Keine Behauptung konnte nicht verifiziert werden — alle in `proposal.md`/`design.md` genannten Datei:Zeile-Referenzen zu bestehendem Code existieren und wurden geprüft.

## Präzisions-Hinweis (kein Fehler, aber erwähnenswert)

- `design.md` Z.139-141 / proposal.md Z.47-49: "`window.confirm(...)`-Bestätigungsdialoge ... (Zeile 411, 428, 445)" → die Zeilennummern 411/428/445 sind exakt korrekt (`deleteInvoice()`, `finalizeInvoice()`, `cancelInvoice()` in `InvoicesView.vue`), aber der tatsächliche Code ruft den globalen Bare-Aufruf `confirm(...)` auf, nicht `window.confirm(...)` (z. B. Zeile 411: `if (!confirm('Diesen Rechnungsentwurf unwiderruflich löschen?')) {`). Funktional identisch (`confirm` ist im Browser-Kontext dasselbe globale Objekt wie `window.confirm`), aber wörtlich ungenau — für T07 unkritisch, da `tasks.md` T07 selbst `window.confirm(...)` als Ziel-API für den neuen Code vorschlägt (funktioniert unabhängig vom Bestandsmuster).

## Neue Elemente (Plausibilität)

- `tasks.md` T01: `backend/database/migrations/2026_08_14_140001_add_document_type_to_invoices_table.php`, `..._140002_add_fee_invoice_id_to_invoice_dunnings_table.php` → keine Namenskollision, additive Migrationen auf bestehenden Tabellen (`invoices`, `invoice_dunnings` existieren beide bereits) — Pfad und Namensschema konsistent mit bestehenden Migrationen im Verzeichnis.
- `tasks.md` T01: `backend/config/invoicing.php` (neu) → `backend/config/` existiert bereits (Laravel-Standard), kein Namenskonflikt (`grep` liefert keine vorhandene `invoicing.php`).
- `tasks.md` T01: `backend/app/Support/DunningFeeSchedule.php` (neu) → Verzeichnis `backend/app/Support/` — Existenz nicht geprüft, aber unproblematisch, da Laravel/Composer-Autoloading (PSR-4) beliebige Unterordner unter `app/` zulässt; kein Konflikt mit vorhandenen Klassen (`grep -rn "class DunningFeeSchedule"` liefert keine Treffer).
- `tasks.md` T02: `backend/app/Services/InvoiceDunningRecorder.php`, `backend/app/Exceptions/InvoiceDunningNotEligibleException.php`, `InvoiceDunningLevelExceededException.php` (neu) → keine Namenskollisionen (`grep` liefert keine Treffer), Pfade konsistent mit bestehendem `InvoicePaymentRecorder.php`/`InvoiceOverpaymentException.php` im selben Verzeichnis.
- `tasks.md` T03: `backend/app/Events/InvoiceDunningTriggered.php`, `backend/app/Listeners/SendInvoiceDunningEmail.php`, `backend/app/Mail/InvoiceDunningNotice.php`, `backend/resources/views/emails/invoice-dunning-notice.blade.php` (neu) → keine Namenskollisionen, Pfade und Namensschema 1:1 analog zu den bestätigten Vorbildern `InvoiceWasSent`/`SendInvoiceEmail`/`InvoiceSent`/`invoice-sent.blade.php`.
- `tasks.md` T04: `backend/app/Http/Resources/InvoiceDunningResource.php` (neu), neue Route `POST /invoices/{invoice}/remind` → kein Namenskonflikt (`grep -n "remind" backend/routes/api.php` liefert vor diesem Change keine Treffer), Platzierung "neben `finalize`/`cancel`/`send-email`" (Zeilen 184-186) ist konsistent mit dem bestehenden Routenblock (Zeilen 182-187).
- `tasks.md` T06/T09: neuer Dashboard-Schlüssel `overdueOrRemindedInvoices` und neuer Frontend-Kartenblock → kein Namenskonflikt mit vorhandenen Dashboard-Response-Schlüsseln (`stats`, `upcomingSessions`, `recentBookings`, `pendingDogRegistrations`, `pendingDogDeletionRequests`, `pendingCancellationRequests`).

## Empfehlung

Die Spezifikation ist verlässlich genug zum Fortfahren (User-Gate 1). Alle sicherheits-/geldbezogenen Kernannahmen (Gebührenverbuchung als eigenständiges Dokument, Concurrency-Schutz via `lockForUpdate()` innerhalb der Transaktion, Cron-Abschaltung ohne Parallelbetrieb, Diskriminator-Spalte zur Vermeidung einer `HasOne`-Fehlinterpretation) sind mit konkreten, zutreffenden Codebelegen unterlegt. Zwei Korrekturen sind vor oder während der Implementierung sinnvoll, aber nicht blockierend: (1) die Zeilenreferenz `DashboardController.php:57-68,148-168` in `design.md` sollte für T06 auf die tatsächlichen `with()/limit(5)/get()/map()`-Blöcke (`104-141`, `204-221`) korrigiert werden, damit `dev-php` nicht versehentlich den `$stats`-Zählblock als Vorlage nimmt; (2) die Zahl "8 Tests"/Zeilenbereich "198-345" für den zu entfernenden `describe('Payment Reminder Emails', ...)`-Block in `EmailNotificationTest.php` ist auf "7 Tests"/"199-347" zu korrigieren.

# Notes: T03 — Mahn-E-Mail (Event/Listener/Mailable/View)

## Umgesetzt

1:1 nach dem `InvoiceWasSent`/`SendInvoiceEmail`/`InvoiceSent`-Muster
(`backend/app/Events/InvoiceWasSent.php`,
`backend/app/Listeners/SendInvoiceEmail.php`,
`backend/app/Mail/InvoiceSent.php`), siehe `design.md` Decision D7:

- `backend/app/Events/InvoiceDunningTriggered.php` (neu): `Dispatchable`,
  `InteractsWithSockets`, `SerializesModels`; `public InvoiceDunning $dunning`.
- `backend/app/Listeners/SendInvoiceDunningEmail.php` (neu): `handle()`
  lädt `$event->dunning->loadMissing(['invoice.customer.user', 'feeInvoice.items'])`
  und versendet synchron `Mail::to($event->dunning->invoice->customer->user->email)
  ->send(new InvoiceDunningNotice($event->dunning))` — **kein** `ShouldQueue`,
  identische Begründung wie `SendInvoiceEmail` (seltene, bewusste
  Einzelaktion). `failed()` loggt `invoice_dunning_id`, `invoice_id`,
  `level`, `error` analog zu `SendInvoiceEmail::failed()`.
- `backend/app/Mail/InvoiceDunningNotice.php` (neu): `envelope()` identisch
  zum `Setting`/`Cache::remember('email_settings', ...)`-Muster aus
  `InvoiceSent`, Betreff `"Zahlungserinnerung – Mahnung Stufe {level} zu
  Rechnung {invoice_number}"`. `content()` rendert
  `emails.invoice-dunning-notice` mit `Cache::remember('all_settings', ...)`
  (identisch zu `InvoiceSent::content()`). `attachments()` rendert **nur
  das Gebührendokument** (`$this->dunning->feeInvoice`) über
  `app(InvoicePdfRenderer::class)` als PDF-Anhang, exakt das
  `Attachment::fromData(...)`-Muster aus `InvoiceSent::attachments()` —
  die Original-Rechnung wird bewusst **nicht** erneut angehängt (siehe
  `design.md` Decision D7 / `proposal.md` offene Frage 3: der Kunde hat sie
  bereits über den Sende-Flow aus `add-invoice-send-flow` erhalten).
- `backend/resources/views/emails/invoice-dunning-notice.blade.php` (neu):
  erweitert `layouts.email` (identische `.info-box`/`.info-row`/
  `.info-label`-Klassen wie `invoice-sent.blade.php`, dazu ein rötlich
  eingefärbter `.amount-box`/`.fee-info`-Block als visuelle Abgrenzung zur
  regulären Rechnungs-Mail). Nennt Rechnungsnummer, Fälligkeitsdatum und
  Restbetrag (`$dunning->invoice->remaining_balance`) der Original-Rechnung
  sowie Mahnstufe (`$dunning->level`) und Gebührenbetrag
  (`$dunning->fee_amount`) im Text. `$dunning` ist als öffentliche
  Mailable-Property automatisch in der View verfügbar (kein expliziter
  `with`-Eintrag nötig, gleiches Verhalten wie `$invoice` in
  `invoice-sent.blade.php`).
- **Keine** Änderung an `backend/app/Providers/AppServiceProvider.php` —
  automatische Event-Discovery reicht, der Kommentar in Zeile 66-74
  (Doppel-Registrierungsbug aus `fix-duplicate-event-listener-registration`)
  wurde nicht angetastet, wie im Auftrag gefordert.

## Baseline-Ergänzung (notwendig für `composer stan` grün)

`backend/phpstan-baseline.neon`: neuer Eintrag für
`app/Mail/InvoiceDunningNotice.php`
(`larastan.noEnvCallsOutsideOfConfig`, `count: 2`), identisch zum
bestehenden Eintrag für `app/Mail/InvoiceSent.php` (Zeile 340-343 vor der
Änderung). Grund: die `envelope()`-Methode übernimmt 1:1 das
`$settings[...] ?? env(...)`-Fallback-Muster aus `InvoiceSent`, das bereits
für zwei bestehende Mail-Klassen (`InvoiceSent`, `PaymentReminder`,
`BookingConfirmation`) baseline-akzeptiert ist. Kein neues strukturelles
Problem, sondern Fortführung eines etablierten, bereits akzeptierten
Patterns. `composer.json` enthält kein Skript zur automatischen
Baseline-Regenerierung — Eintrag manuell nach exaktem Vorbild ergänzt.

## Test-Datei

`backend/tests/Feature/InvoiceDunningNoticeMailTest.php` (neu,
`uses()->group('feature', 'invoice')`, `it()`-Stil gemäß `TESTING.md`).
Da T02 (`InvoiceDunningRecorder`) parallel läuft und in diesem Worktree
noch nicht vorhanden ist, werden alle Fixtures (Original-Rechnung,
Gebührendokument mit `document_type = 'dunning_fee'` + eigener
`InvoiceItem`, `InvoiceDunning` mit `fee_invoice_id`) direkt per Factory
in `beforeEach()` aufgebaut, ohne den Recorder zu nutzen:

- `it löst durch das dispatchen von invoicedunningtriggered genau eine
  mahn-mail an den kunden aus` — `Mail::fake()` +
  `InvoiceDunningTriggered::dispatch($this->dunning)`, prüft
  `Mail::assertSent(InvoiceDunningNotice::class, 1)` und
  `hasTo($this->customerUser->email)`.
- `it hängt bei der ausgelösten mahn-mail das gebührendokument als pdf an`
  — prüft über den `Mail::assertSent(...)`-Closure-Callback, dass
  `$mail->attachments()` genau einen `application/pdf`-Anhang mit
  `as === $this->feeInvoice->invoice_number.'.pdf'` enthält.
- `it rendert die mahn-mail mit stufe, mahngebühr und der rechnungsnummer
  der original-rechnung` — analog zu
  `InvoiceSentMailBankDetailsTest.php`s Direkt-Instanziierungs-Muster:
  `(new InvoiceDunningNotice($this->dunning))->assertSeeInHtml(...)` prüft
  `"Mahnstufe 2"`, den formatierten Gebührenbetrag (`10,00 €`) und die
  Rechnungsnummer der Original-Rechnung.

## Verifikation

Lokale Docker-Umgebung (`docker compose`) war durch parallel laufende
Worktree-Agenten (T02/T06) mit fixen Container-Namen belegt
(`dog-school-mailpit` etc. bereits in Verwendung durch den
Haupt-Checkout). Ausweichend lokal via Composer (frisch installiert in
den Scratchpad, `php` 8.3 aus Homebrew) gegen SQLite (`:memory:`, wie in
`phpunit.xml` fest konfiguriert) verifiziert:

```
php composer.phar lint          # PASS (322 files)
php composer.phar stan          # No errors (213/213)
php composer.phar compat-check  # exit 0, keine Ausgabe
php composer.phar test          # 872 passed (2686 assertions), 2 skipped
                                 # (PostgreSQL-Concurrency-Tests aus T02
                                 # von add-invoice-payment-entry, unverändert
                                 # vorbestehend, benötigen echte MVCC-DB)
php composer.phar qa            # aggregiert alle vier, exit 0
```

Kein MySQL/PostgreSQL-Lauf für T03 nötig — reine Event/Listener/
Mailable/View-Task ohne Migrationen oder raw SQL.

## Offene Punkte für Reviewer/Tester

- Die Test-Fixtures in `InvoiceDunningNoticeMailTest.php` bauen das
  Gebührendokument und den `InvoiceDunning`-Datensatz manuell auf, da T02
  (`InvoiceDunningRecorder`) in diesem Worktree nicht vorhanden war. Sobald
  T02 gemerged ist, könnte optional ein zusätzlicher Integrationstest
  ergänzt werden, der `InvoiceDunningRecorder::trigger()` end-to-end mit
  `InvoiceDunningTriggered::dispatch(...)` verkettet — das ist aber
  Aufgabe von T04 (`InvoiceController::remind()`), nicht T03.
- `InvoiceDunningNotice::attachments()` geht implizit davon aus, dass
  `$this->dunning->feeInvoice` nicht `null` ist (kein Null-Check, analog
  zu `InvoiceSent::attachments()`, das ebenfalls kein Null-Handling für
  `$this->invoice` hat). Das ist konsistent mit T02s Vertrag
  (`InvoiceDunningRecorder::trigger()` liefert laut `design.md` immer ein
  `InvoiceDunning` mit gesetztem `fee_invoice_id`), aber falls ein
  zukünftiger Aufrufer `InvoiceDunningTriggered` mit einer Mahnung ohne
  Gebührendokument dispatcht, würde hier eine Fehlermeldung auf
  `null->invoice_number` auftreten. Kein Scope-Anpassungsbedarf für T03,
  aber als Hinweis für den Reviewer dokumentiert.
- `phpstan-baseline.neon` wurde manuell erweitert (kein
  Baseline-Regenerierungs-Skript in `composer.json` vorhanden) — der
  Reviewer sollte den neuen Eintrag gegen den bestehenden
  `InvoiceSent`-Eintrag abgleichen (identischer Message-/Identifier-/
  Count-Wortlaut).

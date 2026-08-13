# Notes: T02 — `InvoicePdfRenderer`-Service extrahieren, PDF-Anhang für `InvoiceSent`

## Umgesetzt

- **Neu:** `backend/app/Services/InvoicePdfRenderer.php` — `class
  InvoicePdfRenderer` (kein Interface-Zwang, analog zum Stilvorbild
  `App\Services\InvoiceNumberGenerator`) mit einer einzigen Methode
  `render(Invoice $invoice): \Barryvdh\DomPDF\PDF`, die die bisherige
  Inline-Kette `Pdf::loadView('pdf.invoice', [...])
  ->setPaper('a4', 'portrait')->setOption(...)->setOption(...)`
  1:1 kapselt (keine Verhaltensänderung, reine Extraktion).
- `backend/app/Http/Controllers/Api/InvoiceController.php`:
  - `use Barryvdh\DomPDF\Facade\Pdf;` entfernt (nicht mehr referenziert),
    `use App\Services\InvoicePdfRenderer;` ergänzt.
  - `downloadPdf(Invoice $invoice)` → `downloadPdf(Invoice $invoice,
    InvoicePdfRenderer $pdfRenderer)`. Methoden-Injection (nicht
    Konstruktor-Injection) — konsistent mit dem bestehenden Muster in
    `finalize(Invoice $invoice, InvoiceNumberGenerator $numberGenerator)`
    und `cancel(...)` in derselben Klasse. Laravels Controller-Dispatch
    löst zusätzliche, typisierte Methodenparameter über den Container
    auf, Route-Model-Binding bleibt für `$invoice` unberührt.
  - Methodenkörper ersetzt durch `return
    $pdfRenderer->render($invoice)->download($invoice->invoice_number
    .'.pdf');` — Autorisierung (`$this->authorize('view', $invoice)`)
    und das vorgelagerte `load([...])` bleiben unverändert.
- `backend/app/Mail/InvoiceSent.php`: `attachments()` implementiert.
  PDF wird per `Attachment::fromData(fn () =>
  $pdfRenderer->render($this->invoice)->output(), $this->invoice
  ->invoice_number.'.pdf')->withMime('application/pdf')` angehängt.
  `$pdfRenderer` wird **innerhalb der Methode** per `app(InvoicePdfRenderer
  ::class)` aufgelöst, **nicht** über den Konstruktor injiziert.

## Entscheidung: Method- statt Constructor-Injection in `InvoiceSent`

`tasks.md` verlangte eine empirische statt nur theoretische Prüfung,
welcher Weg mit `Queueable`/`SerializesModels` sauber funktioniert. Befund:

- `InvoiceSent` wird an allen bestehenden Aufrufstellen direkt per `new
  InvoiceSent($invoice)` instanziiert — in `SendInvoiceEmail::handle()`
  (`Mail::to(...)->send(new InvoiceSent($event->invoice))`) sowie in fünf
  Testfällen in `InvoiceSentMailBankDetailsTest.php`. Keine dieser
  Stellen läuft über den Laravel-Container (`app()->make(InvoiceSent
  ::class)`), sondern über den PHP-Operator `new` direkt.
- Empirisch geprüft per Sandbox-Skript (isoliert, keine Änderung an
  `InvoiceSent.php`): eine Klasse mit Konstruktor-Signatur
  `__construct(public $invoice, public FakeService $svc)`, aufgerufen als
  `new TestMailable("dummyInvoice")` (ein Argument, wie im bestehenden
  Aufrufmuster), wirft `ArgumentCountError: Too few arguments to
  function ... exactly 2 expected`. Das bestätigt: eine verpflichtende
  Konstruktor-Injection des Renderers hätte **jede** bestehende
  Aufrufstelle brechen lassen (`SendInvoiceEmail`, fünf Testfälle) — Dateien,
  die laut `tasks.md` T02-Dateikatalog **nicht** in dieser Task geändert
  werden sollen (`SendInvoiceEmail.php` gehört zu T01, bereits
  abgeschlossen).
- `app(InvoicePdfRenderer::class)` innerhalb von `attachments()` vermeidet
  dieses Problem vollständig: kein zusätzlicher Konstruktor-Parameter,
  keine bestehende Aufrufstelle muss angepasst werden, und
  `Queueable`/`SerializesModels::__serialize()` iteriert nur über
  tatsächlich vorhandene Objekt-Properties — ohne eine zusätzliche
  Property gibt es nichts, das bei einem hypothetischen künftigen
  Queue-Durchlauf serialisiert werden müsste.
- Das entspricht exakt dem in `tasks.md` als Alternative vorgeschlagenen
  Code-Beispiel und wurde 1:1 übernommen.

## Verifikation

```
docker compose exec php composer qa
# lint:          309 files, PASS (nach vendor/bin/pint-Autofix für
#                 fully_qualified_strict_types/ordered_imports in den
#                 beiden neuen/geänderten Dateien)
# stan:           No errors (206 Dateien)
# compat-check:   keine Ausgabe, exit 0
# test:           816 passed (2559 assertions)

docker compose exec php vendor/bin/pest \
  tests/Feature/InvoiceSentMailBankDetailsTest.php \
  tests/Feature/InvoicePdfTest.php --no-coverage
# InvoiceSentMailBankDetailsTest: 5 passed (inkl. neuem Anhang-Test)
# InvoicePdfTest: 29 passed — unverändert grün, keine Anpassung nötig
```

Neuer, gezielter Test in `InvoiceSentMailBankDetailsTest.php`:
`it('hängt das rechnungs-pdf als anhang an', ...)` — prüft
`count($mail->attachments()) === 1`, `$attachments[0]->mime ===
'application/pdf'` und `$attachments[0]->as ===
$invoice->invoice_number.'.pdf'` (öffentliche Properties von
`Illuminate\Mail\Attachment`, kein `toMailAttachment()` — diese Methode
existiert auf der Basisklasse nicht).

## Abweichungen von der Task-Beschreibung

Keine. `InvoicePdfRenderer`, `downloadPdf()` und `attachments()` folgen
exakt den in `tasks.md` gezeigten Code-Beispielen; `vendor/bin/pint`
hat lediglich die Import-Reihenfolge und `@see`-Referenzen auf voll
qualifizierte Klassennamen automatisch normalisiert (kognitiv
äquivalent, keine inhaltliche Änderung).

## Offene Punkte für T03

- `InvoiceController::sendEmail()` (neuer Endpunkt) ist noch nicht
  implementiert — `App\Events\InvoiceWasSent` wird weiterhin an keiner
  Stelle `dispatch()`-t.
- Der neue PDF-Anhang in `InvoiceSent` erhöht das Risiko eines
  langsameren synchronen Mail-Versands (PDF-Rendering + SMTP-Handshake
  im selben Request, siehe `design.md` Decision D4/Risks) — für T03
  relevant, aber dort bereits als bekanntes, akzeptiertes Trade-off
  dokumentiert.

# Tasks für add-invoice-send-flow

## T01: Mail-Infrastruktur umbenennen (Created → Sent) und auf synchronen Versand umstellen

- **Agent:** dev-php
- **Dateien:**
  - `backend/app/Events/InvoiceWasCreated.php` → neu:
    `backend/app/Events/InvoiceWasSent.php` (Klasse umbenannt, Inhalt
    sonst unverändert)
  - `backend/app/Listeners/SendInvoiceCreatedEmail.php` → neu:
    `backend/app/Listeners/SendInvoiceEmail.php` (Klasse umbenannt,
    `ShouldQueue`/`InteractsWithQueue` entfernt, `Mail::to(...)
    ->queue(...)` → `Mail::to(...)->send(...)`)
  - `backend/app/Mail/InvoiceCreated.php` → neu:
    `backend/app/Mail/InvoiceSent.php` (Klasse umbenannt,
    `attachments()` bleibt vorerst unverändert leer — PDF-Anhang folgt in
    T02)
  - `backend/resources/views/emails/invoice-created.blade.php` → neu:
    `backend/resources/views/emails/invoice-sent.blade.php` (Inhalt
    unverändert kopiert, keine Text-/Layout-Änderung nötig)
  - `backend/app/Providers/AppServiceProvider.php`
  - `backend/tests/Feature/InvoiceCreatedMailBankDetailsTest.php` → neu:
    `backend/tests/Feature/InvoiceSentMailBankDetailsTest.php`
    (Klassenreferenzen `InvoiceCreated` → `InvoiceSent` angepasst,
    Assertions inhaltlich unverändert)
- **Abhängigkeiten:** keine
- **Beschreibung:**
  Reine Umbenennung + ein Verhaltenswechsel (synchron statt Queue), siehe
  `design.md` Decision D4 und D5. Kein neuer Endpunkt, keine neue
  Funktionalität in dieser Task — das ist die Grundlage für T02/T03.

  **`InvoiceWasSent`** (Kopie von `InvoiceWasCreated.php`, nur
  Klassenname/Dateiname geändert; Konstruktor-Signatur
  `public Invoice $invoice` bleibt identisch).

  **`SendInvoiceEmail`** (Kopie von `SendInvoiceCreatedEmail.php`):
  - `implements ShouldQueue` entfernen, `use InteractsWithQueue;`
    entfernen.
  - `handle(InvoiceWasSent $event): void` — Parameter-Typ anpassen.
  - `Mail::to($event->invoice->customer->user->email)
    ->queue(new InvoiceSent($event->invoice));` → `->send(new
    InvoiceSent($event->invoice));`.
  - `shouldQueue()`-Methode entfernen (kein Queue-Kontrakt mehr, die
    Methode gehört zu `InteractsWithQueue`/`ShouldQueue` und hat ohne
    Queue keine Wirkung mehr).
  - `failed(InvoiceWasSent $event, \Throwable $exception): void` bleibt
    erhalten (harmlos, auch ohne Queue kein Schaden — wird von T03 aber
    ohnehin nicht mehr aufgerufen, da synchrone Exceptions über den
    normalen `try/catch` in `InvoiceController::sendEmail()` laufen;
    Methode kann bestehen bleiben oder entfernt werden, falls PHPStan
    "unbenutzt" meldet — dev-php entscheidet nach `composer stan`-Lauf).

  **`InvoiceSent`** (Kopie von `InvoiceCreated.php`): nur Klassenname,
  `content()`-View-Referenz von `'emails.invoice-created'` auf
  `'emails.invoice-sent'` ändern. `attachments()` bleibt in dieser Task
  unverändert (`return [];`).

  **`invoice-sent.blade.php`**: 1:1-Kopie von
  `invoice-created.blade.php`, keine inhaltliche Änderung nötig (Text
  "anbei erhalten Sie Ihre Rechnung" passt bereits zum künftigen
  PDF-Anhang aus T02).

  **`AppServiceProvider.php`** (Zeile 8, 11, 79-82): Imports und
  `Event::listen(...)`-Aufruf auf die neuen Klassennamen umstellen.

  **Test-Umbenennung**: Datei kopieren/umbenennen, `use
  App\Mail\InvoiceCreated;` → `use App\Mail\InvoiceSent;`, `new
  InvoiceCreated($this->invoice)` → `new InvoiceSent($this->invoice)`
  (vier Stellen). Restlicher Testinhalt (Assertions,
  `uses()->group('feature', 'invoice')`) unverändert.

  Alte Dateien (`InvoiceWasCreated.php`, `SendInvoiceCreatedEmail.php`,
  `InvoiceCreated.php`, `invoice-created.blade.php`,
  `InvoiceCreatedMailBankDetailsTest.php`) werden gelöscht, nicht nur
  kopiert (kein Alt-Code-Rest).
- **Akzeptanzkriterien:**
  - [x] `grep -rn "InvoiceWasCreated\|SendInvoiceCreatedEmail\|InvoiceCreated" backend/app backend/tests backend/resources` liefert keinen Treffer mehr (alle Referenzen umgestellt).
  - [x] `backend/tests/Feature/InvoiceSentMailBankDetailsTest.php` (vier bestehende Tests) läuft grün, unveränderte Assertions.
  - [x] `SendInvoiceEmail` implementiert **kein** `ShouldQueue` mehr; `Mail::fake()` + `Mail::assertSent(InvoiceSent::class)` (statt `assertQueued`) bestätigt synchronen Versand in einem gezielten Unit-/Feature-Test für den Listener.
  - [x] `composer qa` läuft grün.

---

## T02: `InvoicePdfRenderer`-Service extrahieren, PDF-Anhang für `InvoiceSent`

- **Agent:** dev-php
- **Dateien:**
  - `backend/app/Services/InvoicePdfRenderer.php` (neu)
  - `backend/app/Http/Controllers/Api/InvoiceController.php`
    (`downloadPdf()` refaktoriert)
  - `backend/app/Mail/InvoiceSent.php` (`attachments()` implementiert)
- **Abhängigkeiten:** T01 (Mailable muss bereits `InvoiceSent` heißen)
- **Beschreibung:**
  Siehe `design.md` Decision D6.

  **`InvoicePdfRenderer`** (analog zum Stilvorbild
  `App\Services\InvoiceNumberGenerator`, reines PHP ohne Interface-Zwang,
  PHPDoc-Block):
  ```php
  final class InvoicePdfRenderer
  {
      public function render(Invoice $invoice): \Barryvdh\DomPDF\PDF
      {
          return Pdf::loadView('pdf.invoice', ['invoice' => $invoice])
              ->setPaper('a4', 'portrait')
              ->setOption('isHtml5ParserEnabled', true)
              ->setOption('isRemoteEnabled', true);
      }
  }
  ```

  **`InvoiceController::downloadPdf()`** (`InvoiceController.php:402-417`):
  Methode injiziert `InvoicePdfRenderer $pdfRenderer` und ersetzt die
  bisherige Inline-`Pdf::loadView(...)`-Kette durch
  `$pdfRenderer->render($invoice)->download($invoice->invoice_number
  .'.pdf')`. Verhalten/Response bleibt byte-identisch (kein
  Test-Bruch für `InvoicePdfTest.php` erwartet).

  **`InvoiceSent::attachments()`**: Service per Constructor- oder
  Method-Injection beziehen (Laravel löst Dependencies in
  `attachments()` nicht automatisch auf — Service im Konstruktor der
  Mailable injizieren, analog zu `Setting`-Zugriffen, die dort bereits
  über `Cache::remember()` direkt aufgerufen werden; alternativ
  `app(InvoicePdfRenderer::class)` innerhalb der Methode, falls
  Constructor-Injection mit `Queueable`/Serialisierung kollidiert —
  dev-php prüft, welcher Weg mit `SerializesModels` sauber
  funktioniert):
  ```php
  public function attachments(): array
  {
      $pdfRenderer = app(InvoicePdfRenderer::class);

      return [
          Attachment::fromData(
              fn () => $pdfRenderer->render($this->invoice)->output(),
              $this->invoice->invoice_number.'.pdf',
          )->withMime('application/pdf'),
      ];
  }
  ```
- **Akzeptanzkriterien:**
  - [x] `GET /api/v1/invoices/{id}/pdf` liefert weiterhin identisches
    Verhalten (bestehende `InvoicePdfTest.php`-Tests bleiben grün, ohne
    Anpassung).
  - [x] `new InvoiceSent($invoice)`-Mailable hat genau einen Anhang mit
    MIME-Typ `application/pdf` und Dateiname
    `{invoice_number}.pdf` (neuer, gezielter Test in
    `InvoiceSentMailBankDetailsTest.php` oder eigener Testdatei, z. B.
    `it('hängt das rechnungs-pdf als anhang an', ...)`).
  - [x] `composer qa` läuft grün (insbesondere PHPStan: keine
    unaufgelösten Dependency-Injection-Probleme bei Queueable-Mailables).

---

## T03: `InvoiceController::sendEmail()` — neuer Endpunkt für App-internen Versand

- **Agent:** dev-php
- **Dateien:**
  - `backend/app/Http/Controllers/Api/InvoiceController.php`
  - `backend/app/Policies/InvoicePolicy.php`
  - `backend/routes/api.php`
  - `backend/tests/Feature/InvoiceSendEmailTest.php` (neu)
- **Abhängigkeiten:** T01, T02
- **Beschreibung:**
  Neue Route unterhalb der bestehenden `pdf`-Route
  (`backend/routes/api.php:181`):
  ```php
  Route::post('/invoices/{invoice}/send-email', [InvoiceController::class, 'sendEmail']);
  ```

  Neue Policy-Methode (Stilvorbild `finalize()`,
  `InvoicePolicy.php:101-118` — Rollen-only, siehe `design.md` Decision
  D7):
  ```php
  public function send(User $user, Invoice $invoice): bool
  {
      return $user->isAdminOrTrainer();
  }
  ```

  Neue Controller-Methode:
  ```php
  private const SENDABLE_STATUSES = ['sent', 'reminded', 'overdue'];

  public function sendEmail(Invoice $invoice): JsonResponse
  {
      $this->authorize('send', $invoice);

      $invoice->load(['customer.user', 'items']);

      if (! in_array($invoice->status, self::SENDABLE_STATUSES, true)) {
          return response()->json([
              'message' => 'Diese Rechnung kann in ihrem aktuellen Status nicht versendet werden.',
          ], 422);
      }

      if (! $invoice->customer->user->email) {
          return response()->json([
              'message' => 'Für diesen Kunden ist keine E-Mail-Adresse hinterlegt.',
          ], 422);
      }

      try {
          InvoiceWasSent::dispatch($invoice);
      } catch (\Throwable $e) {
          logger()->error('Rechnungs-E-Mail konnte nicht versendet werden', [
              'invoice_id' => $invoice->id,
              'error' => $e->getMessage(),
          ]);

          return response()->json([
              'message' => 'Die Rechnung konnte nicht per E-Mail versendet werden. Bitte laden Sie das PDF herunter und versenden Sie es manuell.',
          ], 502);
      }

      return response()->json([
          'message' => 'Rechnung wurde per E-Mail versendet.',
      ]);
  }
  ```
  (`use App\Events\InvoiceWasSent;` ergänzen.) Der Status ändert sich
  **nicht** — kein `$invoice->update(...)`-Aufruf (siehe `design.md`
  Goals/Non-Goals).
- **Akzeptanzkriterien:**
  - [x] `POST /api/v1/invoices/{id}/send-email` für eine Rechnung im
    Status `sent` mit Kunden-E-Mail liefert HTTP 200; `Mail::fake()` +
    `Mail::assertSent(InvoiceSent::class, fn ($mail) => $mail->hasTo($customerEmail))`
    bestätigt den Versand.
  - [x] Derselbe Aufruf für `reminded` und `overdue` liefert ebenfalls
    HTTP 200.
  - [x] Aufruf für eine Rechnung im Status `draft`, `paid` oder
    `cancelled` liefert HTTP 422 mit passender Nachricht, **keine**
    E-Mail wird verschickt (`Mail::assertNothingSent()`).
  - [x] Aufruf für eine Rechnung, deren Kunde (testweise per Factory
    präpariert) eine leere `email` hat, liefert HTTP 422 mit
    "keine E-Mail-Adresse hinterlegt"-Nachricht, keine E-Mail wird
    verschickt.
  - [x] Kunde erhält HTTP 403 bei Aufruf des Endpunkts.
  - [x] `invoice.status` ist vor und nach einem erfolgreichen Aufruf
    identisch (kein Statuswechsel).
  - [x] Simulierter Mail-Fehler (z. B. `Mail::shouldReceive(...)
    ->andThrow(...)` oder ein Test-Mailer, der eine Exception wirft)
    liefert HTTP 502 mit Fallback-Hinweis auf den manuellen Download.
  - [x] `composer qa` läuft grün.

---

## T04: `InvoiceSendDialog.vue` — neue Dialog-Komponente

- **Agent:** dev-typescript
- **Dateien:**
  - `frontend/src/components/InvoiceSendDialog.vue` (neu)
  - `frontend/src/components/InvoiceSendDialog.test.ts` (neu)
- **Abhängigkeiten:** keine (reine Presentation-Komponente, kein
  `apiClient`-Import, siehe `design.md` Decision D8)
- **Beschreibung:**
  Neue Komponente nach dem `@headlessui/vue`-Muster von
  `InvoiceDetailModal.vue` (Import-Zeile 190:
  `TransitionRoot, TransitionChild, Dialog, DialogPanel, DialogTitle`).

  Props:
  ```ts
  defineProps<{
    isOpen: boolean
    invoice?: any
  }>()
  ```

  Emits:
  ```ts
  defineEmits<{
    close: []
    download: [invoice: any]
    'send-email': [invoice: any]
  }>()
  ```

  Darstellung (**immer beide Optionen, kein `hasEmail`-Zweig** — siehe
  `design.md` Decision D8 und `proposal.md` offene Frage 4,
  User-Gate-1-Entscheidung: der Frontend-Fallback-Zweig für "keine
  E-Mail-Adresse" wird bewusst nicht gebaut, YAGNI):
  - Titel "Rechnung versenden", Rechnungsnummer als Kontext-Info.
  - Zwei Buttons/Optionen werden **immer** angezeigt:
    - "Aus der App versenden" → `emit('send-email', props.invoice)`.
    - "Manuell versenden (PDF herunterladen)" →
      `emit('download', props.invoice)`.
    - Kurzer Hinweistext, an welche Adresse die App-Mail ginge
      (`invoice.customer.user.email` anzeigen, analog zur bestehenden
      Anzeige in `InvoiceDetailModal.vue:85`).
  - "Schließen"-Button, emittiert `close`.
  - **Kein** eigener Loading-/Error-State für den API-Aufruf selbst
    (Erfolg/Fehler werden vom aufrufenden `InvoicesView.vue` per
    `showSuccess()`/`handleApiError()` kommuniziert, siehe T05) — die
    Komponente selbst bleibt zustandslos gegenüber dem Request-Ausgang.
    Optional: ein einfacher `sending`-Ref, der die Buttons während eines
    laufenden `send-email`-Aufrufs deaktiviert, falls `InvoicesView.vue`
    das über eine zusätzliche Prop steuert (dev-typescript entscheidet,
    ob das für die UX in T05 sinnvoll per Prop durchgereicht wird, oder
    ob ein einfacher `confirm()`-Dialog vor dem Klick ausreicht,
    konsistent mit `finalizeInvoice()`/`cancelInvoice()` in
    `InvoicesView.vue`).
- **Akzeptanzkriterien:**
  - [x] Komponente zeigt beide Optionen (App-Mail, Download) **immer**
    an — unabhängig davon, ob `invoice.customer.user.email` gesetzt ist
    oder fehlt (kein `hasEmail`-Zweig, siehe User-Gate-1-Entscheidung 4).
  - [x] Klick auf "Aus der App versenden" emittiert `send-email` mit dem
    `invoice`-Objekt, macht **keinen** eigenen API-Aufruf.
  - [x] Klick auf "Manuell versenden" emittiert `download` mit dem
    `invoice`-Objekt.
  - [x] Klick auf "Schließen" emittiert `close`.
  - [x] `npm run lint`, `npm run test`, `npm run build` laufen ohne
    Fehler/Warnings durch.

---

## T05: `InvoicesView.vue` — Senden-Button verdrahten, Dialog mounten

- **Agent:** dev-typescript
- **Dateien:**
  - `frontend/src/views/invoices/InvoicesView.vue`
- **Abhängigkeiten:** T03 (Backend-Endpunkt), T04 (`InvoiceSendDialog`)
- **Beschreibung:**
  **Stub-Button ersetzen** (Zeile 101): `<button v-if="canSend(invoice)"
  disabled title="..." ...>Senden</button>` → `<button
  v-if="canSend(invoice)" @click="openSendDialog(invoice)"
  class="text-blue-600 dark:text-blue-400 hover:text-blue-900
  dark:hover:text-blue-300">Senden</button>` (Farbgebung analog zu
  `Freigeben`, Zeile 100).

  `SENDABLE_STATUSES`/`canSend()` (Zeile 213-218, 243-245) bleiben
  **unverändert** (bereits korrekt aus Change 1).

  **Dialog mounten**, analog zu `InvoiceFormModal`/`InvoiceDetailModal`
  (Zeile 120-138):
  ```html
  <InvoiceSendDialog
    :is-open="showSendDialog"
    :invoice="selectedInvoice"
    @close="closeSendDialog"
    @download="downloadPDF"
    @send-email="sendInvoiceEmail"
  />
  ```

  **Neue Refs** (neben `showFormModal`/`showDetailModal`, Zeile
  159-161): `const showSendDialog = ref(false)`.

  **Neue Funktionen** (nach dem Vorbild von `finalizeInvoice()`, Zeile
  338-353):
  ```ts
  function openSendDialog(invoice: any) {
    selectedInvoice.value = invoice
    showSendDialog.value = true
  }

  function closeSendDialog() {
    showSendDialog.value = false
    selectedInvoice.value = null
  }

  async function sendInvoiceEmail(invoice: any) {
    try {
      await apiClient.post(`/api/v1/invoices/${invoice.id}/send-email`)
      closeSendDialog()
      showSuccess('Rechnung versendet', 'Die Rechnung wurde per E-Mail versendet')
    } catch (error) {
      handleApiError(error, 'Fehler beim Versenden der Rechnung')
      // Dialog bleibt bewusst offen, damit sofort auf "Manuell
      // versenden" ausgewichen werden kann (design.md Decision D8).
    }
  }
  ```
  `downloadPDF(invoice)` (Zeile 283-302) wird **unverändert**
  wiederverwendet — kein `closeSendDialog()`-Aufruf nötig, da der
  Download selbst keinen Fehlerzustand hinterlässt, den man beheben
  müsste (Dialog bleibt geöffnet, User kann selbst schließen oder direkt
  danach auch noch die App-Mail-Option nutzen).

  **Neues Event `send` von `InvoiceDetailModal` verarbeiten** (siehe
  T06): in der `<InvoiceDetailModal ...>`-Bindung (Zeile 128-138) `@send="openSendDialog"` ergänzen.
- **Akzeptanzkriterien:**
  - [x] Klick auf "Senden" in der Listenzeile öffnet
    `InvoiceSendDialog` mit der korrekten Rechnung.
  - [x] "Aus der App versenden" im Dialog löst
    `POST /invoices/{id}/send-email` aus, schließt den Dialog bei Erfolg
    und zeigt einen Erfolgs-Toast.
  - [x] Ein fehlgeschlagener `send-email`-Aufruf zeigt einen
    Fehler-Toast, der Dialog bleibt offen.
  - [x] "Manuell versenden" im Dialog löst denselben PDF-Download aus
    wie der bestehende PDF-Button.
  - [x] `npm run lint`, `npm run test`, `npm run build` laufen ohne
    Fehler/Warnings durch.

---

## T06: `InvoiceDetailModal.vue` — Senden-Button auf `send`-Event umstellen

- **Agent:** dev-typescript
- **Dateien:**
  - `frontend/src/components/InvoiceDetailModal.vue`
- **Abhängigkeiten:** T05 (gleiche Konventionen, `InvoicesView.vue` muss
  das neue `send`-Event bereits verarbeiten können)
- **Beschreibung:**
  **Stub-Button ersetzen** (Zeile 172-174): `<button
  v-if="canSend(invoice)" disabled title="..." ...>Senden</button>` →
  `<button v-if="canSend(invoice)" @click="$emit('send', invoice)"
  class="btn bg-blue-600 hover:bg-blue-700 dark:bg-blue-700
  dark:hover:bg-blue-600 text-white">Senden</button>` (Farbgebung analog
  zu `Freigeben`, Zeile 169-171).

  **`defineEmits`** (Zeile 201-209): neues Event `send: [invoice: any]`
  ergänzen.

  `SENDABLE_STATUSES`/`canSend()` (Zeile 216, 241-243) bleiben
  **unverändert**.

  Das Modal selbst bleibt reines Presentation-Layer — der eigentliche
  `InvoiceSendDialog` wird ausschließlich von `InvoicesView.vue`
  gemountet (siehe `design.md` Decision D8), nicht dupliziert in diesem
  Modal.
- **Akzeptanzkriterien:**
  - [x] Klick auf "Senden" im Detail-Modal emittiert `send` mit dem
    `invoice`-Objekt, öffnet über `InvoicesView.vue` denselben
    `InvoiceSendDialog` wie der Listenzeilen-Button.
  - [x] Alle bisherigen Buttons/Events (`finalize`, `cancel`,
    `mark-paid`, `delete`, `edit`, `download`) funktionieren unverändert.
  - [x] `npm run lint`, `npm run test`, `npm run build` laufen ohne
    Fehler/Warnings durch.

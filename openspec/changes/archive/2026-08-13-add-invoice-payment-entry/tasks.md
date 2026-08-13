# Tasks für add-invoice-payment-entry

Reihenfolge: T01 → T02 → T03 → T04 (Backend, sequenziell, jede baut auf
der vorherigen auf) → T05 (Backend, unabhängig von T02-T04, kann parallel
zu diesen laufen) → T06 (Frontend, kann nach T01 starten, sobald die
API-Vertragsfelder feststehen — parallel zu T02-T05 möglich) → T07/T08
(Frontend, benötigen T03/T04/T06) → T09 (Frontend, benötigt T06-T08).

## T01: `payments.notes`-Spalte, Model, Requests

- **Agent:** dev-php
- **Dateien:**
  `backend/database/migrations/2026_08_13_100001_add_notes_to_payments_table.php`
  (neu), `backend/app/Models/Payment.php`,
  `backend/app/Http/Requests/StorePaymentRequest.php`,
  `backend/app/Http/Requests/UpdatePaymentRequest.php`
- **Abhängigkeiten:** keine
- **Beschreibung:** Neue additive Migration `$table->text('notes')
  ->nullable()` auf `payments` (siehe `design.md` Decision D5/Migration
  M1 — **DB-kritisch, aber unkritisch für MySQL/PostgreSQL/SQLite**, da
  reine additive Standard-Spalte ohne Enum/Raw-SQL). `Payment::$fillable`
  um `notes` ergänzen. `StorePaymentRequest::rules()`/
  `UpdatePaymentRequest::rules()` um `'notes' => ['nullable', 'string',
  'max:1000']` ergänzen, `validatedSnakeCase()` entsprechend erweitern
  (Attribute-Label `'notes' => 'Notiz'`). `PaymentResource::toArray()`
  bleibt unverändert (Feld `notes` existiert dort bereits, Zeile 29,
  liefert danach echte Werte statt immer `null`).
- **Akzeptanzkriterien:**
  - [x] Migration läuft fehlerfrei gegen SQLite (`composer test`) **und**
    wird mit einem kurzen Kommentar versehen, dass sie additiv/
    treiber-unkritisch ist (kein `DB::connection()->getDriverName()`-Switch
    nötig, siehe CLAUDE.md 4.2).
  - [x] `Payment::create([..., 'notes' => 'Bar bezahlt'])` persistiert und
    liefert den Wert über `PaymentResource` als `notes` zurück.
  - [x] `POST /api/v1/payments` mit `notes` im Payload validiert und
    speichert das Feld; ohne `notes` bleibt es `null` (Bestandsverhalten
    unverändert, kein Pflichtfeld).

## T02: `App\Services\InvoicePaymentRecorder`

- **Agent:** dev-php
- **Dateien:** `backend/app/Services/InvoicePaymentRecorder.php` (neu)
- **Abhängigkeiten:** T01
- **Beschreibung:** Neuer Service exakt wie in `design.md` Decision D2
  beschrieben: `record(Invoice $invoice, array $paymentData): Payment`
  (neue Zahlung anlegen) und `completeExisting(Payment $payment): Payment`
  (bestehende Zahlung auf `completed` setzen), beide innerhalb eines
  `DB::transaction()` mit `Invoice::query()->lockForUpdate()->findOrFail()`.
  Private `syncStatus()` setzt `status = 'paid'` +
  `paid_date` = Datum der abschließenden Zahlung (nicht `now()`), sobald
  die Summe abgeschlossener Zahlungen `total_amount` erreicht/übersteigt.
  Noch **nicht** in `PaymentController` verdrahtet (das ist T03) — dieser
  Task liefert den isolierten, eigenständig testbaren Service.
- **Akzeptanzkriterien:**
  - [x] Neue Pest-Testdatei `backend/tests/Feature/Domain/Payment/InvoicePaymentRecorderTest.php`
    (`uses()->group('domain', 'payment')`, `it()`-Stil gemäß TESTING.md)
    deckt ab: (a) Teilzahlung lässt Status unverändert, (b) Summe erreicht
    `total_amount` → Status wechselt zu `paid`, `paid_date` = Datum der
    abschließenden Zahlung, (c) Summe übersteigt `total_amount` (z. B.
    durch mehrere Zahlungen in Summe) → Status wechselt trotzdem zu `paid`.
  - [x] **DB-kritisch — gegen echtes PostgreSQL getestet:** ein
    dedizierter, expliziter Concurrency-Test (zwei nahezu gleichzeitige
    `record()`-Aufrufe für dieselbe Rechnung, deren Summe zusammen genau
    `total_amount` ergibt) läuft gegen die MySQL/PostgreSQL-Matrix
    (`docker compose -f docker-compose.yml -f docker-compose.mysql.yml up
    -d && php artisan migrate:fresh && composer test`, zusätzlich mit
    PostgreSQL als Standard-Verbindung) und bestätigt genau **einen**
    finalen `paid`-Übergang, keine verlorene Aktualisierung. Ergebnis in
    `task-T02.notes.md` dokumentiert (analog zu Change 1
    `task-T03.notes.md`). Kein `docker-compose.mysql.yml` im Repo
    vorhanden (bekannt aus Change 3); lokal gegen eine dedizierte
    PostgreSQL-Test-DB (`dog_school_test`) verifiziert, MySQL wird durch
    die bestehende CI-Matrix (`.github/workflows/ci.yml`) abgedeckt.
  - [x] `composer stan`/`composer compat-check` grün für die neue Datei.

## T03: `PaymentController` auf den neuen Service umstellen + Geschäftsregeln

- **Agent:** dev-php
- **Dateien:** `backend/app/Http/Controllers/Api/PaymentController.php`,
  `backend/app/Policies/PaymentPolicy.php`,
  `backend/tests/Feature/Api/InvoicePaymentApiTest.php` (neu),
  `backend/tests/Feature/PaymentApiTest.php` (Bestand, gezielte Anpassung)
- **Abhängigkeiten:** T02
- **Beschreibung:** `store()`, `markAsCompleted()` **und**
  `handlePaymentCaptureCompleted()` nutzen `InvoicePaymentRecorder` statt
  der bisherigen Inline-Logik (Zeile 93-102, 166-175 bzw. 336-351 werden
  ersetzt, nicht dupliziert — siehe `design.md` Context/Decision D2 zur
  vierten Fundstelle). `store()` prüft zusätzlich vor dem Aufruf des
  Recorders (siehe `design.md` Decision D3): Rechnungsstatus in
  `['sent','reminded','overdue']` (sonst 422), `amount >
  $invoice->remaining_balance` (sonst 422, Betrag formatiert in der
  Fehlermeldung). `PaymentPolicy::create()` erhält den zusätzlichen
  `Invoice`-Parameter und das Trainer-Scoping aus Decision D4;
  `PaymentController::store()` ruft entsprechend
  `$this->authorize('create', [Payment::class, $invoice])` auf (Invoice
  muss dafür **vor** der Autorisierung geladen werden, Reihenfolge im
  Controller entsprechend anpassen: erst `Invoice::findOrFail()`, dann
  `authorize()`, dann die beiden neuen 422-Prüfungen, dann `record()`).
  `handlePaymentCaptureCompleted()` ruft stattdessen
  `InvoicePaymentRecorder::completeExisting($payment)` auf, sobald der
  gefundene `Payment` noch nicht `completed` ist (der bisherige
  `remaining_balance <= 0.01`-Toleranzvergleich entfällt zugunsten des
  einheitlichen `syncStatus()`-Vergleichs, siehe `design.md`
  Risiko-Abschnitt); kein bestehender Test deckt diesen Pfad ab
  (verifiziert per Grep, siehe `design.md` Context), daher keine
  Regressionsgefahr durch die Umstellung. **Zusätzlich zwingend:** die
  bestehende `Customer`-Factory im `beforeEach` von
  `PaymentApiTest.php` (Zeile 13-22) legt Kunden **ohne** `trainer_id`
  an — mit Decision D4s Trainer-Scoping würde das den Test
  `'trainer can create payment'` (Zeile 116-138) brechen (403 statt 201,
  siehe `design.md` Context). Fix: `trainer_id => $this->trainer->id` in
  der `Customer`-Factory ergänzen (entweder zentral im `beforeEach`, wenn
  das die anderen 22 Tests nicht beeinflusst, sonst lokal nur in diesem
  einen Test), analog zum Muster in
  `backend/tests/Feature/InvoiceApiTest.php:159`. Der Test muss danach
  weiterhin exakt dasselbe fachliche Verhalten prüfen: ein Trainer kann
  eine Zahlung für **seinen eigenen** zugewiesenen Kunden erfassen — nur
  die Testdaten-Voraussetzung (jetzt mit `trainer_id`-Zuordnung) ändert
  sich, nicht die geprüfte Erwartung (`assertCreated()` bleibt).
- **Akzeptanzkriterien:**
  - [x] 22 der 23 bestehenden Tests in
    `backend/tests/Feature/PaymentApiTest.php` bleiben unverändert grün.
    Der Test `'trainer can create payment'` (Zeile 116-138) wird
    angepasst (`Customer`-Factory erhält `trainer_id`) und bleibt danach
    ebenfalls grün — geprüft wird weiterhin dasselbe fachliche Verhalten
    (Trainer erfasst Zahlung für eigenen Kunden), nicht mehr "unverändert
    im Testinhalt", sondern "angepasst und grün". **Abweichung von der
    Aufgabenbeschreibung, siehe `task-T03.notes.md`:** ein zweiter,
    bislang nicht genannter Test (`'invoice status updates to paid when
    fully paid'`) brach ebenfalls unter Decision D4s Trainer-Scoping (er
    nutzt eine eigene, per Factory erzeugte Rechnung ohne
    `trainer_id`-Zuordnung) und wurde minimal angepasst (Akteur `admin`
    statt `trainer`, fachliche Aussage unverändert).
  - [x] Neue Testdatei `InvoicePaymentApiTest.php`
    (`uses()->group('api', 'payment')`, `it()`-Stil) deckt ab: Zahlung
    für `draft`-Rechnung wird abgelehnt (422), Zahlung für `cancelled`-
    Rechnung wird abgelehnt (422), Zahlung über dem Restbetrag wird
    abgelehnt (422, mit Betrag in der Nachricht), Zahlung exakt in Höhe
    des Restbetrags wird akzeptiert und setzt Status auf `paid`, Trainer
    kann keine Zahlung für eine Rechnung eines fremden Kunden erfassen
    (403), Trainer kann Zahlung für eigene zugewiesene Kunden erfassen,
    Admin kann für jede Rechnung erfassen, **sowie** ein Webhook-Fall für
    `handlePaymentCaptureCompleted()`: eine abschließende Zahlung, deren
    Abschluss die Rechnung vollständig bezahlt, setzt den
    Rechnungsstatus über den neuen Service korrekt auf `paid`.
  - [x] `composer qa` grün (einzeln ausgeführt: `composer test`,
    `composer lint`, `composer stan`, `composer compat-check`).

## T04: `InvoiceController::markAsPaid()` entfernen

- **Agent:** dev-php
- **Dateien:**
  `backend/app/Http/Controllers/Api/InvoiceController.php`,
  `backend/app/Policies/InvoicePolicy.php`, `backend/routes/api.php`,
  `backend/tests/Feature/InvoiceApiTest.php`
- **Abhängigkeiten:** T03 (die neue Eingabemaske muss den fachlichen
  Ersatz bereits leisten können, bevor der alte Weg entfernt wird)
- **Beschreibung:** `InvoiceController::markAsPaid()` (Zeile 218-240),
  `InvoicePolicy::markAsPaid()` (Zeile 110-127) und die Route
  `POST /invoices/{invoice}/mark-paid` (`routes/api.php:182`) vollständig
  entfernen (siehe `design.md` Decision D1). Die vier zugehörigen Tests
  in `InvoiceApiTest.php:410-474` entfernen (Funktionalität entfällt
  ersatzlos, kein Ersatztest nötig — das Verhalten der neuen Eingabemaske
  wird bereits durch T03s `InvoicePaymentApiTest.php` abgedeckt).
- **Akzeptanzkriterien:**
  - [x] `grep -rn "markAsPaid\|mark-paid"` in `backend/app/` und
    `backend/routes/` liefert keine Treffer mehr.
  - [x] `POST /api/v1/invoices/{id}/mark-paid` liefert HTTP 404 (Route
    existiert nicht mehr).
  - [x] `composer qa` grün, keine verwaisten Referenzen.

## T05: Neuer Dialog `InvoicePaymentDialog.vue`

- **Agent:** dev-typescript
- **Dateien:** `frontend/src/components/InvoicePaymentDialog.vue` (neu)
- **Abhängigkeiten:** keine (nutzt nur den bereits heute stabilen
  `PaymentResource`-API-Vertrag: `remainingBalance`, `totalAmount`,
  `invoiceNumber` — unabhängig von T01-T04 entwickelbar, Integration
  erfolgt erst in T06)
- **Beschreibung:** Neue Komponente nach dem Muster von
  `frontend/src/components/InvoiceSendDialog.vue` (`@headlessui/vue`,
  `TransitionRoot`/`Dialog`/`DialogPanel`/`DialogTitle`, reine
  Props/Events, **kein** `apiClient`-Import). Props: `isOpen: boolean`,
  `invoice?: any`. Formularfelder: Betrag (Zahl, vorbelegt mit
  `invoice.remainingBalance`, Client-seitige Validierung `> 0` und
  `<= invoice.remainingBalance`, Fehlertext bei Verstoß), Datum
  (vorbelegt mit heute, `<= heute`), Zahlungsart (Select mit den
  bestehenden Werten `cash, bank_transfer, paypal, stripe, credit_card` —
  deutsche Labels), optionale Referenz/Notiz (Textfeld, `notes`). Button
  "Zahlung erfassen" emittiert `record-payment` mit dem Formular-Payload
  (`{ amount, paymentDate, paymentMethod, notes }`); Button "Volle
  Restsumme" setzt das Betragsfeld auf `invoice.remainingBalance` zurück
  (Ersatz für die entfernte "Als bezahlt markieren"-Komfortfunktion,
  siehe `design.md` Decision D1). `close`-Event für Abbrechen/Schließen.
  Ladezustand über eine neue `isSubmitting`-Prop (Button disabled während
  des API-Aufrufs, der in `InvoicesView.vue` liegt, siehe T06).
- **Akzeptanzkriterien:**
  - [x] `frontend/src/components/InvoicePaymentDialog.test.ts` (neu)
    prüft: Vorbelegung des Betragsfelds mit `remainingBalance`,
    Client-Validierung lehnt Betrag `> remainingBalance` und `<= 0` ab
    (Button bleibt disabled bzw. Fehlertext erscheint), `record-payment`
    wird mit korrektem Payload emittiert, "Volle Restsumme"-Button setzt
    den Betrag zurück, kein `apiClient`-Import in der Komponente
    (Grep-Check im Test oder Reviewer-Hinweis).
  - [x] `npm run lint` und `npx vitest run` grün für die neue Datei.

## T06: `InvoicesView.vue` — Dialog verdrahten, `markAsPaid` entfernen, Teilzahlungs-Badge

- **Agent:** dev-typescript
- **Dateien:** `frontend/src/views/invoices/InvoicesView.vue`,
  `frontend/src/views/invoices/InvoicesView.test.ts`
- **Abhängigkeiten:** T03, T04 (Backend-Endpunkt/-Vertrag muss stehen),
  T05 (Dialog-Komponente)
- **Beschreibung:** `markAsPaid()`-Handler, `@mark-paid`-Listener und
  jede verbleibende Referenz auf `/mark-paid` entfernen. Neuer Ref
  `paymentDialogInvoice` (eigener Ref, **nicht** `selectedInvoice`,
  gleiche Begründung wie `sendDialogInvoice`, siehe `design.md` Decision
  D6), `showPaymentDialog`, `openPaymentDialog(invoice)`/
  `closePaymentDialog()`. Neuer Handler `recordPayment(invoice, payload)`:
  `POST /api/v1/payments` mit `{ invoiceId: invoice.id, amount:
  payload.amount, paymentDate: payload.paymentDate, paymentMethod:
  payload.paymentMethod, notes: payload.notes || undefined, status:
  'completed' }` (explizites `status: 'completed'`, siehe `design.md`
  Decision D6), `try/catch` mit `handleApiError()`/`showSuccess()`; bei
  Erfolg `loadInvoices()` + Dialog schließen + Detail-Modal schließen
  falls offen (Muster wie `finalizeInvoice()`); bei 422-Fehler
  (Überzahlung/ungültiger Status) bleibt der Dialog **offen** (analog zum
  Send-Dialog-Fehlerverhalten), damit der Betrag korrigiert werden kann.
  Neuer `canRecordPayment(invoice)`-Helper: `!authStore.isCustomer &&
  ['sent','reminded','overdue'].includes(invoice.status) &&
  invoice.remainingBalance > 0`. Neuer Listenzeilen-Button "Zahlung
  erfassen" (bislang gab es dafür **keinen** Button in der Tabelle, nur
  im Detail-Modal — siehe `design.md` Context). Neue Teilzahlungs-Anzeige
  in der Status-Spalte: wenn `invoice.totalPaid > 0 &&
  invoice.status !== 'paid'`, zusätzliche Zeile "{{ totalPaid }} von
  {{ totalAmount }} bezahlt".
- **Akzeptanzkriterien:**
  - [x] `InvoicesView.test.ts`: `markAsPaid`-bezogene Erwartungen entfernt,
    neue Tests für `canRecordPayment()`-Sichtbarkeit je Status, für
    `recordPayment()` (Erfolgsfall: Reload + Dialog zu; 422-Fall: Dialog
    bleibt offen), für die Teilzahlungs-Anzeige bei `totalPaid > 0`.
  - [x] `npm run lint`, `npx vitest run`, `npm run build` grün.

## T07: `InvoiceDetailModal.vue` — Button-Wechsel, Bugfix, Restbetrag-Zusammenfassung

- **Agent:** dev-typescript
- **Dateien:** `frontend/src/components/InvoiceDetailModal.vue`,
  `frontend/src/components/InvoiceDetailModal.test.ts`
- **Abhängigkeiten:** T06 (Emit-Vertrag `record-payment` muss auf
  `InvoicesView.vue`-Seite bereits verdrahtet sein, damit der Test gegen
  ein vollständiges Verhalten läuft)
- **Beschreibung:** `mark-paid`-Emit und den zugehörigen Button entfernen
  (Zeile 163-165, 205), `canMarkAsPaid()` entfernen. Neuer Button
  "Zahlung erfassen" mit `v-if="canRecordPayment(invoice)"` (gleiche
  Bedingung wie in T06, lokal dupliziert nach dem etablierten Muster
  dieser beiden Dateien — siehe `design.md` Context zur bewussten
  Nicht-Konsolidierung aus Change 1 Non-Goals), emittiert
  `record-payment`. **Bugfix:** Zahlungen-Block (Zeile 134-144) liest
  `payment.paymentDate`/`payment.paymentMethod` (camelCase) statt der
  bisherigen, nie korrekt auflösenden `payment.payment_date`/
  `payment.payment_method`. Neue Zusammenfassungszeile über der
  Zahlungsliste: "Bezahlt: {{ formatCurrency(invoice.totalPaid) }} von
  {{ formatCurrency(invoice.totalAmount) }} — Rest: {{
  formatCurrency(invoice.remainingBalance) }}", nur sichtbar wenn
  `invoice.payments?.length > 0`. Zahlungszeilen zeigen zusätzlich
  `payment.notes`, falls vorhanden.
- **Akzeptanzkriterien:**
  - [x] `InvoiceDetailModal.test.ts`: Test für den entfernten
    "Als bezahlt markieren"-Button ersetzt durch einen Test, der den
    neuen "Zahlung erfassen"-Button für `sent`/`reminded`/`overdue` prüft
    (und dessen Abwesenheit für `draft`/`paid`/`cancelled`), Test für die
    korrekte Anzeige von `paymentDate`/`paymentMethod` in der
    Zahlungsliste (Regressionstest für den Bugfix), Test für die
    Restbetrag-Zusammenfassungszeile.
  - [x] `npm run lint`, `npx vitest run`, `npm run build` grün.

## T08: Cross-Cutting QA-Durchlauf

- **Agent:** dev-php
- **Dateien:** keine Code-Änderungen — reiner Verifikationstask
- **Abhängigkeiten:** T01-T07
- **Beschreibung:** Vollständiger Pre-Flight-Check gemäß CLAUDE.md
  Abschnitt 7.1 nach Abschluss aller Tasks: `composer qa`, `npm run
  lint`, `npm run test`, `npm run build`, sowie der MySQL/PostgreSQL-
  Migrations-Testlauf (`docker compose -f docker-compose.yml -f
  docker-compose.mysql.yml up -d && php artisan migrate:fresh &&
  composer test`) inklusive des in T02 geforderten
  PostgreSQL-Concurrency-Tests. Ergebnisse in `task-T08.notes.md`
  dokumentieren (Befehle + Zusammenfassung, kein bloßer Verweis auf
  frühere Einzel-Task-Notes).
- **Akzeptanzkriterien:**
  - [x] Alle vier Backend-Kommandos (`composer test`, `composer lint`,
    `composer stan`, `composer compat-check`) grün, einzeln ausgeführt
    dokumentiert (analog zur in Change 2 `acceptance.md` begründeten
    Praxis, `composer qa` bei Timeout-Risiko in Einzelschritte
    aufzuteilen).
  - [x] `npm run lint`, `npx vitest run`, `npm run build` grün, keine
    neuen Warnings gegenüber dem Bestand.
  - [x] MySQL/PostgreSQL-Migrationslauf inkl. Concurrency-Test grün,
    Ergebnis dokumentiert.

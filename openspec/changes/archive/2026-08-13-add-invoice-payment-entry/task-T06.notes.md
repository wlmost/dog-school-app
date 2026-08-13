# Task T06 — Notes

## Umsetzung

**`frontend/src/views/invoices/InvoicesView.vue`**

- `markAsPaid()`-Handler, `@mark-paid="markAsPaid"`-Listener auf
  `InvoiceDetailModal` sowie der `POST /api/v1/invoices/{id}/mark-paid`-Aufruf
  vollständig entfernt (verifiziert per Grep, keine Treffer mehr für
  `markAsPaid|mark-paid` in Datei und Test).
- Neuer, eigener Ref `paymentDialogInvoice` (nicht `selectedInvoice`,
  identische Begründung/Kommentar wie bei `sendDialogInvoice`, design.md
  Decision D6), `showPaymentDialog`, `openPaymentDialog(invoice)`/
  `closePaymentDialog()` — Muster 1:1 von `openSendDialog`/`closeSendDialog`
  übernommen.
- Neuer Handler `recordPayment(invoice, payload)`:
  `POST /api/v1/payments` mit `{ invoiceId: invoice.id, amount:
  payload.amount, paymentDate: payload.paymentDate, paymentMethod:
  payload.paymentMethod, notes: payload.notes || undefined, status:
  'completed' }`. `isRecordingPayment`-Ref wird vor dem Aufruf auf `true`
  gesetzt und im `finally`-Block wieder zurückgesetzt, gebunden an
  `InvoicePaymentDialog`s `is-submitting`-Prop. Bei Erfolg:
  `await loadInvoices()`, `closePaymentDialog()`, danach `closeDetailModal()`
  falls `showDetailModal.value` (Muster wie `finalizeInvoice()`/
  `deleteInvoice()`/`cancelInvoice()`), `showSuccess(...)`. Bei Fehler
  (inkl. 422 wegen Überzahlung/ungültigem Status): `handleApiError(...)`,
  Dialog bleibt offen — kein `closePaymentDialog()`-Aufruf im `catch`-Zweig,
  analog zum Fehlerverhalten von `sendInvoiceEmail()`.
- Neuer `canRecordPayment(invoice)`-Helper mit eigener
  `PAYABLE_STATUSES`-Konstante (`['sent', 'reminded', 'overdue']`, bewusst
  separat von `SENDABLE_STATUSES` benannt, auch wenn die Werte aktuell
  identisch sind — spiegelt die Namensgebung von
  `PaymentController::store()`s `PAYABLE_STATUSES`, siehe design.md
  Decision D3): `!authStore.isCustomer && PAYABLE_STATUSES.includes(invoice.status)
  && invoice.remainingBalance > 0`.
- Neuer Listenzeilen-Button "Zahlung erfassen" (`v-if="canRecordPayment(invoice)"`,
  `@click="openPaymentDialog(invoice)"`) zwischen "Senden" und "Stornieren"
  eingefügt.
- Neue Teilzahlungs-Anzeige in der Status-Spalte: `v-if="invoice.totalPaid > 0
  && invoice.status !== 'paid'"` zeigt "{{ formatCurrency(invoice.totalPaid) }}
  von {{ formatCurrency(invoice.totalAmount) }} bezahlt" (gleiches
  `formatCurrency()` wie an den übrigen Stellen der Datei).
- `InvoicePaymentDialog` importiert und analog zu `InvoiceSendDialog` gemountet
  (`:is-open`, `:invoice`, `:is-submitting`, `@close="closePaymentDialog"`,
  `@record-payment="(payload) => recordPayment(paymentDialogInvoice, payload)"`).
- **Vorbereitung für T07:** `InvoiceDetailModal`-Usage erhält zusätzlich
  `@record-payment="openPaymentDialog"` (analog zu `@send="openSendDialog"`),
  obwohl `InvoiceDetailModal.vue` dieses Event heute noch nicht emittiert
  (das kommt erst mit dem neuen "Zahlung erfassen"-Button in T07). Diese
  Vorab-Verdrahtung ist explizit durch T07s Abhängigkeitsvermerk in
  `tasks.md` gefordert ("Emit-Vertrag `record-payment` muss auf
  `InvoicesView.vue`-Seite bereits verdrahtet sein"). Ein entsprechender
  Test (`öffnet den InvoicePaymentDialog, wenn InvoiceDetailModal ein
  record-payment-Event emittiert`) deckt das bereits jetzt ab, indem er das
  Event direkt über den Stub emittiert.

**`frontend/src/views/invoices/InvoicesView.test.ts`**

- `makeInvoice()`-Fixture um `remainingBalance: 100`/`totalPaid: 0` als
  Default ergänzt (verifiziert, dass keine bestehende Assertion dadurch
  bricht — u. a. die exakten `toEqual(['PDF'])`-Prüfungen für `cancelled`/
  Kunden-Ansicht, da `canRecordPayment()` dort ohnehin `false` liefert).
- `InvoiceDetailModal`-Stub: `mark-paid` aus `emits` entfernt, `record-payment`
  ergänzt. Neuer `InvoicePaymentDialog`-Stub (`isOpen`/`invoice`/`isSubmitting`
  Props, `close`/`record-payment` Emits).
- Neue Testblöcke:
  - `"Zahlung erfassen"-Button (canRecordPayment)`: `it.each` für
    `sent`/`reminded`/`overdue` (Button sichtbar bei offenem Restbetrag),
    `it.each` für `draft`/`paid`/`cancelled` (Button versteckt), separater
    Test für `remainingBalance === 0` sowie für die Kunden-Ansicht.
  - `InvoicePaymentDialog-Interaktion`: Öffnen mit korrekter Rechnung,
    Erfolgsfall (`POST`-Payload exakt geprüft, Reload via zweitem
    `apiClient.get`-Aufruf, Dialog schließt, `showSuccess`), separater Test
    für eine ausgefüllte Notiz, 422-Fehlerfall (`handleApiError` mit dem
    Original-Error, Dialog bleibt offen, **kein** zweiter `get`-Aufruf),
    `close`-Event, sowie das oben beschriebene Vorbereitungs-Wiring für T07.
  - `Teilzahlungs-Anzeige`: Anzeige bei `totalPaid > 0`, keine Anzeige bei
    `totalPaid === 0`, keine Anzeige mehr bei `status === 'paid'`.

## Gefundener und behobener Fehler während der Implementierung

Der erste Entwurf band den Handler im Template mit
`@record-payment="(payload) => recordPayment(paymentDialogInvoice.value, payload)"`.
Das führte zu Laufzeitfehlern (`Cannot read properties of null/undefined
(reading 'value')`), weil `<script setup>` Top-Level-Refs im Template
automatisch entpackt — `paymentDialogInvoice` referenziert im Template also
bereits den aktuellen Wert, nicht den Ref selbst. Korrigiert auf
`recordPayment(paymentDialogInvoice, payload)` (ohne `.value`). Dieser
Fehler wurde durch die neuen Tests selbst aufgedeckt (11 fehlgeschlagene
Tests inkl. kaskadierender Fehlschläge in unabhängigen Bestandstests durch
nicht konsumierte `mockResolvedValueOnce`-Warteschlangen) — nach der
Korrektur alle 43 Tests der Datei grün.

## QA (Docker-Container `dog-school-node`, `/var/www/html/frontend`)

```
npx vitest run src/views/invoices/InvoicesView.test.ts   # 43/43 grün
npx vitest run                                             # 295/295 grün (Vollsuite, keine Regression;
                                                             # 278 aus T05-Baseline + 17 neue Tests in diesem File)
npx eslint src/views/invoices/InvoicesView.vue src/views/invoices/InvoicesView.test.ts
  # 0 Fehler, 142 Warnungen (ausschließlich @typescript-eslint/no-explicit-any,
  # vue/max-attributes-per-line, vue/attributes-order,
  # vue/singleline-html-element-content-newline — bereits vor diesem Change
  # in der Datei vorhandene Regelklassen, projektweit auf `warn`)
npm run lint                                                # Exit 0, 0 Fehler (3176 projektweite Bestands-Warnungen)
npx vue-tsc -b --noEmit                                     # 0 Fehler
npm run build                                                # erfolgreich, keine Warnungen
```

## Vollständigkeitsprüfung

```
grep -rn "markAsPaid|mark-paid" frontend/src/views/invoices/InvoicesView.vue frontend/src/views/invoices/InvoicesView.test.ts
  # keine Treffer
```

`frontend/src/components/InvoiceDetailModal.vue`/`.test.ts` enthalten
weiterhin `markAsPaid`/`mark-paid`-Referenzen — das ist erwartet und **nicht**
Teil des T06-Scopes (dieser gehört laut `tasks.md` explizit zu T07).

## Offene Punkte / Annahmen

- Keine Backend-Änderungen (T01-T04 bereits abgeschlossen, T06 ist reiner
  Frontend-Task).
- `PAYABLE_STATUSES` in `InvoicesView.vue` dupliziert bewusst die Werte von
  `SENDABLE_STATUSES` (beide `['sent', 'reminded', 'overdue']`) statt sie
  wiederzuverwenden — konsistent mit der im Change etablierten Praxis
  bewusster kleiner Duplizierung zugunsten unabhängiger, fachlich benannter
  Konstanten (vgl. design.md Decision zu T07s lokaler Duplizierung von
  `canRecordPayment()`), da beide Listen aus unabhängigen Gründen entstehen
  (Senden vs. Zahlung erfassen) und zukünftig auseinanderlaufen könnten.
- T07 ist der nächste Task und benötigt laut `tasks.md`-Abhängigkeitsvermerk
  das in T06 bereits verdrahtete `@record-payment`-Listening auf
  `InvoiceDetailModal` — das ist umgesetzt (siehe oben), sodass T07 nur noch
  den Button/das Emit in `InvoiceDetailModal.vue` selbst ergänzen muss.

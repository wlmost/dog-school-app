# Task T05: Neuer Dialog `InvoicePaymentDialog.vue`

## Umgesetzt

- `frontend/src/components/InvoicePaymentDialog.vue` (neu) — reine
  Presentation-Layer-Komponente nach dem Muster von
  `frontend/src/components/InvoiceSendDialog.vue` (`@headlessui/vue`,
  `TransitionRoot`/`Dialog`/`DialogPanel`/`DialogTitle`), kein
  `apiClient`-Import, kein eigener API-Aufruf.
  - Props: `isOpen: boolean`, `invoice?: any` (bewusst `any`, identisch
    zum Muster in `InvoiceSendDialog.vue`, um Konsistenz mit dem
    etablierten Dialog-Pattern zu wahren — `@typescript-eslint/no-explicit-any`
    ist projektweit auf `warn` heruntergestuft, siehe `eslint.config.ts`),
    `isSubmitting: boolean` (Pflicht-Prop, kein Default, wie in tasks.md
    gefordert).
  - Formularfelder: Betrag (`v-model.number`, vorbelegt mit
    `invoice.remainingBalance`), Datum (`type="date"`, `:max="today"`,
    vorbelegt mit dem heutigen Tag), Zahlungsart (`<select>` mit den
    bestehenden Enum-Werten `cash, bank_transfer, paypal, stripe,
    credit_card` und deutschen Labels "Bar", "Überweisung", "PayPal",
    "Stripe", "Kreditkarte" — im Projekt existierten für dieses Enum
    bislang keine deutschen Labels, siehe Grep-Ergebnis unten), optionale
    Referenz/Notiz (`<textarea v-model="notes">`).
  - Client-Validierung: `amountError`-Computed lehnt `amount <= 0` und
    `amount > invoice.remainingBalance` mit Fehlertext ab;
    `dateError`-Computed lehnt ein leeres oder zukünftiges Datum ab.
    `isValid` kombiniert beide; Submit-Button ist `:disabled="!isValid ||
    isSubmitting"`.
  - "Volle Restsumme"-Button (`type="button"`) setzt `amount` auf
    `invoice.remainingBalance` zurück, ohne das Formular abzusenden.
  - `submit()` emittiert `record-payment` mit exakt `{ amount,
    paymentDate, paymentMethod, notes }` (nur wenn `isValid`).
  - `close`-Event über den X-Button in der `DialogTitle` sowie den
    "Abbrechen"-Button.
  - Alle interaktiven Buttons (`Zahlung erfassen`, `Volle Restsumme`,
    `Abbrechen`, X-Schließen) sind `:disabled="isSubmitting"` (bzw. für
    den Submit-Button zusätzlich `!isValid`).
  - Formular-Reset (`resetForm()`) läuft über einen `watch` auf
    `[props.isOpen, props.invoice]` mit `immediate: true`, damit die
    Vorbelegung bei jedem Öffnen bzw. bei einem Rechnungswechsel korrekt
    neu gesetzt wird (relevant für T06: Dialog bleibt bei einem
    422-Fehler offen, siehe `design.md`).

- `frontend/src/components/InvoicePaymentDialog.test.ts` (neu) — 9 Tests:
  Vorbelegung des Betragsfelds, Ablehnung von Betrag `> remainingBalance`
  und `<= 0` (Fehlertext + disabled Submit-Button), korrektes
  `record-payment`-Payload, "Volle Restsumme"-Reset, `close`-Emit,
  Buttons disabled bei `isSubmitting === true`, kein Rendering bei
  `isOpen === false`, sowie ein expliziter Grep-Check gegen den
  Komponenten-Quelltext auf `apiClient` (über einen Vite-`?raw`-Import,
  siehe Hinweis unten — kein `node:fs`, da `frontend/src/**` keine
  Node-Typen im `tsconfig.app.json` einbindet, siehe Abschnitt
  "Abweichungen").

## Abweichungen von der ursprünglichen Testplanung

- Der ursprünglich geplante Grep-Check über `node:fs`/`node:path` (Lesen
  der `.vue`-Datei als Text) scheiterte an `vue-tsc -b`: `tsconfig.app.json`
  setzt `"types": ["vite/client"]` (kein `"node"`), daher sind
  `node:fs`/`node:path`/`process` dort nicht typisiert — kein Präzedenzfall
  im restlichen Frontend-Testbestand für Node-Builtins in `*.test.ts`
  (Grep bestätigt: keine Treffer außer der hier zunächst geschriebenen
  Datei). Stattdessen wird der Komponenten-Quelltext über Vites
  eingebauten `?raw`-Import-Suffix geladen (`import invoicePaymentDialogSource
  from '@/components/InvoicePaymentDialog.vue?raw'`), der bereits in
  `vite/client.d.ts` typisiert ist (`declare module '*?raw'`) — funktioniert
  browser-/happy-dom-seitig ohne Node-API und ist type-sicher.

## Lokale Checks (Docker-Container `dog-school-node`, `/var/www/html/frontend`)

```
npx vitest run src/components/InvoicePaymentDialog.test.ts   # 9/9 grün
npx vitest run                                                # 278/278 grün (Vollsuite, keine Regression)
npx eslint src/components/InvoicePaymentDialog.vue src/components/InvoicePaymentDialog.test.ts
  # 0 Fehler, 45 Warnungen (ausschließlich vue/max-attributes-per-line,
  # vue/attributes-order, vue/singleline-html-element-content-newline,
  # vue/html-self-closing, @typescript-eslint/no-explicit-any — dieselben
  # Regelklassen, die projektweit gemäß eslint.config.ts auf `warn`
  # heruntergestuft sind; InvoiceSendDialog.vue als Referenzdatei hat
  # dieselbe Art von Warnungen (26 Stück), siehe Vergleichslauf)
npm run lint                                                   # Exit 0, 0 Fehler (3167 projektweite Bestands-Warnungen)
npx vue-tsc -b --noEmit                                        # 0 Fehler
npm run build                                                  # erfolgreich, InvoicesView-Chunk unverändert groß
  # (InvoicePaymentDialog.vue ist noch nicht gemountet — Integration
  # erfolgt erst in T06)
```

## Anmerkungen für T06

- Payload-Vertrag `{ amount, paymentDate, paymentMethod, notes }` ist
  exakt wie in `tasks.md` T05/T06 beschrieben; `notes` wird hier immer
  als `string` emittiert (auch leer), die Umwandlung zu `undefined` bei
  leerem String liegt laut `design.md` Decision D6 explizit in
  `InvoicesView.vue`s `recordPayment()`-Handler (`notes: payload.notes ||
  undefined`), nicht in dieser Komponente.
- Die Komponente setzt bei jedem Öffnen (`isOpen` wechselt zu `true`)
  bzw. bei jedem `invoice`-Wechsel automatisch zurück auf `remainingBalance`/
  heute/`cash`/leere Notiz — T06 muss dafür keinen zusätzlichen Reset in
  `InvoicesView.vue` vorsehen.

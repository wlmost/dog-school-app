# T04: `InvoiceSendDialog.vue` — neue Dialog-Komponente

## Status: erledigt

## Geänderte/neue Dateien

- `frontend/src/components/InvoiceSendDialog.vue` (neu)
- `frontend/src/components/InvoiceSendDialog.test.ts` (neu)

Ausschließlich diese beiden Dateien angefasst, wie im Task-Scope
vorgegeben (parallel zu T01–T03 Backend, keine Berührung von
`InvoicesView.vue`/`InvoiceDetailModal.vue` — folgt in T05/T06).

## Umsetzung

- Struktur/Styling 1:1 nach dem `@headlessui/vue`-Muster von
  `frontend/src/components/InvoiceDetailModal.vue` (`TransitionRoot`,
  `TransitionChild`, `Dialog`, `DialogPanel`, `DialogTitle`,
  `btn`-Utility-Klasse, Dark-Mode-Klassen).
- Props exakt wie in `tasks.md` T04 vorgegeben: `isOpen: boolean`,
  `invoice?: any` (Typ `any` bewusst konsistent mit
  `InvoiceDetailModal.vue:198`, da dort ebenfalls kein dedizierter
  Invoice-Typ existiert — kein neuer Präzedenzfall).
- Emits exakt wie vorgegeben: `close: []`, `download: [invoice: any]`,
  `'send-email': [invoice: any]`.
- **Kein `hasEmail`-Zweig** (User-Gate-1-Entscheidung 4, `design.md`
  Decision D8): Template zeigt beide Optionen ("Aus der App versenden",
  "Manuell versenden (PDF herunterladen)") bedingungslos, sobald
  `invoice` gesetzt ist. Der Hinweistext zur E-Mail-Adresse
  (`invoice.customer?.user?.email`) wird unverändert angezeigt, auch
  wenn der Wert leer/undefined ist — analog zur bestehenden Anzeige in
  `InvoiceDetailModal.vue:85`.
- Rechnungsnummer als Kontext-Info direkt unter dem Dialogtitel.
- "Aus der App versenden" → `emit('send-email', invoice)`,
  "Manuell versenden" → `emit('download', invoice)`,
  "Schließen" (Titel-X-Icon und Footer-Button) → `emit('close')`.
  **Kein** `apiClient`-Import, kein eigener Loading-/Error-State — die
  Komponente ist reines Presentation-Layer (siehe `design.md`
  Decision D8). Der optionale `sending`-Prop-Gedanke aus `tasks.md`
  wurde nicht umgesetzt: da T05 explizit dokumentiert, dass ein
  fehlgeschlagener Versand den Dialog offen lässt und `InvoicesView.vue`
  Erfolg/Fehler per Toast kommuniziert, ist ein zusätzlicher
  `sending`-Zustand für dieses Verhalten nicht nötig (YAGNI) — kann bei
  Bedarf in T05 ergänzt werden, ohne die hier definierten Props/Emits zu
  brechen.

## Tests

`InvoiceSendDialog.test.ts`, Stil nach `InvoiceDetailModal.test.ts`
(identische HeadlessUI-Stubs, `makeInvoice()`-Factory,
`findButton()`-Helper). Abgedeckt:

- beide Optionen sichtbar mit gesetzter Kunden-E-Mail
- beide Optionen sichtbar **ohne** Kunden-E-Mail (kein `hasEmail`-Zweig)
- Rechnungsnummer wird als Kontext-Info angezeigt
- Kunden-E-Mail wird als Hinweistext angezeigt
- Klick "Aus der App versenden" → `send-email`-Event mit dem
  `invoice`-Objekt, **kein** `download`- oder `close`-Event
- Klick "Manuell versenden" → `download`-Event mit dem `invoice`-Objekt
- Klick "Schließen" → `close`-Event
- `isOpen=false` rendert keine Buttons

8 Tests, alle grün.

## QA (Docker-Container `dog-school-node`, da lokales `node_modules`
auf dem Host für `linux-arm64` statt `darwin-arm64` gebaut ist)

```
docker exec dog-school-node sh -c "cd /var/www/html/frontend && npx vitest run"
# 23 Test-Dateien, 257 Tests, alle grün (inkl. der 8 neuen)

docker exec dog-school-node sh -c "cd /var/www/html/frontend && npm run lint"
# Exit 0, 0 Fehler. Für InvoiceSendDialog.vue ausschließlich Warnings
# desselben Typs (vue/max-attributes-per-line, vue/attributes-order,
# vue/singleline-html-element-content-newline), wie sie im gesamten
# Bestandscode (u. a. InvoiceDetailModal.vue) bereits vorhanden sind.
# Kein neuer Warnungstyp, kein Fehler eingeführt — konsistent mit dem
# etablierten Stil-Baseline des Projekts, kein Auto-Fix angewendet
# (siehe MEMORY.md: Bestandscode nicht ungefragt mechanisch reformatieren).

docker exec dog-school-node sh -c "cd /var/www/html/frontend && npm run build"
# vue-tsc -b + vite build, Exit 0, keine Typfehler.
```

## Anmerkung für T05/T06

`InvoiceSendDialog` ist noch **nirgends gemountet** (bewusst, siehe
Task-Scope). T05 bindet die Komponente in `InvoicesView.vue` ein, T06
ergänzt das `send`-Event in `InvoiceDetailModal.vue`. Props/Emits sind
exakt wie in `design.md` Decision D8 und `tasks.md` T05 spezifiziert
(`:is-open`, `:invoice`, `@close`, `@download`, `@send-email`).

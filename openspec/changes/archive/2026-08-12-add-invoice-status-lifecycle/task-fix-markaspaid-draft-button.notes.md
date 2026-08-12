# Fix: "Als bezahlt markieren"-Button für Entwürfe sichtbar (Review Muss-Befund 2, Frontend-Teil)

**Bezug:** `openspec/changes/add-invoice-status-lifecycle/change-review.md`,
Muss-Befund 2. Der Backend-Teil ist bereits gefixt
(`task-fix-markaspaid-draft.notes.md`): `POST /invoices/{id}/mark-paid`
liefert HTTP 422 für `status === 'draft'`. Dieser Fix behebt den
verbleibenden Frontend-Teil: der Button war weiterhin für Entwürfe sichtbar,
ein Klick hätte nur noch zu einem Fehler-Toast geführt statt sauberer UX.

## Fix

`frontend/src/components/InvoiceDetailModal.vue`:

- Neue Helper-Funktion `canMarkAsPaid(invoice)` analog zum bestehenden Stil
  von `canDelete()`/`canFinalize()`/`canSend()`/`canCancel()` (T08):
  ```ts
  function canMarkAsPaid(invoice: any): boolean {
    return !authStore.isCustomer && invoice.status === 'sent'
  }
  ```
  Kommentar verweist auf `InvoiceController::markAsPaid()` als
  Quelle der Wahrheit (Statusprüfung `draft` → 422).
- Der Button (vormals Inline-Bedingung
  `invoice.status === 'draft' || invoice.status === 'sent'`) nutzt jetzt
  `v-if="canMarkAsPaid(invoice)"`. Da `draft` aus der erlaubten Menge
  entfernt wurde, bleibt nur `sent` — unverändertes Verhalten für Entwürfe,
  die bereits `finalize()` durchlaufen haben.

**Scope bewusst minimal gehalten:** Das Backend erlaubt `markAsPaid()`
technisch auch für `reminded`/`overdue` (jeder Nicht-`draft`,
Nicht-bereits-bezahlt-Status). Die Button-Sichtbarkeit für diese Status war
vor diesem Fix bereits nicht gegeben (nur `draft`/`sent`) und ist nicht Teil
des gemeldeten Bugs — daher unverändert gelassen, um den Fix auf den
gemeldeten Befund zu beschränken.

**`InvoicesView.vue` geprüft:** Wie in `task-T07`-Notes dokumentiert, wurde
der "Als bezahlt markieren"-Button dort bereits vollständig aus der
Aktionsspalte entfernt. Zeile 134 (`@mark-paid="markAsPaid"`) verdrahtet
lediglich das vom `InvoiceDetailModal`-Emit ausgelöste Event mit dem
API-Aufruf — kein eigener Button in der Tabelle. Kein weiterer Fix nötig.

## Tests

`frontend/src/components/InvoiceDetailModal.test.ts`:

- Bestehenden Test im `Status "draft"`-Block angepasst: erwartet jetzt
  explizit, dass `'Als bezahlt markieren'` **nicht** enthalten ist
  (vorher: enthalten — das war der Bug).
- Neuer Test `zeigt keinen "Als bezahlt markieren"-Button (...)`: verifiziert
  über `findActionButton()`, dass der Button für `status: 'draft'` gar nicht
  im DOM ist (`toBeUndefined()`).
- Bestehenden Test im `Status "sent"`-Block um explizite Assertion
  `expect(buttons).toContain('Als bezahlt markieren')` ergänzt (vorher nur
  implizit über den Testtitel behauptet, nicht geprüft).

## QA

Ausgeführt im `node`-Container (`docker compose exec node ...`), da lokale
`node_modules` für Linux gebaut sind (Plattform-Mismatch mit macOS
Host-`npm`):

- `npm run lint` — 0 Errors (3092 Warnings, alle bestandscode-bedingt,
  keine neuen).
- `npm run test` — 22 Testdateien, 249 Tests grün, inkl. 16 Tests in
  `InvoiceDetailModal.test.ts`.
- `npm run build` (`vue-tsc -b && vite build`) — erfolgreich, keine
  Typfehler, keine Warnings.

## Geänderte Dateien

- `frontend/src/components/InvoiceDetailModal.vue` — `canMarkAsPaid()`
  ergänzt, Button-Bedingung umgestellt.
- `frontend/src/components/InvoiceDetailModal.test.ts` — Test für
  `status: 'draft'` korrigiert + neuer expliziter Negativ-Test, Test für
  `status: 'sent'` um explizite Positiv-Assertion ergänzt.

# T08 — `InvoiceDetailModal.vue`: gleiche Button-/Status-Logik im Detail-Modal

## Umgesetzte Dateien

- `frontend/src/components/InvoiceDetailModal.vue`
- `frontend/src/views/invoices/InvoicesView.vue` (neue Event-Bindungen)
- Neu: `frontend/src/components/InvoiceDetailModal.test.ts` (Vitest,
  Stilvorbild `frontend/src/components/CustomerFormModal.test.ts` für
  die HeadlessUI-Stubs, `frontend/src/views/invoices/InvoicesView.test.ts`
  aus T07 für die Fixture-/Assertion-Struktur je Status)

## Umsetzung

- **`getStatusClass()`/`getStatusLabel()`** (`InvoiceDetailModal.vue:274-296`):
  `reminded` ergänzt — exakt dieselben Klassen/Label wie in
  `InvoicesView.vue::invoiceStatusClass()`/`invoiceStatusLabel()` aus T07
  (`bg-orange-100 text-orange-800 dark:bg-orange-900 dark:text-orange-200` /
  `'Gemahnt'`).
- **Neue Helper-Funktionen** `canDelete()`, `canFinalize()`, `canSend()`,
  `canCancel()` (`InvoiceDetailModal.vue:219-235`), 1:1 aus
  `InvoicesView.vue:218-238` übernommen (inkl. der Konstanten
  `SENDABLE_STATUSES`/`CANCELLABLE_STATUSES` und dem Kommentar zu
  `design.md` Decision D3).
- **Buttons-Bereich** (`InvoiceDetailModal.vue:166-177`): Löschen-,
  Freigeben-, Senden- (disabled, gleicher Tooltip-Text
  `"Versand-Dialog folgt in einem späteren Update"`) und
  Stornieren-Button ergänzt, jeweils mit `v-if` auf die neuen Helper und
  Emit statt direktem API-Aufruf (`$emit('delete'|'finalize'|'cancel',
  invoice)`). Der bestehende "Bearbeiten"/"Als bezahlt
  markieren"-Button (T07-Vorgabe: bleibt unverändert bestehen) wurde
  nicht angefasst.
- **Neue `defineEmits`-Events** `'delete'`, `'finalize'`, `'cancel'`
  (`InvoiceDetailModal.vue:201-209`) — keine direkten API-Aufrufe im
  Modal, reines Presentation-Layer (bestehendes Muster
  `'mark-paid'`).
- **`InvoicesView.vue`** (`InvoicesView.vue:134-137`): neue Bindungen
  `@delete="deleteInvoice"`, `@finalize="finalizeInvoice"`,
  `@cancel="cancelInvoice"` auf die in T07 erstellten Funktionen, analog
  zur bestehenden `@mark-paid="markAsPaid"`-Bindung. Diese Funktionen
  schließen bei Erfolg bereits das Detail-Modal (`if
  (showDetailModal.value) closeDetailModal()`, T07-Code unverändert), das
  Verhalten "Löschen/Freigeben/Stornieren funktioniert identisch, ob über
  Listenzeile oder Detail-Modal ausgelöst" ist damit ohne weitere
  Modal-Anpassung erfüllt.
- **Storno-Referenz** (`InvoiceDetailModal.vue:63-70`): zwei neue
  bedingte Info-Zeilen im "Rechnungsinformationen"-Block —
  `invoice.originalInvoiceNumber` ("Stornorechnung zu:") und
  `invoice.cancellationInvoiceNumber` ("Storniert durch:"), jeweils nur
  gerendert, wenn der Wert gesetzt ist (`v-if`).

## Tests

Neue Datei `frontend/src/components/InvoiceDetailModal.test.ts`, 14 Tests:

- `draft`: korrekte Buttons (inkl. "Als bezahlt markieren", bestehender
  Button); `delete`/`finalize` werden mit dem `invoice`-Objekt emittiert.
- `sent`: korrekte Buttons, Senden `disabled` mit korrektem `title`;
  `cancel` wird emittiert.
- `paid`: PDF/Stornieren, kein Senden.
- `reminded`: wie `sent`, Badge "Gemahnt" sichtbar.
- `cancelled`: nur Schließen/PDF.
- Stornorechnung (`originalInvoiceId` gesetzt): kein Stornieren-Button.
- Storno-Referenz-Anzeige: `originalInvoiceNumber` bzw.
  `cancellationInvoiceNumber` einzeln und beide fehlend (keine Anzeige).
- Kunden-Ansicht: nur Schließen/PDF sichtbar.

`InvoiceDetailModal.vue` lädt beim Mount über `onMounted` die
Small-Business-Einstellung via `GET /api/v1/settings`
(`loadSettings()`). Der Test-Helper `mountModal()` mockt diesen Aufruf
einmalig vor jedem Mount und wartet `flushPromises()` ab, analog zum
bestehenden Test-Stil.

`InvoicesView.test.ts` (T07) wurde nicht erweitert, da es
`InvoiceDetailModal` per globalem Stub ersetzt (`stubs:
{ InvoiceDetailModal: { template: '<div data-testid="invoice-detail-modal" />' } }`)
und daher keine direkte Interaktion mit dem echten Modal testet — die
neuen `@delete`/`@finalize`/`@cancel`-Bindungen sind reine
Template-Wireings ohne eigene Logik (identisch zur bereits getesteten
`@mark-paid`-Bindung) und werden durch die neuen Modal-Tests (Events
werden korrekt emittiert) sowie die bestehenden T07-Tests für
`deleteInvoice()`/`finalizeInvoice()`/`cancelInvoice()` (Zielfunktionen
der Bindung) indirekt abgedeckt.

## Pre-Flight-Checks (in Docker, `dog-school-node`-Container)

```
docker compose exec node sh -c "npm run test -- run"    # 22 Testdateien, 244 Tests, alle grün
docker compose exec node sh -c "npm run lint"            # 0 Fehler, 3091 Warnings (Anstieg um 25 ggü.
                                                           # T07-Baseline von 3066 durch neuen Code in
                                                           # InvoiceDetailModal.vue/.test.ts — ausschließlich
                                                           # dieselben Kategorien wie im Bestandscode:
                                                           # vue/max-attributes-per-line,
                                                           # vue/singleline-html-element-content-newline,
                                                           # @typescript-eslint/no-explicit-any; keine neue
                                                           # Warning-Kategorie eingeführt)
docker compose exec node sh -c "npm run build"            # vue-tsc -b + vite build erfolgreich, keine
                                                           # TS-Fehler
```

Lokales `npm run test`/`npm run build` außerhalb Docker schlägt auf
diesem Host fehl (siehe T07-Notes: `esbuild`-Binary aus dem
Docker-Volume ist `@esbuild/linux-arm64`, Host ist `darwin-arm64`). Alle
Checks daher wie in `CLAUDE.md` Abschnitt 7.1 gefordert innerhalb des
Node-Containers ausgeführt.

## Nicht angefasst (bewusst außerhalb T08)

- `frontend/src/components/InvoiceFormModal.vue` — T09.
- Backend/API — keine Änderungen nötig, alle drei neuen Events nutzen
  bereits von T07 verdrahtete Endpunkte
  (`DELETE .../{id}`, `POST .../{id}/finalize`, `POST .../{id}/cancel`).

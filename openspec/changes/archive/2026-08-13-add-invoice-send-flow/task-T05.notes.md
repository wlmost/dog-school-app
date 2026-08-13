# T05: `InvoicesView.vue` — Senden-Button verdrahten, Dialog mounten

## Status: erledigt

## Geänderte Dateien

- `frontend/src/views/invoices/InvoicesView.vue`
- `frontend/src/views/invoices/InvoicesView.test.ts`

Ausschließlich diese beiden Dateien angefasst, wie im Task-Scope
vorgegeben. `InvoiceSendDialog.vue` (T04) und der Backend-Endpunkt (T03)
wurden nicht verändert.

## Umsetzung

- **Stub-Button ersetzt** (vormals Zeile 101): `disabled`/`title`-Stub
  entfernt, `@click="openSendDialog(invoice)"` ergänzt, Farbgebung auf
  `text-blue-600 dark:text-blue-400 hover:text-blue-900
  dark:hover:text-blue-300` gesetzt — identisch zum "Freigeben"-Button
  (Vorbild laut `tasks.md`).
- `SENDABLE_STATUSES`/`canSend()` unverändert gelassen (bereits korrekt
  aus Change 1).
- **`InvoiceSendDialog` gemountet**, analog zu `InvoiceFormModal`/
  `InvoiceDetailModal`, mit exakt den in `tasks.md`/`design.md` Decision
  D8 vorgegebenen Bindungen: `:is-open="showSendDialog"`,
  `:invoice="selectedInvoice"`, `@close="closeSendDialog"`,
  `@download="downloadPDF"`, `@send-email="sendInvoiceEmail"`.
- **Neuer Ref** `showSendDialog = ref(false)` neben `showFormModal`/
  `showDetailModal`.
- **Neue Funktionen** `openSendDialog()`, `closeSendDialog()`,
  `sendInvoiceEmail()` — Code 1:1 wie im Task-Beispiel übernommen.
  `sendInvoiceEmail()` ruft `POST /api/v1/invoices/{id}/send-email`,
  schließt den Dialog und zeigt einen Erfolgs-Toast bei Erfolg; bei
  Fehler wird `handleApiError()` aufgerufen und der Dialog bleibt bewusst
  offen (Decision D8: sofortiger Wechsel auf "Manuell versenden"
  möglich).
- `downloadPDF(invoice)` unverändert wiederverwendet für den
  `@download`-Handler des Dialogs — kein neuer Code, keine Duplizierung
  (Decision D3).
- **`InvoiceDetailModal`-Bindung** um `@send="openSendDialog"` ergänzt
  (Vorgriff auf T06, das im Modal selbst nur noch das `send`-Event
  emittieren muss — die Elternseite ist bereits fertig verdrahtet).

## Tests

`InvoicesView.test.ts` angepasst/erweitert, bestehender Stil beibehalten
(`makeInvoice()`-Factory, `findActionButton()`-Helper,
`mockTrainerAuth()`/`mockConfirm()`):

- `globalStubs.InvoiceDetailModal` und neu `globalStubs.InvoiceSendDialog`
  jetzt mit `name`/`props`/`emits` (statt reiner Divs), damit
  `wrapper.findComponent({ name: ... })` + `vm.$emit(...)` genutzt werden
  kann — Muster übernommen aus
  `frontend/src/views/CourseDetailView.test.ts` (`CustomerBookingModal`-
  Stub).
- Bestehender Test "zeigt PDF, einen deaktivierten Senden-Button ..."
  umbenannt/angepasst: Senden-Button ist jetzt nicht mehr `disabled`.
- Bestehender Test "löst beim Klick auf Senden keinen API-Aufruf aus"
  ersetzt durch "öffnet beim Klick auf Senden den InvoiceSendDialog mit
  der Rechnung" (prüft `isOpen`/`invoice`-Props des Dialogs sowie weiterhin
  keinen direkten API-Aufruf).
- Neue `describe('InvoiceSendDialog-Interaktion')` mit 5 Tests:
  - `send-email`-Event → POST an `/send-email`, Dialog schließt,
    Erfolgs-Toast.
  - `send-email`-Event mit fehlgeschlagenem POST → `handleApiError()`,
    Dialog bleibt offen (`isOpen` weiterhin `true`).
  - `download`-Event → identischer PDF-GET-Aufruf wie der bestehende
    PDF-Button.
  - `close`-Event → Dialog schließt.
  - `send`-Event vom (gestubbten) `InvoiceDetailModal` → öffnet den
    `InvoiceSendDialog` mit der korrekten Rechnung (deckt die
    T06-Vorgriff-Bindung ab).

Ergebnis: 24 Tests in `InvoicesView.test.ts` (vorher 19), alle grün.

## QA (Docker-Container `dog-school-node`, siehe T04-Notes zur Begründung)

```
docker exec dog-school-node sh -c "cd /var/www/html/frontend && npx vitest run"
# 23 Test-Dateien, 262 Tests, alle grün (vorher 257 nach T04, +5 neue)

docker exec dog-school-node sh -c "cd /var/www/html/frontend && npm run lint"
# Exit 0, 0 Fehler. Nur bereits bestehende Warnings im Bestandscode von
# InvoicesView.vue (u. a. vue/max-attributes-per-line,
# vue/attributes-order, @typescript-eslint/no-explicit-any), kein neuer
# Warnungstyp durch diesen Diff eingeführt.

docker exec dog-school-node sh -c "cd /var/www/html/frontend && npm run build"
# vue-tsc -b + vite build, Exit 0, keine Typfehler.
```

## Keine Abweichungen von der Task-Beschreibung

Implementierung folgt dem in `tasks.md` T05 vorgegebenen Code-Beispiel
(Button-Markup, Dialog-Bindung, `openSendDialog`/`closeSendDialog`/
`sendInvoiceEmail`) 1:1.

## Hinweis für T06

`InvoicesView.vue` ist bereits vollständig auf das `send`-Event von
`InvoiceDetailModal` vorbereitet (`@send="openSendDialog"`). T06 muss in
`InvoiceDetailModal.vue` nur noch den bestehenden Stub-Button durch
`@click="$emit('send', invoice)"` ersetzen und `send` in `defineEmits`
aufnehmen — an `InvoicesView.vue` ist dafür keine weitere Änderung nötig.

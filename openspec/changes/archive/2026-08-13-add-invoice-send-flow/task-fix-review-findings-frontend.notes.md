# Fix: Frontend-Befunde aus review.md (Muss-Befund 1 und 2)

## Fix 1 — Geteilter `selectedInvoice`-Ref bricht das Detail-Modal

**Datei:** `frontend/src/views/invoices/InvoicesView.vue`

`InvoiceDetailModal` und `InvoiceSendDialog` teilten sich denselben
`selectedInvoice`-Ref. Da `InvoiceSendDialog` laut `design.md` Decision D8
über dem weiterhin geöffneten `InvoiceDetailModal` geöffnet werden kann,
setzte `closeSendDialog()` beim Schließen `selectedInvoice.value = null` und
leerte damit versehentlich auch das im Hintergrund noch offene Detail-Modal.

Behoben durch einen eigenen Ref `sendDialogInvoice`, der ausschließlich vom
Send-Dialog verwendet wird:

- `openSendDialog(invoice)` setzt jetzt `sendDialogInvoice.value = invoice`
  statt `selectedInvoice.value`.
- `closeSendDialog()` setzt `sendDialogInvoice.value = null`, lässt
  `selectedInvoice` (und damit ein evtl. offenes Detail-Modal) unangetastet.
- Die Template-Bindung `<InvoiceSendDialog :invoice="...">` wurde von
  `selectedInvoice` auf `sendDialogInvoice` umgestellt.
- `selectedInvoice` wird weiterhin ausschließlich von `InvoiceFormModal` und
  `InvoiceDetailModal` verwendet (`viewInvoice`, `editInvoice`,
  `closeDetailModal`, `closeFormModal`) — unverändert.

Der Send-Dialog wird sowohl beim Klick auf "Senden" in der Tabellenzeile als
auch beim `send`-Event aus dem Detail-Modal über denselben `openSendDialog()`-
Handler befüllt, sodass in beiden Fällen die korrekte Rechnung übergeben
wird.

**Test:** neuer Test in `InvoicesView.test.ts`
("lässt das Detail-Modal nach dem Schließen eines aus ihm heraus geöffneten
Send-Dialogs weiterhin korrekt sichtbar ...") öffnet das Detail-Modal per
Zeilenklick, öffnet daraus den Send-Dialog, sendet erfolgreich per App-Mail
und prüft, dass das Detail-Modal danach weiterhin `isOpen === true` mit der
unveränderten `invoice`-Prop ist.

## Fix 2 — 502-Fallback-Hinweis ging im Frontend verloren

**Datei:** `frontend/src/utils/errorHandler.ts`

Der `status >= 500`-Zweig in `handleApiError()` zeigte immer nur die
generische Meldung "Server-Fehler: Ein interner Fehler ist aufgetreten...",
bevor der `data.message`-Zweig erreicht wurde. Der vom Backend bei
`InvoiceController::sendEmail()` gezielt gesetzte 502-Hinweistext ("Die
Rechnung konnte nicht per E-Mail versendet werden. Bitte laden Sie das PDF
herunter und versenden Sie es manuell.") kam beim Nutzer nie an.

**Entscheidung:** globaler Fix in `handleApiError()` (Reviewer-Präferenz),
nicht der lokale Fix nur in `InvoicesView.vue::sendInvoiceEmail()`. Im
`status >= 500`-Zweig wird jetzt zuerst `data.message` angezeigt, falls
vorhanden, sonst weiterhin der generische Fallback-Text.

**Risikoabschätzung (`handleApiError` wird an 27 Stellen im Frontend
genutzt):** Per Grep über `backend/app/Http/Controllers` wurden alle
Stellen geprüft, die aktuell mit Status >= 500 antworten
(`InvoiceController.php:460` [502], `ContactController.php:52` [503],
`PaymentController.php:230,270` [500], `PaymentController.php:321` [500,
kein `message`-Feld]). Alle vorhandenen `message`-Felder in diesen
5xx-Antworten sind bewusst kurz gehaltene, deutsche, nutzerfreundliche
Texte — keine rohen Exception-Meldungen (die liegen, wo vorhanden, in einem
separaten `error`-Feld und sind zusätzlich über `config('app.debug')`
gated). Die Änderung verbessert also global die Informationsqualität ohne
Risiko, interne Fehlerdetails preiszugeben oder bestehende Meldungen zu
verschlechtern. Wo `message` fehlt (z.B. `PaymentController.php:321`),
bleibt der bisherige generische Fallback-Text unverändert erhalten.

**Test:** neue Datei `frontend/src/utils/errorHandler.test.ts` (bisher
existierte keine) deckt gezielt ab:
- 502 mit `data.message` → Toast zeigt den Backend-Text.
- 500 ohne `data.message` → Toast zeigt weiterhin den generischen Fallback.
- Bestehendes Verhalten für 422 (Validierungsfehler) und den Fall ohne
  Error-Objekt bleibt unverändert (Regressionsschutz).

## QA-Läufe (Docker-Container `node`, da lokales `node_modules` für
falsche Plattform gebaut ist)

```
docker compose exec node sh -lc "cd /var/www/html/frontend && npm run test -- --run"
# 24 Test-Dateien, 269 Tests, alle grün (inkl. 2 neue InvoicesView-Fälle
# und 4 neue errorHandler-Fälle)

docker compose exec node sh -lc "cd /var/www/html/frontend && npm run lint"
# exit 0, 0 Fehler (nur bestandsweite, unveränderte Warnings)

docker compose exec node sh -lc "cd /var/www/html/frontend && npm run build"
# vue-tsc -b && vite build erfolgreich, keine Fehler
```

## Geänderte / neue Dateien

- `frontend/src/views/invoices/InvoicesView.vue` (Fix 1)
- `frontend/src/utils/errorHandler.ts` (Fix 2)
- `frontend/src/views/invoices/InvoicesView.test.ts` (neuer Test für Fix 1)
- `frontend/src/utils/errorHandler.test.ts` (neu, Tests für Fix 2)

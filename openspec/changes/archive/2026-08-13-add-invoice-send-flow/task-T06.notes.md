# T06: `InvoiceDetailModal.vue` — Senden-Button auf `send`-Event umstellen

## Status: erledigt

## Geänderte Dateien

- `frontend/src/components/InvoiceDetailModal.vue`
- `frontend/src/components/InvoiceDetailModal.test.ts`

Ausschließlich diese beiden Dateien angefasst, wie im Task-Scope
vorgegeben. `InvoicesView.vue` (bereits in T05 fertig verdrahtet) und
`InvoiceSendDialog.vue` (T04) wurden nicht verändert.

## Umsetzung

- **Stub-Button ersetzt** (vormals Zeile 172-174): `disabled`/`title`-Stub
  entfernt, `@click="$emit('send', invoice)"` ergänzt, Farbgebung
  identisch zum "Freigeben"-Button übernommen (`btn bg-blue-600
  hover:bg-blue-700 dark:bg-blue-700 dark:hover:bg-blue-600 text-white`),
  wie in `tasks.md` T06 vorgegeben.
- **`defineEmits`** um `send: [invoice: any]` ergänzt, konsistent mit dem
  bestehenden `[invoice: any]`-Muster der anderen Events (`download`,
  `edit`, `mark-paid`, `delete`, `finalize`, `cancel`).
- `SENDABLE_STATUSES` und `canSend()` unverändert gelassen — keine
  Logikänderung nötig, wie in `tasks.md` gefordert.
- Das Modal mountet weiterhin keinen `InvoiceSendDialog` — bleibt reines
  Presentation-Layer, Öffnen des Dialogs erfolgt ausschließlich über
  `InvoicesView.vue::openSendDialog` (bereits in T05 an `@send` gebunden,
  design.md Decision D8).

## Tests

`InvoiceDetailModal.test.ts` angepasst, bestehender Stil beibehalten
(`makeInvoice()`-Factory, `findActionButton()`-Helper,
`mockTrainerAuth()`):

- Test "zeigt PDF, Bezahlt-markieren, einen deaktivierten Senden-Button
  und Stornieren, ..." umbenannt zu "... einen aktiven Senden-Button
  ..."; Assertion auf `disabled`-Attribut umgedreht
  (`toBeUndefined()` statt `toBeDefined()`), Assertion auf das
  (jetzt entfernte) `title`-Attribut gestrichen.
- Neuer Test "emittiert 'send' mit dem invoice-Objekt beim Klick auf
  Senden" in `describe('Status "sent"')`, analog zum bestehenden
  "emittiert 'finalize' beim Klick auf Freigeben"-Test in
  `describe('Status "draft"')`.

Ergebnis: 17 Tests in `InvoiceDetailModal.test.ts` (vorher 16), alle
grün.

## QA (Docker-Container `dog-school-node`)

```
docker exec dog-school-node sh -c "cd /var/www/html/frontend && npx vitest run"
# 23 Test-Dateien, 263 Tests, alle grün (vorher 262 nach T05, +1 neuer)

docker exec dog-school-node sh -c "cd /var/www/html/frontend && npm run lint"
# Exit 0, 0 Fehler, 3121 Warnings. Alle Warnings in InvoiceDetailModal.vue/
# .test.ts sind bereits bestehende Bestandscode-Warnings (u. a.
# vue/max-attributes-per-line, vue/attributes-order,
# vue/singleline-html-element-content-newline,
# @typescript-eslint/no-explicit-any) — kein neuer Warnungstyp und keine
# neue Fundstelle durch diesen Diff. Gleiches Muster wie in T04-/
# T05-Notes dokumentiert.

docker exec dog-school-node sh -c "cd /var/www/html/frontend && npm run build"
# vue-tsc -b + vite build, Exit 0, keine Typfehler.
```

## Keine Abweichungen von der Task-Beschreibung

Implementierung folgt dem in `tasks.md` T06 vorgegebenen Code-Beispiel
(Button-Markup, `defineEmits`-Ergänzung) 1:1.

## Hinweis zu Inkonsistenzen über T01-T06 hinweg

- Der Akzeptanzkriterien-Wortlaut "`npm run lint` ... laufen ohne
  Fehler/Warnings durch" (so in T01-T06 wiederholt) ist strenggenommen
  nicht erfüllbar, ohne den gesamten Bestandscode mitzureformatieren
  (3121 Bestandswarnings, keine davon durch diesen oder die
  vorangegangenen Changes eingeführt). Bereits T04/T05 haben dieses
  Kriterium pragmatisch als "keine neuen Warnings" interpretiert; T06
  folgt demselben Muster. Empfehlung für künftige Changes: Formulierung
  in `tasks.md`-Vorlagen auf "keine neuen Lint-Fehler/-Warnings" präzisieren,
  um diese wiederkehrende Interpretationslücke zu schließen.
- Mit T06 ist der in `design.md` Decision D8 beschriebene Rundlauf
  (Listenzeile → `InvoiceSendDialog`; Detail-Modal → `send`-Event →
  `InvoicesView.vue::openSendDialog` → derselbe `InvoiceSendDialog`)
  vollständig verdrahtet. Keine offenen Lücken zwischen T01-T06
  festgestellt.

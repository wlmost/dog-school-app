# Notes: T07 — `InvoicesView.vue` — "Mahnen"-Button

## Status

Implementiert. Alle Akzeptanzkriterien in `tasks.md` T07 sind erfüllt.

## Ausgangslage in diesem Worktree

Der Auftrag beschrieb den Stand "nach Merge von T01-T06, Commit `b4cd191`".
Dieser Worktree war jedoch initial auf dem Hauptzweig (`29bfb77`, PR #92)
eingecheckt — der `openspec/changes/add-invoice-dunning-dashboard/`-Ordner
existierte hier noch nicht (identisches Muster wie bereits in
`task-T04.notes.md` dokumentiert). Vor Beginn der Implementierung per
`git reset --hard b4cd191` auf exakt den im Auftrag genannten Commit
zurückgesetzt (identisch zu dem, was `feature/add-invoice-dunning-dashboard`
im Haupt-Checkout referenziert, verifiziert per `git worktree list`/
`git merge-base`). Der isolierte Worktree wurde dabei nicht angetastet —
kein Push, keine Änderung an anderen Worktrees/Branches. `node_modules`
war in diesem Worktree noch nicht installiert (`npm ci` lokal nachgeholt,
gitignored, kein Commit nötig).

## Umgesetzt

- `frontend/src/views/invoices/InvoicesView.vue`:
  - Neue Konstante `REMINDABLE_STATUSES = ['sent', 'reminded', 'overdue']`,
    direkt nach `PAYABLE_STATUSES` platziert (analog zu `SENDABLE_STATUSES`/
    `PAYABLE_STATUSES`, mit Kommentar, der auf
    `InvoiceDunningRecorder::trigger()` als Backend-Vorbild verweist,
    siehe `design.md` Decision D3).
  - Neuer Helper `canRemind(invoice)`, direkt nach `canRecordPayment()`:
    `!authStore.isCustomer && REMINDABLE_STATUSES.includes(invoice.status)
    && !invoice.originalInvoiceId && invoice.nextDunningLevel !== null` —
    exakter Wortlaut aus der Task-Beschreibung.
  - Neuer Listenzeilen-Button "Mahnen" (`text-orange-...`-Klassen, passend
    zur bestehenden `reminded`-Badge-Farbe), zwischen "Zahlung erfassen"
    und "Stornieren" platziert, `v-if="canRemind(invoice)"`,
    `@click="remindInvoice(invoice)"`.
  - Neuer Handler `remindInvoice(invoice)`, 1:1 nach dem Muster von
    `cancelInvoice()`: `confirm(...)`-Dialog mit exaktem, in der
    Task-Beschreibung vorgegebenem Text (Stufe + `formatCurrency(...)`-
    formatierte Gebühr), danach `POST /api/v1/invoices/{id}/remind`,
    `try { await loadInvoices(); Detail-Modal schließen falls offen;
    showSuccess(...) } catch { handleApiError(...) }`.
  - Neuer `@remind="remindInvoice"`-Listener auf `<InvoiceDetailModal>`
    (der Emit selbst wird laut Auftrag erst in T08 in der Komponente
    ergänzt — hier ausschließlich der Listener, damit beide Tasks
    unabhängig zusammenpassen).

- `frontend/src/views/invoices/InvoicesView.test.ts`:
  - `makeInvoice()`-Fixture um `nextDunningLevel: 1`/
    `nextDunningFeeAmount: 5` als Default ergänzt (entspricht einer
    frischen Rechnung ohne Mahnhistorie,
    `DunningFeeSchedule::nextLevel(null) === 1`, siehe
    `task-T01.notes.md`). Bewusst nicht `null` als Default gewählt, damit
    bestehende Tests, die implizit remindfähige Status (`sent`/`reminded`/
    `overdue`) verwenden, weiterhin realistische Fixtures erhalten;
    verifiziert, dass kein bestehender Test eine exakte
    Button-Liste (`toEqual([...])`) für einen remindfähigen Status
    voraussetzt (nur `cancelled`/Kunden-Draft nutzen `toEqual`, beide
    nicht remindfähig).
  - `InvoiceDetailModal`-Stub: `'remind'` zur `emits`-Liste ergänzt.
  - Neue `describe('"Mahnen"-Button (canRemind)', ...)`: Sichtbarkeit je
    Status (`sent`/`reminded`/`overdue` zeigen, `draft`/`paid`/`cancelled`
    verstecken), Sichtbarkeit bei erreichter Maximalstufe
    (`nextDunningLevel === null`), bei Stornorechnungen
    (`originalInvoiceId` gesetzt) und für Kunden.
  - Neue `describe('remindInvoice()', ...)`: Bestätigungsdialog-Inhalt
    (Stufe + formatierte Gebühr im `confirm()`-Aufruf), Abbruch bei
    `confirm() === false` (kein POST), Erfolgsfall (POST gegen
    `/remind`, Reload, `showSuccess`), 422-Fehlerfall
    (`handleApiError` mit der durchgereichten Backend-Fehlermeldung,
    kein Absturz), sowie Schließen eines offenen Detail-Modals nach
    erfolgreichem Mahnen über das `remind`-Event von
    `InvoiceDetailModal`.

## Abweichungen von der Task-Beschreibung

Keine.

## Verifikation

```bash
cd frontend
npm ci                                    # node_modules war in diesem
                                           # Worktree noch nicht installiert
npx vitest run src/views/invoices/InvoicesView.test.ts
  # Test Files  1 passed (1) — 57 passed (bisher 46 + 11 neue Tests)
npx vitest run
  # Test Files  25 passed (25) — 322 passed, keine Regression
npx eslint src/views/invoices/InvoicesView.vue src/views/invoices/InvoicesView.test.ts
  # 0 errors, 149 warnings (ausschließlich bereits bestehende
  # Bestandscode-Warnungen: `any`-Nutzung, Attribut-Reihenfolge/
  # Zeilenlänge — kein neuer Fehler durch diese Task)
npm run lint
  # 0 errors, 3186 warnings — repo-weite Baseline, unverändert
npm run build
  # vue-tsc -b && vite build — erfolgreich, keine TypeScript-Fehler,
  # InvoicesView-Chunk erzeugt (dist/assets/InvoicesView-*.js)
```

Kein `composer qa`-Lauf nötig — reine Frontend-Task, keine
Backend-Dateien angefasst.

## Offene Punkte für Reviewer/Tester

- Die Button-Farbe (`text-orange-...`) ist eine eigene Design-Entscheidung
  dieser Task (an die bestehende `reminded`-Status-Badge-Farbe angelehnt,
  `invoiceStatusClass()` Zeile ~489 in `InvoicesView.vue`) — weder
  `tasks.md` noch `design.md` geben eine Farbe vor.
- `formatCurrency(invoice.nextDunningFeeAmount)` nutzt die bereits
  bestehende Funktion unverändert; bei `nextDunningFeeAmount === 0`
  würde sie `'0,00 €'` anzeigen (Bestandsverhalten von `formatCurrency`,
  siehe Zeile ~478 — `if (!amount) return '0,00 €'`), was für diesen
  Anwendungsfall irrelevant ist, da alle konfigurierten Mahngebühren
  laut `config/invoicing.php`-Default > 0 sind.
- T08 (`InvoiceDetailModal.vue`) muss den `remind`-Emit noch ergänzen,
  damit der hier bereits verdrahtete `@remind="remindInvoice"`-Listener
  in der echten Anwendung (außerhalb der Tests) etwas auslöst — bewusst
  keine Blockade dieser Task, wie im Auftrag beschrieben (paralleler
  Worktree für T08 an demselben Ursprungscommit).

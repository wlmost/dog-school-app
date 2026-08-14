# Notes: T08 — `InvoiceDetailModal.vue` — "Mahnen"-Button + Mahnhistorie

## Status

Implementiert. Alle Akzeptanzkriterien in `tasks.md` T08 sind erfüllt.

## Ausgangslage in diesem Worktree

Der Auftrag beschrieb den Stand "nach Merge von T01-T06, Commit `b4cd191`".
Dieser Worktree war initial auf `29bfb77` (Hauptzweig, PR #92) eingecheckt.
Da `29bfb77` ein Vorfahre von `b4cd191` ist (per `git merge-base
--is-ancestor` verifiziert), per `git merge --ff-only b4cd191` sauber
vorgespult — keine Konflikte, keine Änderung an anderen Worktrees/Branches
(der Branch `feature/add-invoice-dunning-dashboard` selbst ist im
Haupt-Checkout ausgecheckt, dieser isolierte Worktree hatte einen eigenen,
zurückliegenden lokalen Branch `worktree-agent-a4fce622ac989fbc1`).

## Umgesetzt

`frontend/src/components/InvoiceDetailModal.vue`:

- Neue lokale Konstante `REMINDABLE_STATUSES = ['sent', 'reminded',
  'overdue']` und Funktion `canRemind(invoice)` — exakt wie in der
  Task-Beschreibung vorgegeben: `!authStore.isCustomer &&
  REMINDABLE_STATUSES.includes(invoice.status) &&
  !invoice.originalInvoiceId && invoice.nextDunningLevel !== null`.
  Bewusst lokal dupliziert, nicht mit `InvoicesView.vue` konsolidiert
  (etabliertes Nicht-Konsolidierungs-Muster laut `design.md` Context zu
  T07/T08, analog zu `PAYABLE_STATUSES`/`CANCELLABLE_STATUSES`).
- Neuer Button "Mahnen" (`v-if="canRemind(invoice)"`, `@click="$emit('remind',
  invoice)"`) in der Action-Buttons-Zeile, nach "Stornieren" eingefügt.
  Eigene Farbe (`bg-orange-600`), um sich optisch von den bestehenden
  blauen/roten Aktionen abzuheben (orange korrespondiert mit dem
  bestehenden "Gemahnt"-Status-Badge, siehe `getStatusClass()`).
- Neuer `remind: [invoice: any]`-Eintrag in `defineEmits` (Payload:
  komplettes `invoice`-Objekt, identisch zum Muster von `cancel`/`send`).
- Neuer "Mahnungen"-Block, direkt nach dem bestehenden "Zahlungen"-Block
  (Vorbild Zeile 133-148 im Ausgangsstand) eingefügt, vor dem
  "Notizen"-Block: `v-if="invoice.dunnings && invoice.dunnings.length >
  0"` (identisches Muster wie der Zahlungen-Block, nicht Optional-Chaining
  wie im Task-Text angedeutet — funktional identisch, aber konsistent mit
  dem tatsächlichen Vorbild im Bestandscode). Listet je Mahnung: Stufe
  (`dunning.level`), Gebühr (`formatCurrency(dunning.feeAmount)`), Datum
  (`formatDate(dunning.dunningDate)`) und — falls vorhanden — die
  Rechnungsnummer des verlinkten Gebührendokuments
  (`dunning.feeInvoiceNumber`, hinter `v-if` da laut
  `InvoiceDunningResource` nur befüllt, wenn die `feeInvoice`-Relation
  eager-geladen wurde).

Feldnamen (`level`, `dunningDate`, `feeAmount`, `feeInvoiceId`,
`feeInvoiceNumber`) 1:1 aus `backend/app/Http/Resources/
InvoiceDunningResource.php` übernommen (gelesen, nicht aus dem Gedächtnis
angenommen).

## Tests

`frontend/src/components/InvoiceDetailModal.test.ts`:

- `makeInvoice()`-Fixture um `nextDunningLevel: 1`, `nextDunningFeeAmount:
  5`, `dunnings: []` als Defaults ergänzt (analog zum bestehenden
  `remainingBalance`/`totalPaid`-Default-Kommentar: Standardmäßig
  "mahnbarer" Zustand, sofern nicht explizit überschrieben).
- Neue Gruppe `'"Mahnen"-Button (canRemind)'`:
  - `it.each(['sent', 'reminded', 'overdue'])`: Button sichtbar, Klick
    emittiert `remind` mit dem `invoice`-Objekt (Muster identisch zu
    `'Zahlung erfassen'-Button (canRecordPayment)'`).
  - `it.each(['draft', 'paid', 'cancelled'])`: Button NICHT sichtbar.
  - Eigener Test: Button NICHT sichtbar bei `nextDunningLevel: null` trotz
    remindable Status (deckt explizit "Abwesenheit bei bereits erreichter
    Stufe 3" aus den Akzeptanzkriterien ab).
  - Eigener Test: Button NICHT sichtbar bei `originalInvoiceId` gesetzt
    (Storno-/Gebührendokument).
  - Eigener Test: Button NICHT sichtbar für Kunden (Muster identisch zu
    den bestehenden Kunden-Sichtbarkeitstests für andere Buttons).
- Neue Gruppe `'Mahnhistorie ("Mahnungen"-Block)'`:
  - Test mit zwei Mahn-Datensätzen: prüft Stufe, Datum, Gebühr (via
    Regex `/5,00\s€/`, siehe unten) und Dokumentnummer je Eintrag.
  - Test: kein "Mahnungen"-Block, wenn `dunnings: []`.

### Kleinere Korrektur während der Umsetzung

Erster Testlauf schlug fehl: `expect(wrapper.text()).toContain('5,00 €')`
fand die Zeichenkette nicht, obwohl sie sichtbar im gerenderten Text stand.
Ursache: `Intl.NumberFormat('de-DE', { style: 'currency' })` trennt Betrag
und Symbol mit einem geschützten Leerzeichen (U+00A0 NBSP), nicht mit
einem normalen Leerzeichen (U+0020) — bereits im bestehenden Test
"Restbetrag-Zusammenfassungszeile" per Kommentar dokumentiert, dort aber
mit einem literalen NBSP-Zeichen in der Test-Datei gelöst. Da ein
verlässliches Einfügen eines unsichtbaren Unicode-Zeichens über die
Editier-Werkzeuge fehleranfällig ist, stattdessen `toMatch(/5,00\s€/)`
verwendet — JavaScripts `\s`-Zeichenklasse matcht laut ECMAScript-Spec
auch NBSP, verifiziert durch grünen Testlauf.

## Verifikation (lokal, ohne Docker — reine Frontend-Task)

```bash
cd frontend
npm ci                                          # node_modules fehlte initial in diesem Worktree
npx vitest run src/components/InvoiceDetailModal.test.ts   # 40 passed (40)
npx vitest run                                              # 319 passed (25 Testdateien)
npm run lint                                                 # 0 errors, nur bestehende
                                                               # Warnings (repo-weit
                                                               # 3193 Warnings, keine neu
                                                               # durch diese Änderung
                                                               # verursacht — einzeln
                                                               # gegen InvoiceDetailModal.vue/
                                                               # .test.ts geprüft: nur
                                                               # Formatierungs-Warnings
                                                               # (vue/max-attributes-per-line
                                                               # etc.) und bestehende
                                                               # `any`-Warnings, identisch
                                                               # zum Bestandsmuster der Datei)
npm run build                                                 # vue-tsc -b + vite build,
                                                               # keine TS-Fehler
```

Kein Backend-/Docker-Lauf nötig — reine Vue-SFC-Änderung ohne
API-Client-Aufrufe (der eigentliche `POST .../remind`-Aufruf inkl.
Bestätigungsdialog/Error-Handling lebt laut Aufgabenteilung in T07,
`InvoicesView.vue`, die den `@remind`-Listener auf dieses Modal registriert
— dieses Modal emittiert nur das Event, ruft die API nicht selbst auf,
analog zu `cancel`/`send`/`finalize`).

## Abweichungen von der Task-Beschreibung

Keine funktionalen Abweichungen. Der `v-if`-Ausdruck für den
Mahnungen-Block nutzt `invoice.dunnings && invoice.dunnings.length > 0`
statt der im Task-Text erwähnten Optional-Chaining-Schreibweise
(`invoice.dunnings?.length > 0`) — funktional identisch, aber 1:1
konsistent mit dem tatsächlichen Vorbild ("Zahlungen"-Block) im
Bestandscode.

## Offene Punkte für Reviewer/Tester

- Dieselbe strukturelle Nicht-Konsolidierung wie bei
  `PAYABLE_STATUSES`/`SENDABLE_STATUSES`/`CANCELLABLE_STATUSES`:
  `REMINDABLE_STATUSES` ist nun dreifach dupliziert (T07
  `InvoicesView.vue`, T08 `InvoiceDetailModal.vue`, sowie implizit die
  Backend-Eligibility in `InvoiceDunningRecorder`). Kein neuer Befund,
  sondern Fortführung des in `design.md` bewusst getroffenen
  Nicht-Konsolidierungs-Musters dieses Projekts.
- Die Farbwahl für den "Mahnen"-Button (`bg-orange-600`) ist nicht in
  `tasks.md`/`design.md` spezifiziert — an den bestehenden
  "Gemahnt"-Status-Badge (`bg-orange-100`/`text-orange-800`) angelehnt,
  um eine konsistente Farbsemantik im UI herzustellen. Falls der Reviewer
  eine andere Farbe erwartet, ist das rein kosmetisch trivial anpassbar.
- T07 (`InvoicesView.vue`) läuft parallel in einem separaten Worktree und
  ergänzt dort den `@remind="remindInvoice"`-Listener auf
  `<InvoiceDetailModal>`. Das neue `remind`-Event dieser Datei ist dazu
  kompatibel (Payload: vollständiges `invoice`-Objekt, exakt wie
  `cancel`/`send`) — verifiziert durch Lesen der Task-Vorgabe für T07 im
  Zuteilungsauftrag, nicht durch Einsicht in den T07-Worktree-Code selbst
  (der lag außerhalb des Zugriffs dieses isolierten Worktrees).

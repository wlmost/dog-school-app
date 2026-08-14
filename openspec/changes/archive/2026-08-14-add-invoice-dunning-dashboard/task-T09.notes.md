# T09 — `DashboardView.vue` — Widget "Überfällige & gemahnte Rechnungen"

## Umgesetzt

- `frontend/src/views/DashboardView.vue`:
  - Neuer Kartenblock "Überfällige & gemahnte Rechnungen" (Zeile ~274-338),
    direkt nach dem bestehenden "Ausstehende Stornierungsanfragen"-Block
    (Zeile 208-272) eingefügt, 1:1 nach dessen Struktur (Header mit
    Zähler-Badge, `loading`-Spinner, Leer-Zustand, `divide-y`-Liste),
    sichtbar für `user?.role === 'trainer' || user?.role === 'admin'`.
  - Neues Interface `OverdueOrRemindedInvoice` (`id`, `invoiceNumber`,
    `customerName`, `dueDate`, `status`, `dunningLevel: number | null`,
    `remainingBalance`) — exakt die von `DashboardController::
    mapOverdueOrRemindedInvoice()` gelieferten Felder (siehe
    `task-T06.notes.md`).
  - Neuer `ref<OverdueOrRemindedInvoice[]>([])` `overdueOrRemindedInvoices`,
    befüllt in `loadDashboard()` aus
    `response.data.overdueOrRemindedInvoices ?? []`.
  - Je Zeile: Rechnungsnummer, Status-Badge (Farbe + Label), Mahnstufe
    (`v-if="invoice.dunningLevel !== null"`), Kundenname, Fälligkeitsdatum
    (Backend liefert bereits `d.m.Y`-formatiert, kein zusätzliches
    `formatDate()` nötig), Restbetrag (`formatCurrency()`).
  - Kein Aktions-Button in der Zeile (Non-Goal laut `design.md`) —
    stattdessen ein `<router-link :to="{ name: 'Invoices' }">Zur
    Rechnungsübersicht</router-link>`-Link am Kartenende. Router-Name
    `'Invoices'` gegen `frontend/src/router/index.ts:122` verifiziert.
  - Leerer Zustand: "Keine überfälligen oder gemahnten Rechnungen".
  - Neue lokale Helper `formatCurrency()`, `invoiceStatusClass()`,
    `invoiceStatusLabel()` — bewusst dupliziert statt aus
    `InvoicesView.vue` importiert, analog zum in `design.md` (Ist-Zustand
    Frontend) dokumentierten Nicht-Konsolidierungs-Muster zwischen
    `InvoicesView.vue`/`InvoiceDetailModal.vue`.
- `frontend/src/views/DashboardView.test.ts` (neu — vor Beginn geprüft,
  existierte noch nicht):
  - Mockt `@/stores/auth` und `@/api/client`, stubbt `router-link` über
    `RouterLinkStub` aus `@vue/test-utils` (siehe Abweichungen unten).
  - Tests: Sichtbarkeit für `admin`/`trainer`, Abwesenheit für `customer`;
    Rendering von Rechnungsnummer/Kundenname/Fälligkeitsdatum/Status/
    Restbetrag; Anzeige der Mahnstufe wenn gesetzt, keine Anzeige wenn
    `null`; mehrere Zeilen für mehrere Einträge; Leer-Zustand bei leerer
    Liste und bei fehlendem Response-Schlüssel; Link zur Rechnungsliste
    inkl. Prüfung des `:to`-Props (`{ name: 'Invoices' }`).

## Abweichungen von der Task-Beschreibung (mit Begründung)

- Die Task-Beschreibung nennt `ref<any[]>([])` für
  `overdueOrRemindedInvoices`. Stattdessen `ref<OverdueOrRemindedInvoice[]>([])`
  mit explizitem Interface verwendet (CLAUDE.md: `strict: true` respektieren,
  kein `any`). Funktional identisch, nur präziser typisiert.
- `RouterLinkStub`/`global.stubs` in der Testdatei: `DashboardView.vue` ist
  die einzige Datei im Projekt, die `<router-link>` direkt im Template
  verwendet, ohne einen echten Router im Test zu installieren (verifiziert
  per Grep — kein anderes SFC im Projekt nutzt das Tag; die in
  `InvoicesView.test.ts`/`CoursesView.test.ts` vorhandenen
  `vi.mock('vue-router', ...)`-Blöcke werden dort nie für `<router-link>`
  gebraucht, da die jeweiligen Templates keins enthalten). `@vue/test-utils`
  2.4.10 stubbt `router-link`/`router-view` nicht automatisch (anders als
  `vue-test-utils` v1) — daher explizit `global.stubs: { 'router-link':
  RouterLinkStub }` in `mountWithResponse()` gesetzt, kein
  `vi.mock('vue-router')` nötig, da `DashboardView.vue` keine Composables
  aus `vue-router` importiert.

## Nicht angefasst

- Backend (`DashboardController`, T06) — unverändert, nur konsumiert.
- `InvoicesView.vue`/`InvoiceDetailModal.vue` (T07/T08) — laufen laut
  Auftrag zeitgleich in separaten Worktrees, keine Berührung.

## Offene Punkte für Reviewer/Tester

- Keine bekannten offenen Punkte.

## QA-Ergebnis

Ausgeführt im Worktree via `npm ci` (kein `node_modules` vorhanden, daher
zunächst installiert):

```
npx vitest run src/views/DashboardView.test.ts   → 10/10 Tests grün
npx vitest run                                    → 26 Testdateien, 318 Tests grün
npm run lint                                      → 0 Errors, 163 Warnings in
                                                       DashboardView.vue/.test.ts
                                                       (ausschließlich bereits
                                                       im Bestand vorhandene
                                                       Stilwarnungen wie
                                                       vue/max-attributes-per-line;
                                                       exit code 0)
npm run build                                      → vue-tsc -b + vite build
                                                       erfolgreich, keine
                                                       Warnings, dist/ danach
                                                       wieder entfernt
                                                       (kein Commit von
                                                       Build-Artefakten)
```

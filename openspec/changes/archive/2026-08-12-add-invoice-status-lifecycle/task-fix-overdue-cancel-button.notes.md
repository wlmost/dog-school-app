# Fix: Stornieren-Button für `status = 'overdue'` (Muss-Befund 1, change-review.md)

## Befund

`CANCELLABLE_STATUSES` enthielt in beiden Frontend-Dateien fälschlich
`'overdue'`:

- `frontend/src/views/invoices/InvoicesView.vue:219`
- `frontend/src/components/InvoiceDetailModal.vue:207`

`InvoicePolicy::cancel()` (`backend/app/Policies/InvoicePolicy.php:142-147`)
lässt Storno serverseitig ausschließlich für
`in_array($invoice->status, ['sent', 'paid', 'reminded'], true)` zu —
`overdue` ist dort bewusst nicht enthalten. Ein Klick auf "Stornieren" bei
einer Rechnung mit (legacy) Status `overdue` führte serverseitig zu
HTTP 403.

## Verifikation vor dem Fix

- `design.md` Decision D3 (Zeile 197-210): `overdue` bleibt zwar als
  DB-Enum-Wert erhalten (Altlasten-Kompatibilität), wird aber **von
  keinem Produktivpfad mehr aktiv geschrieben** — einzige Schreibstelle
  ist `InvoiceFactory::overdue()` (Test-Fixture,
  `backend/database/factories/InvoiceFactory.php:42`).
- `Invoice::isOverdue()` (`backend/app/Models/Invoice.php:140-143`) ist
  rein datumsbasiert (`due_date->isPast()` und Status nicht
  `paid`/`cancelled`) und **unabhängig** vom Status-String — eine
  "überfällige" Rechnung trägt weiterhin den Status `sent` oder
  `reminded`, niemals literal `'overdue'`, wenn sie über den normalen
  Lebenszyklus dieses Change entstanden ist.
- `specs/invoice-cancellation/spec.md:11-56` nennt ausschließlich
  `sent`/`paid` (implizit auch `reminded`, siehe Policy) als stornierbare
  Ausgangsstatus — nie `overdue`.
- Die Hypothese aus dem Auftrag hat sich damit bestätigt: `overdue` in
  `CANCELLABLE_STATUSES` war schlicht falsch, unabhängig davon, ob eine
  Rechnung `isOverdue === true` ist.

## Fix

- `CANCELLABLE_STATUSES` in beiden Dateien auf `['sent', 'reminded', 'paid']`
  reduziert (Policy-konform). `SENDABLE_STATUSES` (deaktivierter
  Platzhalter-Button, keine Backend-Anbindung) unverändert gelassen — außerhalb
  des Scopes dieses Fixes.
- Kommentare bei beiden Konstanten präzisiert: `CANCELLABLE_STATUSES` muss
  exakt `InvoicePolicy::cancel()` spiegeln.

## Tests angepasst

- `frontend/src/views/invoices/InvoicesView.test.ts`:
  - `describe('Status "overdue"')`: erwartet jetzt **kein** `'Stornieren'`
    mehr (vorher fälschlich `toContain`).
  - Neuer Block `describe('Stornieren-Button bei isOverdue === true')`:
    belegt explizit, dass eine tatsächlich überfällige Rechnung mit Status
    `sent` bzw. `reminded` weiterhin den Stornieren-Button zeigt — der
    Fix schränkt also nur den (praktisch unerreichbaren) Legacy-Status
    `overdue` ein, nicht das `isOverdue`-Flag.
- `frontend/src/components/InvoiceDetailModal.test.ts`:
  - `describe('Status "overdue"')`: gleiche Anpassung (kein
    `'Stornieren'` mehr). Kein zusätzlicher `isOverdue`-Test nötig, da
    `InvoiceDetailModal.vue` `canCancel()` ausschließlich über
    `invoice.status` bestimmt (keine `isOverdue`-Prop in dieser
    Komponente) — die bereits vorhandenen Tests für `sent`/`reminded`/
    `paid` decken das ab.

## Lokale Checks (via `docker compose exec node`, da Host-`node_modules`
für `linux-arm64` statt `darwin-arm64` gebaut sind — Skript-Ausführung auf
dem Host schlägt an `esbuild`-Plattform-Mismatch fehl)

- `npm run lint`: 0 Errors, 3091 Warnings (unveränderte Baseline,
  bereits vom Reviewer verifiziert — keine neuen Lint-Fehler durch
  diesen Fix).
- `npx vitest run` (voller Frontend-Testlauf): 22 Testdateien, 248 Tests,
  alle grün (inkl. 19 in `InvoicesView.test.ts`, 15 in
  `InvoiceDetailModal.test.ts`).
- `npm run build` (`vue-tsc -b && vite build`): erfolgreich, keine
  TypeScript-Fehler, kein Build-Abbruch.

## Scope-Hinweis

Dieser Fix behebt ausschließlich Muss-Befund 1 aus `change-review.md`.
Muss-Befund 2 (`markAsPaid()` für `draft`-Rechnungen) sowie die
Sollte-/Könnte-Punkte sind bewusst nicht Teil dieses Fixes.

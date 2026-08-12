# Fix: `markAsPaid()` schließt Status `draft` nicht aus (Review Muss-Befund 2)

**Bezug:** `openspec/changes/add-invoice-status-lifecycle/change-review.md`,
Abschnitt "Muss", zweiter Befund
(`backend/app/Http/Controllers/Api/InvoiceController.php:209-224` /
`backend/app/Policies/InvoicePolicy.php:110-126`).

## Problem

`InvoicePolicy::markAsPaid()` prüfte nur die Rolle (`isAdminOrTrainer()`),
nicht den Status. `InvoiceController::markAsPaid()` prüfte lediglich, ob die
Rechnung bereits bezahlt ist (422 "Rechnung ist bereits bezahlt."), aber
nicht, ob sie sich noch im Entwurf befindet. Ein Admin/Trainer konnte damit
`POST /api/v1/invoices/{id}/mark-paid` für eine `draft`-Rechnung aufrufen und
sie direkt auf `status = 'paid'` setzen, **ohne** je `finalize()` durchlaufen
zu haben. Da `invoice_number` ausschließlich in `finalize()`/`cancel()`
vergeben wird (T02/T03), blieb die Rechnung dauerhaft ohne Nummer — ein
Verstoß gegen die Kernanforderung des Change (feste Rechnungsnummer ab
"Offen", siehe `specs/invoice-status-lifecycle/spec.md:3-8`).

## Fix

Analog zum bereits etablierten Muster in `InvoiceController::finalize()`
(Statusprüfung im Controller mit 422, nicht in der Policy) wurde in
`InvoiceController::markAsPaid()`
(`backend/app/Http/Controllers/Api/InvoiceController.php:209-231`) **vor**
der bestehenden `isPaid()`-Prüfung ein Draft-Check ergänzt:

```php
if ($invoice->status === 'draft') {
    return response()->json([
        'message' => 'Ein Entwurf muss zuerst freigegeben werden, bevor er als bezahlt markiert werden kann.',
    ], 422);
}
```

**Warum Controller statt Policy (403 vs. 422):** Der bestehende Docblock von
`InvoicePolicy::markAsPaid()` (`InvoicePolicy.php:110-126`) beschrieb dieses
Verhalten bereits als Soll-Zustand ("the already-paid conflict is a 422
handled in `InvoiceController::markAsPaid()`, exactly like `finalize()`"),
setzte es aber nicht um. Der Fix erfüllt jetzt, was der Docblock schon
behauptete — Policy bleibt reiner Rollen-Check ("darf diese Rolle die Aktion
grundsätzlich ausführen"), Zustandskonflikte (draft, bereits bezahlt) werden
konsistent als 422 im Controller behandelt. Der Docblock wurde entsprechend
präzisiert (draft-Fall jetzt explizit erwähnt, nicht nur "already-paid").
Ein reiner Policy-Fix (`&& $invoice->status !== 'draft'`) hätte stattdessen
zu einem 403 für den draft-Fall geführt und wäre inkonsistent zum
403-vs-422-Split gewesen, den `task-T04.notes.md` bereits für `finalize()`
begründet.

**Bereits-bezahlt-Fall unverändert:** Die Frage, ob eine bereits bezahlte
Rechnung erneut als bezahlt markiert werden kann, war nicht Teil dieses
Bugfixes — der bestehende `isPaid()`-Check (422 "Rechnung ist bereits
bezahlt.") war schon vorhanden und durch den bestehenden Test `cannot mark
already paid invoice as paid` abgedeckt; dieses Verhalten wurde unverändert
gelassen.

## Geänderte Dateien

- `backend/app/Http/Controllers/Api/InvoiceController.php` — Draft-Check in
  `markAsPaid()` ergänzt (Zeilen ~209-231).
- `backend/app/Policies/InvoicePolicy.php` — Docblock von `markAsPaid()`
  präzisiert (kein Verhaltensänderung, nur Dokumentation nachgezogen).
- `backend/tests/Feature/InvoiceApiTest.php` — neuer Regressionstest
  `cannot mark a draft invoice as paid`: Trainer erhält 422 mit der
  erwarteten Nachricht, Rechnung bleibt `draft` ohne `invoice_number`.

## QA

`docker compose exec php composer qa` (Lint, PHPStan, `compat-check` gegen
PHP 8.2, Pest) läuft grün, Exit-Code 0. Alle 813 Tests bestehen, inklusive
des neuen Tests und der übrigen `InvoiceApiTest`-Fälle
(`trainer can mark invoice as paid`, `customer cannot mark invoice as paid`,
`cannot mark already paid invoice as paid`).

## Nicht Teil dieses Fixes

Der Reviewer nennt zusätzlich `InvoiceDetailModal.vue:163`, wo der
"Als bezahlt markieren"-Button weiterhin für `draft`-Rechnungen sichtbar
ist. Das ist eine reine Frontend-/TypeScript-Änderung (`dev-typescript`) und
außerhalb des Scopes dieses PHP-Bugfix-Auftrags — die serverseitige
Durchsetzung (dieser Fix) verhindert den fachlichen Fehler bereits
zuverlässig, unabhängig davon, ob der Button separat noch ausgeblendet wird.

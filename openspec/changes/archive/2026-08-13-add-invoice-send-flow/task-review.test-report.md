# Test-Report: add-invoice-send-flow (Change 2 von 4, Full-Diff-Review)

**Status:** fehler-vorhanden

Ein durch neu ergänzte Tests aufgedeckter funktionaler Bug (doppelter
Mail-Versand pro Klick, siehe Abschnitt "Fehler"). Alle übrigen Tests sind
grün, `composer lint`/`composer stan`/`composer compat-check` sowie
`npm run test`/`npm run lint`/`npm run build` laufen fehlerfrei durch.

## Hinzugefügte / geänderte Tests

- `backend/tests/Feature/InvoiceSendEmailTest.php`:
  - `it('lässt status und alle übrigen felder der rechnung unverändert nach erfolgreichem versand')`
    (parametrisiert über `reminded`/`overdue`, vergleicht das komplette
    `getAttributes()`-Array vor/nach dem Aufruf, nicht nur `status`).
  - `it('verschickt bei zweimaligem aufruf zwei separate e-mails und antwortet beide male mit 200')`
    — deckt den in der Aufgabenstellung geforderten Mehrfachversand-Fall ab
    und **deckt dabei einen echten Bug auf** (siehe unten).
- `frontend/src/views/invoices/InvoicesView.test.ts`:
  - `it('reicht die Backend-Fehlermeldung "keine E-Mail-Adresse hinterlegt" (HTTP 422) unverändert an handleApiError weiter')`
    — simuliert die konkrete 422-Antwort des Backends
    (`Für diesen Kunden ist keine E-Mail-Adresse hinterlegt.`) und prüft,
    dass exakt dieses Error-Objekt an `handleApiError()` durchgereicht wird
    (nicht nur, dass irgendein Fehler behandelt wurde).

**Nicht dupliziert** (bereits ausreichend laut Inventarisierung):
`InvoiceSentMailBankDetailsTest.php` (PDF-Anhang-Test prüft bereits
`toHaveCount(1)`, `mime === 'application/pdf'`, `as === '{invoice_number}.pdf'`
— präzise genug), `SendInvoiceEmailListenerTest.php` (prüft bereits explizit
`not->toBeInstanceOf(ShouldQueue::class)` per Reflection-freundlichem
`expect()`), `InvoiceSendEmailTest.php`s bestehender Rollen-Test für Trainer
bei `reminded`/`overdue` (deckt `InvoicePolicy::send()` für Nicht-Admin
bereits ab), `InvoiceSendDialog.test.ts`/`InvoiceDetailModal.test.ts`
(Akzeptanzkriterien aus T04/T06 vollständig abgedeckt, keine Lücke
gefunden).

## Akzeptanzkriterien-Abdeckung (Fokus: die 6 geprüften Lücken aus dem Auftrag)

- [x] 1. Statuswechsel-Freiheit für `reminded`/`overdue` inkl. Nebenfelder —
  neu getestet in `InvoiceSendEmailTest.php::it('lässt status und alle
  übrigen felder der rechnung unverändert nach erfolgreichem versand')`
  (beide Status, volles Attribut-Array inkl. `paid_date`, `updated_at`).
- [x] 2. Mehrfacher Versand — neu getestet in
  `InvoiceSendEmailTest.php::it('verschickt bei zweimaligem aufruf...')`.
  **Test schlägt fehl** — echter Bug, siehe "Fehler".
- [x] 3. PDF-Anhang-Präzision — bereits vorhanden und ausreichend präzise
  (`InvoiceSentMailBankDetailsTest.php::it('hängt das rechnungs-pdf als
  anhang an')`, prüft Anzahl, MIME-Typ und Dateiname).
- [x] 4. Frontend-422-Fehlerpfad "keine E-Mail-Adresse" — neu getestet in
  `InvoicesView.test.ts` (siehe oben). Hinweis: `handleApiError()` selbst
  ist in `InvoicesView.test.ts` gemockt (Projektkonvention), daher wird
  geprüft, dass das exakte Backend-Error-Objekt (inkl. `response.data.message`)
  unverändert an `handleApiError()` übergeben wird — mehr Tiefe ist ohne
  Entmocken von `handleApiError` (eigene, ungetestete Utility-Datei
  `frontend/src/utils/errorHandler.ts`, außerhalb des Diffs dieses Change)
  in diesem Test nicht sinnvoll erreichbar.
- [x] 5. `SendInvoiceEmail` implementiert nicht `ShouldQueue` — bereits
  vorhanden (`SendInvoiceEmailListenerTest.php::it('implementiert nicht
  mehr shouldqueue')`), zusätzlich synchron bestätigt über
  `Mail::assertNothingQueued()`.
- [x] 6. `InvoicePolicy::send()` für Trainer (nicht nur Admin) — bereits
  vorhanden (`InvoiceSendEmailTest.php`, Test `'trainer can send an
  invoice email for reminded and overdue invoices'`).

## Ausführungs-Ergebnis

### Backend: `docker compose exec php vendor/bin/pest --no-coverage`

```
Tests:    1 failed, 828 passed (2597 assertions)
Duration: 30.28s

FAILED  Tests\Feature\InvoiceSendEmailTest > it verschickt bei zweimaligem aufruf...
The expected [App\Mail\InvoiceSent] mailable was sent 4 times instead of 2 times.
```

Isolierter Lauf (`--filter=InvoiceSendEmailTest`): 12 von 13 Tests grün,
derselbe eine Fehlschlag, 38 Assertions.

### `composer lint` / `composer compat-check` / `composer stan`

Alle drei laufen einzeln grün (PASS, 310 Dateien / PHPCompatibility ohne
Treffer / PHPStan "No errors"). `composer qa` als Gesamtlauf wurde bewusst
nicht verwendet (bekanntes 300s-Timeout-Risiko laut `task-T03.notes.md`),
stattdessen phasenweise ausgeführt wie im Auftrag vorgegeben.

### Frontend

```
npm run test  → 23 Testdateien, 264 Tests, alle grün (inkl. 25 in
                InvoicesView.test.ts, 8 in InvoiceSendDialog.test.ts,
                17 in InvoiceDetailModal.test.ts)
npm run lint  → exit 0, 0 Errors, 3121 Warnings (alle vorbestehend,
                keine neuen Verstöße in geänderten Dateien)
npm run build → exit 0, vue-tsc -b + vite build erfolgreich
```

## Fehler

- `backend/tests/Feature/InvoiceSendEmailTest.php::it verschickt bei
  zweimaligem aufruf zwei separate e-mails und antwortet beide male mit
  200` schlägt fehl:
  - Erwartet: 2 versendete `InvoiceSent`-Mails bei 2 Aufrufen von
    `POST /invoices/{id}/send-email`.
  - Erhalten: 4 versendete Mails (`MailFake` zählt 4 statt 2).
  - **Root Cause (verifiziert, nicht nur vermutet):**
    `docker compose exec php php artisan event:list` zeigt für
    `App\Events\InvoiceWasSent` **zwei** registrierte Listener-Einträge:
    ```
    App\Events\InvoiceWasSent
    ⇂ App\Listeners\SendInvoiceEmail
    ⇂ App\Listeners\SendInvoiceEmail@handle
    ```
    `backend/app/Providers/AppServiceProvider.php` registriert
    `Event::listen(InvoiceWasSent::class, SendInvoiceEmail::class)`
    **manuell**, zusätzlich zur automatischen Laravel-Event-Discovery
    (scannt `app/Listeners/` nach Klassen mit passend typisierter
    `handle()`-Methode und registriert sie eigenständig). Beide
    Mechanismen sind gleichzeitig aktiv, daher feuert
    `SendInvoiceEmail::handle()` bei jedem `InvoiceWasSent::dispatch()`
    **zweimal**, und mit dem synchronen `Mail::to(...)->send(...)` aus T01
    (statt `->queue(...)`) wird die Rechnung dadurch bei jedem einzelnen
    Klick auf "Aus der App versenden" **zweimal** an den Kunden verschickt.
  - **Wichtige Einordnung:** Dieses doppelte Registrierungsmuster
    (manuelles `Event::listen()` + Auto-Discovery) existiert bereits auf
    `main` und betrifft auch `BookingCreated`/`SendBookingConfirmationEmail`
    und `UserRegistered`/`SendWelcomeEmail` (`event:list` zeigt dieselbe
    doppelte Registrierung dort). Es ist also **kein neu eingeführter Bug
    dieses Change**, sondern ein vorbestehendes, projektweites Muster. Bei
    den bisherigen `ShouldQueue`-Listenern blieb der Effekt (zwei
    identische Jobs in der `jobs`-Tabelle statt einem) praktisch
    unauffällig/unbemerkt. **Erst T01s bewusste Umstellung von
    `SendInvoiceEmail` auf synchronen Versand** (`design.md` Decision D4)
    macht die Doppel-Registrierung für dieses konkrete Feature sofort
    beobachtbar und fachlich schädlich: der Kunde erhält die Rechnung real
    zweimal pro Klick, exakt der im Auftrag benannte
    "Mehrfacher-Versand"-Fall — nur ausgelöst durch einen einzigen
    Klick/Request, nicht durch zwei.
  - **Nicht von mir gefixt** (Produktivcode-Verbot). Empfehlung an
    `dev-php`/Reviewer: entweder die manuelle `Event::listen(...)`-Zeile in
    `AppServiceProvider.php:79-82` entfernen (Auto-Discovery reicht aus)
    oder Auto-Discovery projektweit deaktivieren
    (`shouldDiscoverEvents(): false` im `EventServiceProvider`/
    `AppServiceProvider`) und stattdessen ausschließlich manuell
    registrieren — Letzteres ist konsistenter mit dem bereits vorhandenen
    expliziten `Event::listen()`-Block für alle drei Event/Listener-Paare.
    Diese Entscheidung sollte, weil sie auch `BookingCreated` und
    `UserRegistered` betrifft, nicht lokal nur für `InvoiceWasSent`
    gepatcht werden, sondern einheitlich für alle drei — potenziell ein
    eigener kleiner Fix-Change, da er über den Scope von Change 2 hinausgeht.

## Hinweis zur PostgreSQL-Nachprüfung (Retry-/Transaktions-Bug aus Change 1)

Geprüft, ob `InvoiceController::sendEmail()` (T03) oder die Mail-Infrastruktur
(T01/T02: `InvoiceWasSent`, `SendInvoiceEmail`, `InvoiceSent`,
`InvoicePdfRenderer`) DB-Schreiboperationen, Transaktionen oder Retry-Logik
enthalten:

```
grep -n "DB::transaction\|DB::\|Retry\|retry" \
  backend/app/Listeners/SendInvoiceEmail.php \
  backend/app/Mail/InvoiceSent.php \
  backend/app/Services/InvoicePdfRenderer.php \
  backend/app/Events/InvoiceWasSent.php
→ kein Treffer
```

`InvoiceController::sendEmail()` (`InvoiceController.php:432-466`) ruft
ausschließlich `$invoice->load(...)` (lesend) und `InvoiceWasSent::dispatch()`
auf — **kein** `$invoice->update()`/`->save()`, **kein** `DB::transaction()`,
**kein** Retry-Loop (im Unterschied zu `finalize()`/`cancel()` in derselben
Datei, die die dokumentierte Postgres-Savepoint-Problematik aus Change 1
haben). Das deckt sich mit der Architektur-Entscheidung in `design.md`
Goals/Non-Goals ("Kein Statuswechsel durch `sendEmail()`"). **Daher wurde
kein zusätzlicher Testlauf gegen echtes PostgreSQL durchgeführt** — es gibt
keine DB-Schreiboperation, die eine Postgres-spezifische
Transaktions-Poisoning-Problematik auslösen könnte, wie sie in Change 1 bei
`finalize()`/`cancel()` gefunden wurde.

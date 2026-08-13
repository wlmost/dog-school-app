## Why

`backend/app/Providers/AppServiceProvider.php` registriert in `boot()`
(Zeilen 73–87) drei Event/Listener-Paare zusätzlich zur automatischen
Laravel-Event-Discovery manuell per `Event::listen(...)`:

- `BookingCreated::class → SendBookingConfirmationEmail::class` (Zeile 74–77)
- `InvoiceWasCreated::class → SendInvoiceCreatedEmail::class` (Zeile 79–82)
- `UserRegistered::class → SendWelcomeEmail::class` (Zeile 84–87)

Alle drei Listener implementieren `ShouldQueue` mit typisierter
`handle()`-Methode (`SendBookingConfirmationEmail::handle(BookingCreated $event)`,
`backend/app/Listeners/SendBookingConfirmationEmail.php:28`;
`SendWelcomeEmail::handle(UserRegistered $event)`,
`backend/app/Listeners/SendWelcomeEmail.php:28`) — Laravel discovered sie
dadurch zusätzlich automatisch. Laut Triage
(`openspec/triage/20260813133000-fix-duplicate-event-listener-registration.md`)
zeigt `php artisan event:list` für alle drei Paare je zwei
Listener-Einträge.

Konsequenz: Jedes der beiden hier relevanten Events hat zwei aktive
Listener-Registrierungen.

- `SendWelcomeEmail::handle()` sendet die Willkommens-E-Mail synchron
  (`Mail::to(...)->send(...)`,
  `backend/app/Listeners/SendWelcomeEmail.php:31-32`) → jede
  Registrierung erzeugt real **zwei** Willkommens-E-Mails.
- `SendBookingConfirmationEmail::handle()` queued die Bestätigungs-Mail
  (`Mail::to(...)->queue(...)`,
  `backend/app/Listeners/SendBookingConfirmationEmail.php:37-38`) → jede
  Buchung erzeugt vermutlich zwei Queue-Jobs und (bei
  Worker-Verarbeitung) zwei E-Mails. Weder `SendBookingConfirmationEmail`
  noch `SendWelcomeEmail` implementieren `ShouldBeUnique`
  (`Illuminate\Contracts\Queue\ShouldBeUnique`) — es gibt keinen
  eingebauten Dedupe-Mechanismus.

Kundensichtbarer Effekt: Kunden erhalten doppelte Buchungsbestätigungen,
neue Nutzer doppelte Willkommens-Mails.

Bestehende Tests decken das nicht auf:
`backend/tests/Feature/EmailNotificationTest.php:34-49` prüft mit
`Mail::assertQueued(BookingConfirmation::class, function ($mail) {...})`
nur, dass *mindestens* eine passende Mail gequeued wurde — nicht die
exakte Anzahl. Für `UserRegistered` / `SendWelcomeEmail` existiert
aktuell keine Testabdeckung.

## What Changes

- Die beiden redundanten `Event::listen(...)`-Blöcke für
  `BookingCreated → SendBookingConfirmationEmail` und
  `UserRegistered → SendWelcomeEmail` werden aus
  `AppServiceProvider::boot()` entfernt. Laravels automatische
  Event-Discovery übernimmt die Registrierung weiterhin unverändert.
- Regressionstests werden ergänzt, die verifizieren, dass jedes der
  beiden Events nach dem Fix genau eine Mail auslöst (nicht zwei).
- **Unverändert bleibt** der `InvoiceWasCreated`-Block (Zeile 79–82).
  Dieses dritte Paar ist auf `main` ebenfalls noch dupliziert, wurde
  aber bereits im Rahmen von `feature/add-invoice-send-flow` (PR #86,
  noch ungemergt) behoben — dort wurden Event/Listener/Mail zudem
  umbenannt (`InvoiceWasCreated` → `InvoiceWasSent`,
  `SendInvoiceCreatedEmail` → `SendInvoiceEmail`). Dieser Change fasst
  den Invoice-Block nicht an, um Merge-Konflikte mit PR #86 zu vermeiden.
  Sobald PR #86 gemergt ist, ist auch das dritte Paar behoben —
  unabhängig von der Merge-Reihenfolge dieses Changes. Keine
  Abhängigkeit zwischen den beiden Changes.
- **Non-Goal:** Keine `ShouldBeUnique`-Härtung der Listener. In der
  Triage als möglicher separater Folge-Change vorgeschlagen, aber für
  einen Minimal-Fix der doppelten Registrierung nicht nötig (YAGNI).

## Capabilities

### New Capabilities
- `event-listener-single-registration`: Schlanke, rein technische
  Capability, die sicherstellt, dass Events mit auto-discovered
  Listenern nicht zusätzlich manuell registriert werden und dadurch
  genau einmal statt doppelt ausgelöst werden. Bewusst als eigene,
  minimale Capability angelegt statt einer Nachdokumentation ganzer
  fachlicher Mail-Capabilities (siehe `design.md`).

### Modified Capabilities
_Keine._

## Impact

**Backend:**
- `backend/app/Providers/AppServiceProvider.php` — die beiden
  `Event::listen(...)`-Blöcke für `BookingCreated` und `UserRegistered`
  (Zeile 74–77, 84–87) werden entfernt, inklusive dadurch ungenutzter
  `use`-Imports. Der `InvoiceWasCreated`-Block (Zeile 79–82) bleibt
  unverändert.
- Neue/erweiterte Testdatei unter `backend/tests/Feature/` —
  Regressionstest für exakt eine Mail pro Event-Dispatch.

**Nicht betroffen (geprüft, kein Änderungsbedarf):**
- `backend/app/Listeners/SendBookingConfirmationEmail.php`,
  `backend/app/Listeners/SendWelcomeEmail.php`,
  `backend/app/Events/BookingCreated.php`,
  `backend/app/Events/UserRegistered.php` — keine Änderung, die
  Listener/Events bleiben inhaltlich unverändert; nur die zusätzliche
  Registrierung in `AppServiceProvider.php` entfällt.
- Keine Migration, keine DB-Änderung, kein raw SQL.
- `frontend/**` — kein Frontend betroffen, reiner Backend-Fix.

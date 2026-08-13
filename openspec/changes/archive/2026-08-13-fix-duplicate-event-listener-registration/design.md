# Design: fix-duplicate-event-listener-registration

## Wie

In `backend/app/Providers/AppServiceProvider.php::boot()` die beiden
`Event::listen(...)`-Blöcke für `BookingCreated` (Zeile 74–77) und
`UserRegistered` (Zeile 84–87) entfernen. Der `InvoiceWasCreated`-Block
(Zeile 79–82) bleibt unverändert liegen (siehe proposal.md, Abschnitt
"What Changes").

Laravels automatische Event-Discovery (aktiv, da beide Listener eine
typisierte `handle()`-Methode besitzen — `SendBookingConfirmationEmail::handle(BookingCreated $event)`
und `SendWelcomeEmail::handle(UserRegistered $event)`) übernimmt die
Registrierung weiterhin ohne Code-Änderung an den Listenern selbst. Damit
bleiben die nicht-`handle()`-Methoden (`shouldQueue()`, `failed()`) und
die `ShouldQueue`-Implementierung beider Listener unverändert wirksam.

Die nicht mehr benötigten `use`-Imports (`App\Events\BookingCreated`,
`App\Listeners\SendBookingConfirmationEmail`, `App\Events\UserRegistered`,
`App\Listeners\SendWelcomeEmail` — Zeilen 7, 9, 10, 12) werden mit
entfernt, sofern sie nach dem Entfernen der `Event::listen()`-Aufrufe
ungenutzt sind. `App\Events\InvoiceWasCreated` und
`App\Listeners\SendInvoiceCreatedEmail` (Zeilen 8, 11) bleiben, da der
Invoice-Block weiter besteht.

## Betroffene Module

- `backend/app/Providers/AppServiceProvider.php` — einzige zu ändernde
  Bestandsdatei (Task T01).
- Neue/erweiterte Testdatei unter `backend/tests/Feature/` (Task T02) —
  Regressionstest, der die Listener-Anzahl bzw. Mail-/Job-Anzahl pro
  Event-Dispatch prüft.

## DB-/Migrations-Bezug (CLAUDE.md Abschnitt "Projektspezifische
Workflow-Regeln")

Keine Migration, keine DB-Änderung, keine raw SQL. Reine
PHP-Code-Änderung (Entfernen von Zeilen) plus Testdatei. Damit
unkritisch bezüglich MySQL/Postgres-Portabilität.

## Spec-Delta: minimal statt Nachdokumentation ganzer Mail-Capabilities

Für das beobachtete Fehlverhalten existiert aktuell keine
openspec-Capability in `openspec/specs/` (geprüft: keine Datei unter
`openspec/specs/` referenziert `SendBookingConfirmationEmail`,
`SendWelcomeEmail`, `BookingCreated` oder `UserRegistered`). Ursprünglich
war für diesen Change **kein** Spec-Delta vorgesehen (reiner technischer
Bugfix, keine neue fachliche Anforderung). `openspec validate` verlangt
jedoch strukturell mindestens ein Delta pro Change (bestätigt:
`openspec validate fix-duplicate-event-listener-registration` schlägt
ohne `specs/`-Verzeichnis mit `[ERROR] file: Change must have at least
one delta` fehl) — dieses Muster zeigt sich auch im bereits archivierten
Ein-Task-Fix `fix-invoice-pdf-status-visible`, der trotz Ein-Datei-Scope
ein Spec-Delta enthält.

Um kein Overengineering zu betreiben (keine Nachdokumentation ganzer
Mail-Capabilities wie "Buchungsbestätigungs-Mail" oder "Willkommens-Mail"
als eigene fachliche Capabilities), wird stattdessen eine **schlanke,
rein technische** Capability `event-listener-single-registration`
angelegt, die exakt das abbildet, was dieser Fix sicherstellt: dass jedes
der beiden Events genau einen aktiven Listener hat und genau eine
E-Mail pro Dispatch auslöst. Siehe
`specs/event-listener-single-registration/spec.md`.

## Koexistenz mit PR #86 (`feature/add-invoice-send-flow`)

PR #86 ändert denselben Datei-Bereich (`AppServiceProvider.php`,
Invoice-Block) unabhängig von den hier geänderten Zeilen. Da dieser
Change nur die Booking- und UserRegistered-Blöcke anfasst und den
Invoice-Block unverändert lässt, ist bei einem späteren Merge von PR #86
höchstens ein trivialer Datei-Kontext-Konflikt zu erwarten (keine
inhaltliche Überschneidung). Keine Abhängigkeit zwischen den Changes;
Merge-Reihenfolge ist beliebig.

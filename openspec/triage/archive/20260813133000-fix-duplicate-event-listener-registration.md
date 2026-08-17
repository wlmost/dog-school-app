# Triage: fix-duplicate-event-listener-registration

**Pfad:** klein
**Geschätzter Umfang:** 1 Bestandsdatei geändert (`backend/app/Providers/AppServiceProvider.php`), plus voraussichtlich 1 neue/erweiterte Testdatei unter `backend/tests/Feature/` oder `backend/tests/Unit/`. Sprache: PHP/Laravel (Backend) — `dev-php`. Kein Frontend betroffen.
**Risiko:** mittel — kein Schema-/Migrations-/Schnittstellen-Bruch, aber direkter kundensichtbarer Effekt (doppelte Buchungsbestätigungs- und Willkommens-Mails), Änderung an Event-/Queue-Verhalten.
**Klarheit:** klar — Fix-Muster ist bereits an einer Stelle im Repo real umgesetzt und dokumentiert (siehe unten); die Anforderung ist eindeutig, mit zwei offenen Detailfragen an den Architekten (keine Rückfragen an den User nötig).

## Anforderung (Zusammenfassung)

In `AppServiceProvider::boot()` sind `BookingCreated → SendBookingConfirmationEmail`
und `UserRegistered → SendWelcomeEmail` zusätzlich zur automatischen Laravel-
Event-Discovery manuell per `Event::listen()` registriert. Beide Listener
implementieren `ShouldQueue` mit typisierter `handle()`-Methode, wodurch Laravel
sie zusätzlich automatisch discovered — Ergebnis: jedes Event hat zwei aktive
Listener-Registrierungen, jede Buchung/Registrierung erzeugt zwei Queue-Jobs
und (ohne Job-Deduplizierung) zwei E-Mails. Analog zum bereits gefixten
Rechnungs-Fall soll die redundante manuelle `Event::listen()`-Registrierung
für diese zwei Paare entfernt werden.

## Empirische Verifikation

- `docker compose exec php php artisan event:list` (Laravel 11.51, `backend/composer.lock`)
  zeigt für **alle drei** in `AppServiceProvider.php` registrierten Paare je
  zwei Einträge (einen ohne, einen mit `@handle`-Suffix — klassisches Zeichen
  für "manuell registriert + auto-discovered"):
  - `App\Events\BookingCreated` → `SendBookingConfirmationEmail` (2×)
  - `App\Events\InvoiceWasCreated` → `SendInvoiceCreatedEmail` (2×)
  - `App\Events\UserRegistered` → `SendWelcomeEmail` (2×)
- **Wichtige Präzisierung zur Anforderung:** Die in der Anforderung genannten
  Klassennamen `App\Listeners\SendInvoiceEmail` / `InvoiceWasSent` existieren
  **nicht auf `main`** — dort heißen sie weiterhin `SendInvoiceCreatedEmail` /
  `InvoiceWasCreated`, und die doppelte Registrierung ist auf `main` **noch
  nicht gefixt** (bestätigt: 2 Listener-Einträge in `event:list`, s.o.).
  Die genannten Namen existieren auf dem noch offenen Branch
  `feature/add-invoice-send-flow` (PR #86, ungemergt) — dort wurden Event/
  Listener/Mail im Zuge des Send-Flow-Features umbenannt (`InvoiceWasCreated`
  → `InvoiceWasSent`, `SendInvoiceCreatedEmail` → `SendInvoiceEmail`,
  `InvoiceCreated`-Mail → `InvoiceSent`) **und** die redundante
  `Event::listen()`-Zeile dort entfernt. Der Kommentarblock in
  `AppServiceProvider.php` auf diesem Branch benennt explizit dasselbe Problem
  für Booking/UserRegistered und verweist wörtlich auf einen
  `fix-duplicate-event-listener-registration`-Folge-Change — das ist exakt
  dieser Task. Diese Referenz ist damit **kein Halluzinations-Fund**, sondern
  bezieht sich korrekt auf ungemergten Code; ich habe sie über
  `git fetch origin feature/add-invoice-send-flow` + Diff gegen `main`
  verifiziert (`backend/app/Providers/AppServiceProvider.php`,
  `backend/app/Events/InvoiceWasSent.php`,
  `backend/app/Listeners/SendInvoiceEmail.php`).
- Vollständige Suche nach weiteren `Event::listen(`-Aufrufen im Projekt: nur
  die drei genannten in `AppServiceProvider.php`, kein `EventServiceProvider`
  oder sonstige Registrierungsstelle vorhanden. Keine weiteren betroffenen
  Event/Listener-Paare identifiziert.
- `QUEUE_CONNECTION=redis` in `backend/.env` (lokal), `database` in
  `backend/.env.example`. Weder `SendBookingConfirmationEmail` noch
  `SendWelcomeEmail` implementieren `Illuminate\Contracts\Queue\ShouldBeUnique`
  — es gibt also keinen eingebauten Dedupe-Mechanismus. Die doppelte
  Registrierung führt somit vermutlich tatsächlich zu zwei Queue-Jobs und bei
  Worker-Verarbeitung zu zwei E-Mails; das war mit den vorhandenen Mitteln
  (kein laufender Queue-Worker-Test in dieser Session) nicht bis zum
  tatsächlichen Mail-Versand nachgestellt, sondern nur bis zur doppelten
  Job-Registrierung über `event:list` bestätigt.
- Bestehende Tests: `backend/tests/Feature/BookingApiTest.php` und
  `backend/tests/Feature/DogRegistrationRequestApiTest.php` referenzieren
  Booking/Registrierung, aber **keiner der beiden testet `BookingCreated`,
  `SendBookingConfirmationEmail`, `UserRegistered` oder `SendWelcomeEmail`**
  (kein `Event::fake()`, `Queue::fake()` oder `Mail::fake()`-Assert dazu).
  Es gibt also keine bestehenden (grünen, aber blinden) Tests, die die
  Duplizierung verdecken — es gibt schlicht **keine Testabdeckung** für dieses
  Verhalten. Ein eigener `App\Console\Commands\TestEventIntegration`
  (`test:events`) prüft nur `count($listeners) > 0`, nicht `=== 1` — deckt die
  Duplizierung also ebenfalls nicht auf.

## Rückfragen an den User

Keine — Klarheit ist gegeben. Zwei Entscheidungsfragen gehen an den
Architekten (kein User-Blocker):

1. Soll der Fix jetzt unabhängig auf `main` erfolgen (Invoice-Paar bleibt
   unangetastet, da bereits Teil von PR #86) — Empfehlung: ja, der
   Merge-Konflikt in `AppServiceProvider.php` beim späteren Mergen von PR #86
   ist trivial (3-Zeilen-Block).
2. Soll der Fix nur die redundante Registrierung entfernen (Minimal-Fix,
   analog zum Invoice-Fall), oder zusätzlich `ShouldBeUnique` auf die
   Listener als Härtung ergänzen? Empfehlung für den "klein"-Pfad: nur
   Minimal-Fix + Regressionstest, der `app('events')->getListeners(...)`
   auf genau 1 Eintrag pro Event prüft (bzw. `Queue::fake()`/`Mail::fake()`-
   Assert auf genau 1 Job/Mail pro Dispatch) — Härtung ggf. als separater
   Folge-Change.

## Empfohlene nächste Aktion

`@architect` (Modus A) erstellt einen kleinen openspec-Change
`fix-duplicate-event-listener-registration` mit einer Task für `dev-php`:
Entfernen der beiden `Event::listen()`-Blöcke für `BookingCreated` und
`UserRegistered` in `backend/app/Providers/AppServiceProvider.php` sowie
einem Regressionstest, der pro Event genau einen registrierten Listener
(bzw. genau einen gequeueten Job) sicherstellt. Anschließend `reviewer` +
`tester` parallel, dann Abnahme — kein Skeptiker-/User-Gate-1-Overhead nötig
laut "klein"-Pfad, aber der Architekt sollte im `design.md` kurz auf die
Koexistenz mit dem offenen PR #86 (`feature/add-invoice-send-flow`)
hinweisen.

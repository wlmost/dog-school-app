# Notes: T01 + T02 — fix-duplicate-event-listener-registration

## T01: Redundante Event::listen()-Registrierung entfernen

**Datei:** `backend/app/Providers/AppServiceProvider.php`

Entfernt in `boot()`:

```php
Event::listen(
    BookingCreated::class,
    SendBookingConfirmationEmail::class
);

Event::listen(
    UserRegistered::class,
    SendWelcomeEmail::class
);
```

Der `InvoiceWasCreated`/`SendInvoiceCreatedEmail`-Block (unverändert,
bleibt bestehen — behoben in separatem PR #86) und die zugehörigen
`use`-Imports (`App\Events\InvoiceWasCreated`,
`App\Listeners\SendInvoiceCreatedEmail`) bleiben.

Entfernte, dadurch ungenutzte `use`-Imports:
`App\Events\BookingCreated`, `App\Events\UserRegistered`,
`App\Listeners\SendBookingConfirmationEmail`,
`App\Listeners\SendWelcomeEmail`. `Illuminate\Support\Facades\Event`
bleibt (wird für den Invoice-Block weiterhin gebraucht).

Laravels automatische Event-Discovery übernimmt die Registrierung
weiterhin unverändert (`SendBookingConfirmationEmail::handle(BookingCreated $event)`
und `SendWelcomeEmail::handle(UserRegistered $event)` sind typisiert).

**Verifikation via `event:list`** (`docker compose exec -T php php artisan
event:list`), Zustand nach dem Fix:

```
App\Events\BookingCreated
  ⇂ App\Listeners\SendBookingConfirmationEmail@handle (ShouldQueue)
App\Events\InvoiceWasCreated
  ⇂ App\Listeners\SendInvoiceCreatedEmail (ShouldQueue)
  ⇂ App\Listeners\SendInvoiceCreatedEmail@handle (ShouldQueue)
App\Events\UserRegistered
  ⇂ App\Listeners\SendWelcomeEmail@handle (ShouldQueue)
```

`BookingCreated` und `UserRegistered` haben je genau einen
Listener-Eintrag. `InvoiceWasCreated` zeigt bewusst weiterhin zwei
Einträge — dieser Block war nicht Teil des Fixes (siehe proposal.md,
Abschnitt "What Changes" / Koexistenz mit PR #86).

## T02: Regressionstests ergänzt

**Datei:** `backend/tests/Feature/EmailNotificationTest.php` (erweitert,
keine neue Datei — passt stilistisch zur bestehenden Pest-Struktur mit
`describe()`/`it()`).

Ergänzt:

1. `describe('Booking Confirmation Emails', ...)`: neuer Test „sends
   exactly one confirmation email when creating a booking, not two"
   — `Mail::assertQueued(BookingConfirmation::class, 1)` (Count-Form
   statt nur Closure-Match).
2. Neues `describe('User Registration Emails', ...)` mit zwei Tests:
   - „sends exactly one welcome email when registering a new user, not
     two" — `Mail::assertSent(WelcomeEmail::class, 1)`. Registrierung
     erfolgt über `POST /api/v1/auth/register` als eingeloggter Admin
     (`RegisterRequest::authorize()` verlangt Admin-Rolle).
   - „sends the welcome email to the newly registered user" — prüft
     `hasTo()` auf die neu registrierte Adresse (Regressionsschutz
     gegen falschen Empfänger, kein Duplizierungs-Fokus, aber sinnvolle
     Ergänzung).
3. Import `use App\Mail\WelcomeEmail;` ergänzt. `App\Mail\WelcomeEmail`
   implementiert nicht `ShouldQueue` (nur `Queueable`-Trait ohne
   Interface) und wird per `Mail::to(...)->send(...)` synchron
   verschickt — daher `assertSent()`, nicht `assertQueued()` (verifiziert
   in `backend/app/Mail/WelcomeEmail.php` und
   `backend/app/Listeners/SendWelcomeEmail.php:31-32`).

**Manuelle Regressionsverifikation** (Akzeptanzkriterium T02): T01
temporär per `git stash` zurückgesetzt (Duplizierung wiederhergestellt),
`vendor/bin/pest tests/Feature/EmailNotificationTest.php` erneut
ausgeführt:

```
FAILED Booking Confirmation Emails > it sends exactly one confirmation email...
  The expected [App\Mail\BookingConfirmation] mailable was queued 2 times instead of 1 times.
FAILED User Registration Emails > it sends exactly one welcome email...
  The expected [App\Mail\WelcomeEmail] mailable was sent 2 times instead of 1 times.
```

Danach `git stash pop` — T01-Stand wiederhergestellt, alle 18 Tests in
der Datei grün.

## QA-Ergebnisse (Docker-Umgebung)

- `docker compose exec -T php composer lint` → PASS (307 files)
- `docker compose exec -T php composer stan` → No errors (205/205)
- `docker compose exec -T php composer compat-check` → keine Ausgabe
  (keine Verstöße)
- `docker compose exec -T php composer test` → 816 passed (2553
  assertions), Dauer ~28s
- `docker compose exec -T php php artisan event:list` → manuell gegen
  `BookingCreated`/`UserRegistered`/`InvoiceWasCreated` geprüft (siehe
  T01-Abschnitt oben)

`composer qa` (Aggregat aus lint + stan + compat-check + test) damit
vollständig grün.

## Abweichungen von Spec/Tasks

Keine. Beide Tasks wurden wie in `tasks.md`/`design.md` beschrieben
umgesetzt, der `InvoiceWasCreated`-Block wurde nicht angefasst.

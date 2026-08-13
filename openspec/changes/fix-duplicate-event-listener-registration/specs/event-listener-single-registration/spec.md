## ADDED Requirements

### Requirement: Jedes Event hat genau eine aktive Listener-Registrierung

`AppServiceProvider::boot()` SHALL keine zusätzliche manuelle
`Event::listen(...)`-Registrierung für Events enthalten, deren Listener
bereits über eine typisierte `handle()`-Methode von Laravels
automatischer Event-Discovery erfasst werden. Jedes betroffene Event
SHALL beim Dispatch genau einen Listener-Aufruf auslösen, nicht mehrere.

#### Scenario: BookingCreated löst genau eine Buchungsbestätigungs-Mail aus
- **GIVEN** eine gültige Buchung wird erstellt
- **WHEN** `App\Events\BookingCreated` dispatcht wird
- **THEN** wird `App\Listeners\SendBookingConfirmationEmail::handle()`
  genau einmal ausgeführt
- **AND** es wird genau eine `App\Mail\BookingConfirmation`-Mail gequeued

#### Scenario: UserRegistered löst genau eine Willkommens-Mail aus
- **GIVEN** ein neuer Nutzer wird registriert
- **WHEN** `App\Events\UserRegistered` dispatcht wird
- **THEN** wird `App\Listeners\SendWelcomeEmail::handle()` genau einmal
  ausgeführt
- **AND** es wird genau eine `App\Mail\WelcomeEmail`-Mail versendet

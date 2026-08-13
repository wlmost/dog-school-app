# Tasks für fix-duplicate-event-listener-registration

## T01: Redundante Event::listen()-Registrierung entfernen
- **Agent:** dev-php
- **Dateien:** `backend/app/Providers/AppServiceProvider.php`
- **Abhängigkeiten:** keine
- **Beschreibung:** In `boot()` die beiden `Event::listen(...)`-Blöcke für
  `BookingCreated::class → SendBookingConfirmationEmail::class` (aktuell
  Zeile 74–77) und `UserRegistered::class → SendWelcomeEmail::class`
  (aktuell Zeile 84–87) entfernen. Der `InvoiceWasCreated`-Block
  (Zeile 79–82) bleibt unverändert bestehen. Die zugehörigen `use`-Imports
  (`App\Events\BookingCreated`, `App\Listeners\SendBookingConfirmationEmail`,
  `App\Events\UserRegistered`, `App\Listeners\SendWelcomeEmail`) entfernen,
  sofern danach ungenutzt — `App\Events\InvoiceWasCreated` und
  `App\Listeners\SendInvoiceCreatedEmail` bleiben.
- **Akzeptanzkriterien:**
  - [x] `Event::listen(BookingCreated::class, SendBookingConfirmationEmail::class)`
        existiert nicht mehr in `AppServiceProvider.php`.
  - [x] `Event::listen(UserRegistered::class, SendWelcomeEmail::class)`
        existiert nicht mehr in `AppServiceProvider.php`.
  - [x] Der `InvoiceWasCreated`-Block bleibt unverändert (Diff zeigt keine
        Änderung an diesen Zeilen).
  - [x] Keine ungenutzten `use`-Imports übrig (durch `composer lint`/`composer stan`
        geprüft).
  - [x] `php artisan event:list` zeigt für `App\Events\BookingCreated` und
        `App\Events\UserRegistered` je genau **einen** Listener-Eintrag
        (statt bisher zwei).
  - [x] `composer qa` läuft ohne neue Fehler durch.

## T02: Regressionstests für Einfach-Zustellung ergänzen
- **Agent:** dev-php
- **Dateien:** `backend/tests/Feature/EmailNotificationTest.php` (erweitern)
  oder neue Testdatei `backend/tests/Feature/DuplicateEventListenerRegressionTest.php`
  — Entscheidung liegt beim Entwickler, je nachdem was sich stilistisch
  besser einfügt (bestehende Datei nutzt Pest-Funktionsstil mit
  `describe()`/`it()`, siehe `backend/tests/Feature/EmailNotificationTest.php:34-49`).
- **Abhängigkeiten:** T01
- **Beschreibung:** Regressionstests ergänzen, die verifizieren, dass nach
  dem Fix aus T01 jedes der beiden Events **genau eine** Mail auslöst statt
  potenziell zwei. Bestehende Tests wie
  `backend/tests/Feature/EmailNotificationTest.php:34-49` prüfen nur, dass
  *mindestens* eine passende Mail gequeued wurde (`Mail::assertQueued(...,
  closure)`) — das deckt eine Verdopplung nicht auf. Die neuen Tests
  müssen die exakte Anzahl prüfen, z. B. per
  `Mail::assertQueued(BookingConfirmation::class, 1)` bzw. Zählen der
  Mailbox-Einträge, sowie einen Test für `UserRegistered` /
  `SendWelcomeEmail`, für das aktuell keine Testabdeckung existiert
  (verifiziert: kein Treffer für `UserRegistered`/`SendWelcomeEmail` in
  `backend/tests/`).
- **Akzeptanzkriterien:**
  - [x] Test verifiziert: Dispatch von `BookingCreated` löst genau eine
        `BookingConfirmation`-Mail aus (nicht zwei).
  - [x] Test verifiziert: Dispatch von `UserRegistered` löst genau eine
        `WelcomeEmail`-Mail aus (nicht zwei).
  - [x] Tests schlagen fehl, wenn die `Event::listen()`-Duplizierung aus
        T01 wieder eingeführt würde (manuell verifiziert durch
        Kurzzeit-Revert von T01 im Zuge der Testentwicklung, danach
        wieder auf den T01-Stand zurück).
  - [x] `composer qa` (inkl. `composer test`) läuft grün.

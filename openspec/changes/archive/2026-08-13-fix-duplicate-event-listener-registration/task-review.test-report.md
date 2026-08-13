# Test-Report: fix-duplicate-event-listener-registration (Review-Prüfung)

**Status:** alle-gruen

## Prüfauftrag

Prüfung des kompletten Diffs `feature/fix-duplicate-event-listener-registration`
gegen `main` (in diesem Repo als uncommitted Working-Tree-Diff vorliegend, da
der Branch bereits ausgecheckt ist). Kein Produktivcode geändert — nur die
Testdatei `backend/tests/Feature/EmailNotificationTest.php`.

## 1. Grep nach weiteren `Event::listen(`-Vorkommen

```
grep -rn "Event::listen(" backend/app/ --include="*.php"
→ app/Providers/AppServiceProvider.php:70   (nur der InvoiceWasCreated-Block, bewusst unverändert)
```

- Kein weiterer Treffer für `->listen(` in `app/`.
- Kein `EventServiceProvider` im Projekt vorhanden (`find app/Providers -iname "*ServiceProvider*"`
  liefert nur `AppServiceProvider.php`).
- Alle drei Listener (`SendBookingConfirmationEmail`, `SendInvoiceCreatedEmail`,
  `SendWelcomeEmail`) implementieren `ShouldQueue` mit typisierter `handle()`
  → Auto-Discovery aktiv für alle drei.

**Ergebnis:** Architekten-Recherche verifiziert — keine übersehenen
Doppel-Registrierungs-Fälle gefunden. Der verbleibende `InvoiceWasCreated`-Block
ist wie in `proposal.md`/`design.md` dokumentiert bewusst unverändert
(behoben in separatem, noch ungemergtem PR #86).

## 2. TESTING.md-Konformität der neuen Tests

Geprüft gegen `TESTING.md` Abschnitt 2 (Datei-Header), 2.1 (Benennung),
7 (Groups), 9 (Verbote).

**Befunde vor Korrektur:**
- Die gesamte Datei `EmailNotificationTest.php` hatte **keine**
  `uses()->group(...)`-Zeile (Bestandslücke, nicht durch diesen Change
  verursacht, aber laut TESTING.md Abschnitt 9 für neue Test-Definitionen
  verboten wegzulassen).
- Die drei neu hinzugefügten `it(...)`-Beschreibungen waren auf Englisch
  ("sends exactly one confirmation email...", "sends exactly one welcome
  email...", "sends the welcome email to..."), entgegen der verbindlichen
  Regel aus Abschnitt 2.1 (deutsch, konjugiertes Verb in dritter Person,
  kleinschreibung). Die restlichen ~20 Bestandstests der Datei sind ebenfalls
  englisch — das wird per Boy-Scout-Regel bewusst NICHT pauschal umgeschrieben
  (siehe `feedback_large_mechanical_reformats`-Memory), nur die drei neuen
  Tests wurden korrigiert, da TESTING.md für **neue** Tests gewinnt.

**Vorgenommene Korrekturen (nur Testdatei, kein Produktivcode):**
- `uses()->group('feature', 'notification');` ergänzt (passt zum Pfad
  `tests/Feature/EmailNotificationTest.php`, kein `Api/`-Unterordner trotz
  HTTP-Requests im Test-Body, daher `feature` statt `api` — konsistent mit
  bestehendem `TrainerApiTest.php` unter `tests/Feature/`, das ebenfalls
  `feature` statt `api` verwendet, siehe `grep -n "uses()->group" tests/Feature/*.php`).
- Drei `it(...)`-Beschreibungen ins Deutsche übersetzt:
  - `sendet beim erstellen einer buchung genau eine bestätigungs-mail statt zwei`
  - `sendet bei der registrierung eines neuen nutzers genau eine willkommens-mail statt zwei`
  - `sendet die willkommens-mail an den neu registrierten nutzer`
- Factory-States (`User::factory()->admin()/->trainer()/->customer()`),
  Assertion-Stile (`Mail::assertQueued/assertSent` für Mail-Domäne, kein
  `expect()` für HTTP/Mail) und `RefreshDatabase` (global via `tests/Pest.php`
  auf `Feature`-Ordner angewendet) waren bereits konform — keine weiteren
  Korrekturen nötig.

## 3. Robustheit des `UserRegistered`/`WelcomeEmail`-Tests

Verifiziert per Code-Lesung (nicht nur Annahme):

- `POST /api/v1/auth/register` → `routes/api.php:83`, innerhalb der
  `auth:sanctum`-Middleware-Gruppe.
- `AuthController::register()` (`app/Http/Controllers/Api/AuthController.php:92-119`)
  ruft `UserRegistered::dispatch($user, $password)` (Zeile 107) synchron im
  echten Controller-Pfad auf — kein Umweg, keine Mock-Injektion.
- `RegisterRequest::authorize()` verlangt `$user->isAdmin()` — der Test nutzt
  korrekt `$this->actingAs($this->admin)`.
- `App\Mail\WelcomeEmail` implementiert **nicht** `ShouldQueue` (nur
  `Queueable`-Trait) → `Mail::assertSent()` statt `assertQueued()` ist korrekt
  (verifiziert in `app/Mail/WelcomeEmail.php:18-19`).
- Grep bestätigt: `UserRegistered::dispatch(...)` existiert nur an zwei
  Stellen im Code — `AuthController.php:107` (vom Test getroffen) und
  `TrainerController.php:98` (separater Pfad, nicht vom Test berührt, aber
  auch nicht betroffen vom Fix, da derselbe Event/Listener-Mechanismus gilt).

**Bewertung:** Der Test über den vollen HTTP-Request-Umweg ist robust und
bewusst als Integrationstest gewählt — er verifiziert den echten
Produktionscodepfad inklusive Autorisierung, nicht nur den Event-Dispatch
isoliert. Ein zusätzlicher `Event::fake()`-Test wäre redundant (würde nur
den Listener-Registrierungsmechanismus selbst prüfen, was bereits durch
`php artisan event:list`, laut `task-T01.notes.md` manuell verifiziert,
abgedeckt ist). Keine Ergänzung vorgenommen.

## 4. Bestehende Tests mit impliziter Abhängigkeit von der alten Doppel-Registrierung

```
grep -rln "BookingCreated\|UserRegistered\|SendWelcomeEmail\|SendBookingConfirmationEmail" backend/tests/ --include="*.php"
→ nur tests/Feature/EmailNotificationTest.php

grep -rln "BookingConfirmation\|WelcomeEmail" backend/tests/ --include="*.php"
→ nur tests/Feature/EmailNotificationTest.php
```

Keine anderen Testdateien referenzieren diese Events/Listener/Mailables.
Insbesondere kein Treffer für `assertQueued(..., 2)` / `assertSent(..., 2)`
im Kontext von Booking- oder Registrierungs-Mails. Kein Risiko eines
"falschen Sicherheitsgefühls" durch andere, unentdeckt weiterhin grüne Tests.

## Hinzugefügte / geänderte Tests (durch Tester-Agent, zusätzlich zu T02)

- `backend/tests/Feature/EmailNotificationTest.php`: keine neuen Test-Cases
  hinzugefügt (T02-Abdeckung bereits vollständig für die Akzeptanzkriterien),
  aber 1 Zeile Group-Deklaration ergänzt und 3 bestehende (von T02 neu
  eingeführte) `it()`-Beschreibungen auf TESTING.md-konformes Deutsch
  umbenannt.

## Akzeptanzkriterien-Abdeckung (T02, `tasks.md`)

- [x] Dispatch von `BookingCreated` löst genau eine `BookingConfirmation`-Mail
      aus — `EmailNotificationTest.php::sendet beim erstellen einer buchung
      genau eine bestätigungs-mail statt zwei`
- [x] Dispatch von `UserRegistered` löst genau eine `WelcomeEmail`-Mail aus —
      `EmailNotificationTest.php::sendet bei der registrierung eines neuen
      nutzers genau eine willkommens-mail statt zwei`
- [x] Tests schlagen bei Regression fehl — laut `task-T01.notes.md` durch
      Entwickler manuell per Kurzzeit-Revert verifiziert (nicht erneut vom
      Tester wiederholt, da destruktiv gegenüber Produktivcode; Ergebnis aus
      Notes plausibel und nachvollziehbar dokumentiert:
      "queued 2 times instead of 1 times" / "sent 2 times instead of 1 times").
- [x] `composer qa` inkl. Tests läuft grün — siehe Ausführungs-Ergebnis unten.

## Ausführungs-Ergebnis

```
docker compose exec php vendor/bin/pest --no-coverage
...
Tests:    816 passed (2553 assertions)
Duration: 28.66s
```

Gezielter Lauf der geänderten Datei:

```
docker compose exec php vendor/bin/pest tests/Feature/EmailNotificationTest.php --no-coverage

PASS  Tests\Feature\EmailNotificationTest
✓ Booking Confirmation Emails → it sends confirmation email when crea…       0.26s
✓ Booking Confirmation Emails → it sendet beim erstellen einer buchun…       0.03s
✓ Booking Confirmation Emails → it sends confirmation email when conf…       0.04s
✓ Booking Confirmation Emails → it does not send email when booking c…       0.03s
✓ Booking Confirmation Emails → it includes correct booking details i…       0.03s
✓ User Registration Emails → it sendet bei der registrierung eines ne…       0.03s
✓ User Registration Emails → it sendet die willkommens-mail an den ne…       0.03s
✓ Invoice Creation Emails → it does not send email when creating an i…       0.04s
✓ Invoice Creation Emails → it does not send email when invoice creat…       0.03s
✓ Payment Reminder Emails → it sends reminders for overdue invoices v…       0.03s
✓ Payment Reminder Emails → it does not send reminders for paid invoi…       0.02s
✓ Payment Reminder Emails → it does not send reminders for cancelled…        0.02s
✓ Payment Reminder Emails → it respects the days overdue threshold           0.02s
✓ Payment Reminder Emails → it sends multiple reminders for multiple…        0.03s
✓ Payment Reminder Emails → it supports dry run mode without sending…        0.03s
✓ Payment Reminder Emails → it includes invoice details in reminder e…       0.03s
✓ Email Queue Configuration → it queues booking confirmation email in…       0.03s
✓ Email Queue Configuration → it does not queue an invoice email on c…       0.03s

Tests:    18 passed (37 assertions)
Duration: 0.86s
```

## Fehler

Keine.

## Sonstige Findings (nicht behoben, da außerhalb Testerscope bzw. bewusst nicht angefasst)

- `EmailNotificationTest.php` enthält weiterhin ~20 Bestandstests mit
  englischen `it()`-Beschreibungen. Boy-Scout-Regel aus TESTING.md würde
  eine Modernisierung erlauben, aber ein pauschaler Umschreib-Durchlauf
  wäre ein großer mechanischer Reformat ohne expliziten Auftrag (siehe
  Memory `feedback_large_mechanical_reformats`) — daher hier nicht
  vorgenommen. Für einen Architekten/User-Entscheid: eigener Folge-Change
  "Testdatei X auf TESTING.md-Stand bringen" wäre sauberer als Ad-hoc-Umbau
  im Rahmen dieses Bugfix-Changes.

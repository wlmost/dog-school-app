# Notes: T01 — Mail-Infrastruktur umbenennen (Created → Sent) und auf synchronen Versand umstellen

## Umgesetzt

- `backend/app/Events/InvoiceWasCreated.php` gelöscht, neu:
  `backend/app/Events/InvoiceWasSent.php` — 1:1-Kopie, nur Klassenname
  geändert, Konstruktor-Signatur `public Invoice $invoice` unverändert.
- `backend/app/Listeners/SendInvoiceCreatedEmail.php` gelöscht, neu:
  `backend/app/Listeners/SendInvoiceEmail.php`:
  - `implements ShouldQueue` und `use InteractsWithQueue;` entfernt.
  - `handle(InvoiceWasSent $event): void`.
  - `Mail::to(...)->queue(new InvoiceSent(...))` → `Mail::to(...)->send(new InvoiceSent(...))`.
  - `shouldQueue()` entfernt.
  - `failed()` bleibt erhalten — `composer stan` meldet sie nicht als
    unbenutzt (Laravel ruft `failed()` reflektiv über den Event-Dispatcher
    auf, kein PHPStan-Fehler), daher wie in der Task-Beschreibung als
    Option belassen.
- `backend/app/Mail/InvoiceCreated.php` gelöscht, neu:
  `backend/app/Mail/InvoiceSent.php` — nur Klassenname und
  `content()`-View-Referenz (`emails.invoice-created` → `emails.invoice-sent`)
  geändert. `attachments()` bleibt `return [];` (PDF-Anhang folgt in T02).
- `backend/resources/views/emails/invoice-created.blade.php` gelöscht,
  1:1 kopiert nach `backend/resources/views/emails/invoice-sent.blade.php`
  (`diff` bestätigt Byte-Identität vor dem Löschen des Originals).
- `backend/app/Providers/AppServiceProvider.php`: Imports (Zeile 8, 11)
  und `Event::listen(InvoiceWasSent::class, SendInvoiceEmail::class)`
  (vormals Zeile 79-82) umgestellt.
- `backend/tests/Feature/InvoiceCreatedMailBankDetailsTest.php` gelöscht,
  neu: `backend/tests/Feature/InvoiceSentMailBankDetailsTest.php` —
  `use App\Mail\InvoiceCreated;` → `use App\Mail\InvoiceSent;`,
  `new InvoiceCreated($this->invoice)` → `new InvoiceSent($this->invoice)`
  an allen vier Stellen. Assertions unverändert.
- Neuer, gezielter Test `backend/tests/Feature/SendInvoiceEmailListenerTest.php`
  (`uses()->group('feature', 'invoice')`): bestätigt per `Mail::fake()`
  + `Mail::assertSent(InvoiceSent::class)` + `Mail::assertNothingQueued()`
  den synchronen Versand, sowie per
  `expect(new SendInvoiceEmail)->not->toBeInstanceOf(ShouldQueue::class)`,
  dass der Listener kein Queue-Kontrakt mehr implementiert.

## Über den Task-Dateikatalog hinaus angepasst (notwendig für "kein Rest-Grep-Treffer" und `composer qa` grün)

Der Grep-Akzeptanzkriterium (`grep -rn "InvoiceWasCreated\|SendInvoiceCreatedEmail\|InvoiceCreated" backend/app backend/tests backend/resources`)
deckte drei weitere, in `tasks.md`/`design.md` nicht aufgeführte
Bestandsdateien auf, die die umbenannten Klassen ebenfalls referenzierten
und ohne Anpassung entweder `composer stan` oder `composer test` gebrochen
hätten:

- `backend/app/Console/Commands/SendTestEmail.php` — `use App\Mail\InvoiceCreated;`
  und `new InvoiceCreated($invoice)` auf `InvoiceSent` umgestellt (sonst
  neuer, nicht baselinierter PHPStan-Fehler "Instantiated class ... not found").
- `backend/app/Console/Commands/TestEventIntegration.php` — Imports und
  alle `InvoiceWasCreated`/`SendInvoiceCreatedEmail`-Referenzen (Anzeige-Labels,
  `class_exists()`-Prüfung, `getListeners()`-Aufruf) auf `InvoiceWasSent`/
  `SendInvoiceEmail` umgestellt.
- `backend/tests/Feature/EmailNotificationTest.php` — Test
  `'does not queue an invoice email on creation'` nutzte
  `Mail::assertNotSent(InvoiceCreated::class)`; auf `InvoiceSent::class`
  umgestellt (reiner Compile-/Referenz-Fix, keine Assertion-Logik geändert).
- `backend/tests/Unit/InvoiceBankDetailsBladeSourceTest.php` — zwei Tests
  lasen den Blade-Quelltext per `file_get_contents(...'/emails/invoice-created.blade.php')`;
  auf `invoice-sent.blade.php` umgestellt (ohne die Umstellung schlug
  `expect($source)->...` fehl, weil `file_get_contents()` nach dem Löschen
  der Quelldatei `false` statt eines Strings liefert).
- `backend/phpstan-baseline.neon`: Der Baseline-Eintrag für die
  Larastan-Regel `noEnvCallsOutsideOfConfig` (zwei `env()`-Aufrufe in
  `envelope()`) referenzierte den alten Pfad `app/Mail/InvoiceCreated.php`;
  auf `app/Mail/InvoiceSent.php` umbenannt (sonst bricht `composer stan`
  mit "Invalid entry in ignoreErrors: Path ... is neither a directory,
  nor a file path").

**Bewusst nicht angefasst:** `backend/app/Console/Commands/TestEmailTemplates.php`
enthält `use App\Mail\InvoiceCreatedMail;` und `new InvoiceCreatedMail($invoice)`
(Zeile 8, 57) — das ist eine andere, bereits vor dieser Task nicht
existierende Klasse (`App\Mail\InvoiceCreatedMail`, nicht `InvoiceCreated`)
und bereits vollständig in `phpstan-baseline.neon` als
`class.notFound`/`argument.type` baselined (vorbestehender toter Code,
unabhängig von diesem Change). Der einzige verbleibende Grep-Treffer nach
Abschluss von T01 ist dieser Substring-Zufallstreffer auf einen
unrelated, bereits kaputten Klassennamen — keine echte Referenz auf die
umbenannten Klassen dieser Task.

## Abweichungen von der Task-Beschreibung

Keine inhaltlichen Abweichungen von `tasks.md`/`design.md` Decision D4/D5.
Die oben gelisteten Zusatzänderungen sind reine Folgekorrekturen der
Umbenennung, um die in `tasks.md` selbst geforderten Akzeptanzkriterien
(Grep-Sauberkeit, `composer qa` grün) zu erfüllen.

## Verifikation

```
docker compose exec php composer lint          # 307 Dateien, PASS
docker compose exec php composer stan          # No errors
docker compose exec php composer compat-check  # keine Ausgabe, exit 0
docker compose exec php composer test          # 815 passed (2556 assertions)
docker compose exec php composer qa            # aggregiert alle vier, exit 0
```

`grep -rn "InvoiceWasCreated\|SendInvoiceCreatedEmail\|InvoiceCreated" backend/app backend/tests backend/resources`
liefert nach Abschluss genau einen Treffer in
`backend/app/Console/Commands/TestEmailTemplates.php` (unrelated
`InvoiceCreatedMail`, siehe oben) — keinen Treffer mehr auf die
tatsächlich umbenannten Klassen `InvoiceWasCreated`, `SendInvoiceCreatedEmail`,
`InvoiceCreated`.

## Offene Punkte für T02/T03

- `InvoiceSent::attachments()` liefert weiterhin `return [];` — PDF-Anhang
  folgt in T02 (Abhängigkeit bereits in `tasks.md` T02 vermerkt).
- `App\Events\InvoiceWasSent` wird aktuell an keiner Stelle mehr
  `dispatch()`-t (wie zuvor `InvoiceWasCreated`) — der Aufruf kommt aus
  `InvoiceController::sendEmail()` in T03.

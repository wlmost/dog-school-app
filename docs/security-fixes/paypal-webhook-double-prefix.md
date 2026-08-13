# Fix: PayPal-Webhook-Route doppelt präfixiert

## Status

Behoben.

## Betroffene Datei

- `backend/routes/api.php`

## Problem

`backend/bootstrap/app.php` registriert `routes/api.php` über
`Application::configure()->withRouting(api: __DIR__.'/../routes/api.php', ...)`.
Laravel versieht alle darin definierten Routen automatisch mit dem Präfix
`/api`. Alle anderen Routen der Datei berücksichtigen das korrekt, indem sie
zusätzlich in `Route::prefix('v1')->group(...)`-Blöcke gekapselt sind
(effektiver Pfad z. B. `/api/v1/pricing-items`).

Die PayPal-Webhook-Route war davon abweichend mit einem hartkodierten,
vollständigen Pfad registriert:

```php
Route::post('/api/v1/payments/paypal/webhook', [PaymentController::class, 'handleWebhook']);
```

Dadurch ergab sich der effektive, nach außen sichtbare Pfad
`/api/api/v1/payments/paypal/webhook` (doppeltes `/api`-Präfix). PayPal wird
beim Einrichten des Webhooks jedoch mit `https://<domain>/api/v1/payments/paypal/webhook`
konfiguriert — dieser Pfad existierte nie und lieferte 404. Damit kamen
PayPal-Zahlungsbestätigungen (`PAYMENT.CAPTURE.COMPLETED`,
`PAYMENT.CAPTURE.DENIED`/`DECLINED`, `PAYMENT.CAPTURE.REFUNDED`) vermutlich
seit Einführung der Route nie in der Anwendung an. Zahlungen, die über PayPal
abgewickelt wurden, blieben serverseitig dauerhaft im Status `pending`, auch
wenn PayPal die Zahlung erfolgreich abgeschlossen hatte.

## Fix

Die Route folgt jetzt dem Stil der übrigen Datei und nutzt die implizite
`/api`-Präfixierung aus `bootstrap/app.php` sowie einen eigenen
`Route::prefix('v1')`-Block:

```php
// PayPal webhook - separate without rate limiting (PayPal needs reliable access)
Route::prefix('v1')->group(function () {
    Route::post('/payments/paypal/webhook', [PaymentController::class, 'handleWebhook']);
});
```

Effektiver Pfad jetzt: `/api/v1/payments/paypal/webhook` — exakt der Pfad,
den PayPal beim Einrichten des Webhooks erwartet.

Bewusst **beibehalten**:

- Kein `auth:sanctum` auf dieser Route — PayPal kann sich nicht per
  Sanctum-Session authentifizieren. Die Authentizität wird stattdessen im
  Controller über `PayPalWebhookValidator::validate()` per Signaturprüfung
  (`PAYPAL-TRANSMISSION-SIG` etc.) sichergestellt.
- Keine eigene Rate-Limit-Middleware auf dieser Route, damit PayPal-Retries
  zuverlässig ankommen (Kommentar im Code).

## Verifikation

```bash
php artisan route:list --path=paypal
# POST api/v1/payments/paypal/webhook  Api\PaymentController@handleWebhook
```

Regressionstest ergänzt in
`backend/tests/Feature/PaymentApiTest.php`
(`'paypal webhook is reachable at the external path PayPal is configured with, without authentication'`),
der explizit den finalen, nach außen sichtbaren Pfad
`/api/v1/payments/paypal/webhook` unauthentifiziert aufruft und eine
`200 OK`-Antwort mit `status: success` erwartet.

`composer qa` (Pint, PHPStan/Larastan, PHPCompatibility gegen PHP 8.2, Pest)
lief danach vollständig grün (834 Tests, 2605 Assertions).

## Empfehlung für den Betrieb

Nach dem Deploy dieses Fixes sollte die Webhook-URL im PayPal Developer
Dashboard (Sandbox und Live) überprüft bzw. neu verifiziert werden, da
PayPal bei dauerhaft fehlschlagenden Zustellungen Webhooks deaktivieren kann.

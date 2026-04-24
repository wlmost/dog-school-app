<?php

return [

    /*
    |--------------------------------------------------------------------------
    | PayPal Mode (sandbox | live)
    |--------------------------------------------------------------------------
    |
    | Set to 'sandbox' for testing and 'live' for production
    |
    */

    'mode' => env('PAYPAL_MODE', 'sandbox'),

    /*
    |--------------------------------------------------------------------------
    | PayPal Client ID
    |--------------------------------------------------------------------------
    |
    | Your PayPal Client ID from the PayPal Developer Dashboard
    |
    */

    'client_id' => env('PAYPAL_CLIENT_ID', ''),

    /*
    |--------------------------------------------------------------------------
    | PayPal Client Secret
    |--------------------------------------------------------------------------
    |
    | Your PayPal Client Secret from the PayPal Developer Dashboard
    |
    */

    'client_secret' => env('PAYPAL_CLIENT_SECRET', ''),

    /*
    |--------------------------------------------------------------------------
    | PayPal Webhook ID
    |--------------------------------------------------------------------------
    |
    | Your PayPal Webhook ID for verifying webhook signatures
    |
    */

    'webhook_id' => env('PAYPAL_WEBHOOK_ID', ''),

    /*
    |--------------------------------------------------------------------------
    | PayPal API Base URL
    |--------------------------------------------------------------------------
    |
    | Automatically determined based on mode
    |
    */

    'base_url' => env('PAYPAL_MODE', 'sandbox') === 'live'
        ? 'https://api-m.paypal.com'
        : 'https://api-m.sandbox.paypal.com',

    /*
    |--------------------------------------------------------------------------
    | Currency
    |--------------------------------------------------------------------------
    |
    | Default currency for transactions
    |
    */

    'currency' => env('PAYPAL_CURRENCY', 'EUR'),

    /*
    |--------------------------------------------------------------------------
    | Return URL
    |--------------------------------------------------------------------------
    |
    | URL where customer is redirected after successful payment
    |
    */

    'return_url' => env('APP_URL') . '/payment/success',

    /*
    |--------------------------------------------------------------------------
    | Cancel URL
    |--------------------------------------------------------------------------
    |
    | URL where customer is redirected after payment cancellation
    |
    */

    'cancel_url' => env('APP_URL') . '/payment/cancel',

];

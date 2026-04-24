<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Rate Limiters
    |--------------------------------------------------------------------------
    |
    | Define rate limiters for different routes to prevent abuse
    |
    */

    'login' => [
        'max_attempts' => 5,
        'decay_minutes' => 15,
    ],

    'api' => [
        'max_attempts' => 60,
        'decay_minutes' => 1,
    ],

    'global' => [
        'max_attempts' => 1000,
        'decay_minutes' => 1,
    ],

];

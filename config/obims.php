<?php

return [
    /*
    | Optional key for the first-time administrator setup page. Leave empty for no key. On a
    | public server, set it before the first administrator is created so only someone who
    | knows the key can create that account.
    */
    'setup_key' => env('OBIMS_SETUP_KEY', ''),

    /*
    | Password reset by email: how long a code / link stays valid, and how many wrong codes are
    | accepted before the code is cancelled.
    */
    'password_reset' => [
        'expires_minutes' => (int) env('OBIMS_RESET_EXPIRES_MINUTES', 15),
        'max_attempts' => 5,
        'resend_seconds' => 60,
    ],
];

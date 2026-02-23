<?php

return [
    /*
    |--------------------------------------------------------------------------
    | HMAC Secret Key
    |--------------------------------------------------------------------------
    | Used to validate request signatures on payment endpoints.
    | Generate a strong random key and set it in the .env file.
    | NEVER commit the actual secret to version control.
    */
    'hmac_secret' => env('HMAC_SECRET', ''),
];

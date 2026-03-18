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
    "hmac_secret" => env("HMAC_SECRET", ""),

    /*
    |--------------------------------------------------------------------------
    | Supported Currencies (ISO 4217)
    |--------------------------------------------------------------------------
    | The list of currency codes accepted by the transfer endpoint.
    | All codes must be exactly 3 characters per the ISO 4217 standard.
    */
    "supported_currencies" => [
        "AED", // UAE Dirham
        "AUD", // Australian Dollar
        "BRL", // Brazilian Real
        "CAD", // Canadian Dollar
        "CHF", // Swiss Franc
        "CNY", // Chinese Yuan
        "CZK", // Czech Koruna
        "DKK", // Danish Krone
        "EUR", // Euro
        "GBP", // British Pound Sterling
        "HKD", // Hong Kong Dollar
        "HUF", // Hungarian Forint
        "IDR", // Indonesian Rupiah
        "ILS", // Israeli New Shekel
        "INR", // Indian Rupee
        "JPY", // Japanese Yen
        "KRW", // South Korean Won
        "MXN", // Mexican Peso
        "MYR", // Malaysian Ringgit
        "NOK", // Norwegian Krone
        "NZD", // New Zealand Dollar
        "PHP", // Philippine Peso
        "PLN", // Polish Zloty
        "RON", // Romanian Leu
        "SAR", // Saudi Riyal
        "SEK", // Swedish Krona
        "SGD", // Singapore Dollar
        "THB", // Thai Baht
        "TRY", // Turkish Lira
        "TWD", // New Taiwan Dollar
        "UAH", // Ukrainian Hryvnia
        "USD", // United States Dollar
        "ZAR", // South African Rand
    ],
];

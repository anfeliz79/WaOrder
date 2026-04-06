<?php

return [
    /*
    |--------------------------------------------------------------------------
    | PayPal Environment
    |--------------------------------------------------------------------------
    |
    | 'sandbox' uses sandbox URLs, 'live' uses production URLs.
    |
    */
    'mode' => env('PAYPAL_MODE', 'sandbox'),

    /*
    |--------------------------------------------------------------------------
    | PayPal API Credentials
    |--------------------------------------------------------------------------
    |
    | Client ID and Secret from the PayPal Developer Dashboard.
    |
    */
    'client_id' => env('PAYPAL_CLIENT_ID'),
    'client_secret' => env('PAYPAL_CLIENT_SECRET'),

    /*
    |--------------------------------------------------------------------------
    | PayPal Webhook ID
    |--------------------------------------------------------------------------
    |
    | The webhook ID from PayPal, used to verify incoming webhook signatures.
    |
    */
    'webhook_id' => env('PAYPAL_WEBHOOK_ID'),

    /*
    |--------------------------------------------------------------------------
    | PayPal Product ID
    |--------------------------------------------------------------------------
    |
    | Once a PayPal product is created, store the ID here to avoid re-creating.
    |
    */
    'product_id' => env('PAYPAL_PRODUCT_ID'),
];

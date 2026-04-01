<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Cardnet Environment
    |--------------------------------------------------------------------------
    |
    | 'testing' uses lab URLs, 'production' uses live URLs.
    |
    */
    'environment' => env('CARDNET_ENVIRONMENT', 'testing'),

    /*
    |--------------------------------------------------------------------------
    | Platform Credentials (for subscription billing)
    |--------------------------------------------------------------------------
    |
    | These are the platform's own Cardnet credentials, used for charging
    | tenant subscriptions. Tenant-specific credentials for customer order
    | payments are stored in tenant.settings.payment.cardnet.
    |
    */
    'platform' => [
        'public_key' => env('CARDNET_PUBLIC_KEY'),
        'private_key' => env('CARDNET_PRIVATE_KEY'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Cardnet API URLs
    |--------------------------------------------------------------------------
    */
    'urls' => [
        'testing' => [
            'payment_base' => 'https://lab.cardnet.com.do',
            'tokenization_base' => 'https://labservicios.cardnet.com.do/servicios/tokens/v1/api',
        ],
        'production' => [
            'payment_base' => 'https://ecommerce.cardnet.com.do',
            'tokenization_base' => 'https://servicios.cardnet.com.do/servicios/tokens/v1/api',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Subscription Settings
    |--------------------------------------------------------------------------
    */
    'subscription' => [
        'grace_period_days' => 3,
        'max_retry_attempts' => 3,
        'retry_interval_hours' => 24,
    ],
];

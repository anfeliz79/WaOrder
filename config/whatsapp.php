<?php

return [
    'verify_token' => env('WHATSAPP_VERIFY_TOKEN', ''),
    'app_secret' => env('WHATSAPP_APP_SECRET', ''),
    'api_version' => env('WHATSAPP_API_VERSION', 'v21.0'),
    'api_base_url' => 'https://graph.facebook.com',
];

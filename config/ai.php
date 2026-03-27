<?php

return [
    'default_provider' => env('AI_PROVIDER', 'groq'),

    'providers' => [
        'groq' => [
            'api_key' => env('GROQ_API_KEY', ''),
            'base_url' => 'https://api.groq.com/openai/v1',
            'model' => env('GROQ_MODEL', 'llama-3.1-8b-instant'),
            'max_tokens' => 200,
            'temperature' => 0.3,
        ],
        'openai' => [
            'api_key' => env('OPENAI_API_KEY', ''),
            'base_url' => 'https://api.openai.com/v1',
            'model' => env('OPENAI_MODEL', 'gpt-4o-mini'),
            'max_tokens' => 200,
            'temperature' => 0.3,
        ],
    ],

    'cache' => [
        'enabled' => true,
        'ttl' => 86400, // 24 hours
        'prefix' => 'ai_cache',
    ],

    'max_retries_before_ai' => 2,
    'max_ai_attempts' => 2,
];

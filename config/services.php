<?php

return [
    'opencode' => [
        'key' => env('OPENCODE_API_KEY'),
        'base_url' => env('OPENCODE_BASE_URL', 'https://opencode.ai/inference/openai/v1'),
        'model' => env('OPENCODE_MODEL', 'muse-ember-1.0'),
        'timeout' => (int) env('OPENCODE_TIMEOUT', 45),
    ],

    'deployment' => [
        'secret' => env('DEPLOYMENT_SECRET'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'groq' => [
        'key' => env('GROQ_API_KEY'),
        'model' => env('AI_MODEL', 'llama-3.1-70b-versatile'),
        'base_uri' => env('AI_BASE_URI', 'https://api.groq.com/openai/v1'),
    ],

];

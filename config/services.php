<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
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

    'github' => [
        'base_url' => env('GITHUB_API_BASE_URL', 'https://api.github.com'),
        'api_version' => env('GITHUB_API_VERSION', '2022-11-28'),
        'token' => env('GITHUB_TOKEN'),
    ],

    'ai' => [
        'provider' => env('AI_PROVIDER', 'fake'),
    ],

    'openai' => [
        'base_url' => env('OPENAI_BASE_URL', 'https://api.openai.com'),
        'api_key' => env('OPENAI_API_KEY'),
        'model' => env('OPENAI_MODEL', 'gpt-5.4-mini'),
        'timeout' => env('OPENAI_TIMEOUT', 30),
    ],

    'codex' => [
        'auth_path' => env('CODEX_AUTH_PATH'),
        'home' => env('CODEX_HOME'),
        'fallback_home' => env('HOME'),
        'base_url' => env('CODEX_BASE_URL', 'https://chatgpt.com/backend-api/codex'),
        'timeout' => env('CODEX_TIMEOUT', 30),
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

];

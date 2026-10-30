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

    'whatnot_seller' => [
        'base_url' => env('WHATNOT_SELLER_API_BASE_URL', 'https://api.whatnot.com/seller-api/rest/v2'),
        // Explicit opt-in after checking the actual Whatnot pagination response.
        'pagination_verified' => env('WHATNOT_SELLER_PAGINATION_VERIFIED', false),
        // Keep false on dev/staging. Never share the production scraper profile.
        'live_requests_enabled' => env('WHATNOT_SELLER_LIVE_REQUESTS_ENABLED', false),
        'contract_verified' => env('WHATNOT_SELLER_CONTRACT_VERIFIED', false),
        'sync_scheduler_enabled' => env('WHATNOT_SELLER_SYNC_SCHEDULER_ENABLED', false),
        'webhooks_enabled' => env('WHATNOT_SELLER_WEBHOOKS_ENABLED', false),
        'webhook_secret' => env('WHATNOT_SELLER_WEBHOOK_SECRET', ''),
        'webhook_seller_ids' => env('WHATNOT_SELLER_WEBHOOK_SELLER_IDS', ''),
    ],

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

    'ollama' => [
        'url'             => env('OLLAMA_BASE_URL', 'http://ollama:11434'),
        'model'           => env('OLLAMA_MODEL', 'llama3.2:3b'),
        'vision_model'    => env('OLLAMA_VISION_MODEL', 'moondream'),
        'embedding_model' => env('OLLAMA_EMBEDDING_MODEL', 'nomic-embed-text'),
    ],

];

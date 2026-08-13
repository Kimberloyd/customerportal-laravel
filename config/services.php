<?php

return [

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

    // See App\Support\OrderNotifications. email/facebook stay disabled
    // until real SMTP/Meta Graph API credentials exist to verify
    // delivery against -- both flags default false intentionally.
    'po_notifications' => [
        'public_conversation_link_ttl_hours' => env('PUBLIC_CONVERSATION_LINK_TTL_HOURS', 720),
        'email_enabled' => env('PO_NOTIFICATIONS_EMAIL_ENABLED', false),
        'facebook_enabled' => env('PO_NOTIFICATIONS_FACEBOOK_ENABLED', false),
    ],

];

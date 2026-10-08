<?php

return [
    // Only addresses on this domain can sign in or link WhatsApp.
    'email_domain' => env('IDEAS_EMAIL_DOMAIN', 'zb.co.zw'),

    // Demo switch: accept any 6 digits instead of the emailed code. Never enable in production.
    'accept_any_code' => (bool) env('IDEAS_ACCEPT_ANY_CODE', false),

    // Create an account the first time a valid work email verifies. Replace with a directory lookup in production.
    'auto_provision' => (bool) env('IDEAS_AUTO_PROVISION', true),

    'code_ttl_minutes' => 10,
    'max_code_attempts' => 5,
    'lockout_minutes' => 30,

    'whatsapp' => [
        'token' => env('WHATSAPP_TOKEN'),
        'phone_number_id' => env('WHATSAPP_PHONE_NUMBER_ID'),
        'verify_token' => env('WHATSAPP_VERIFY_TOKEN', 'change-me'),
        'app_secret' => env('WHATSAPP_APP_SECRET'),
        'api_version' => env('WHATSAPP_API_VERSION', 'v21.0'),
        'session_minutes' => 30,
        'rate_per_minute' => 20,
        'templates' => [
            'approved' => env('WHATSAPP_TPL_APPROVED', 'idea_approved'),
        ],
    ],
];

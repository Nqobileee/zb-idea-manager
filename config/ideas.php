<?php

return [
    // Where uploaded images, documents and avatars are stored: 'public' (server disk) or 'supabase' (Supabase Storage).
    'upload_disk' => env('IDEAS_UPLOAD_DISK', 'public'),

    // Emails that get Executive admin straight away when they register in the chatbot (comma separated in .env).
    // Anyone else who asks is a General member until an administrator approves on the Members page.
    'executive_emails' => array_values(array_filter(array_map(fn ($e) => strtolower(trim($e)), explode(',', (string) env('IDEAS_EXECUTIVE_EMAILS', ''))))),

    // Shared secret the Zernio chatbot sends in the X-Zernio-Secret header. Empty disables the /api/zernio endpoints.
    'zernio_secret' => env('ZERNIO_SECRET', ''),

    'whatsapp' => [
        'display_number' => env('WHATSAPP_DISPLAY_NUMBER', '263777366886'), // the number people message, for the Open WhatsApp button
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

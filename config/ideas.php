<?php

return [
    // Where uploaded images, documents and avatars are stored: 'public' (server disk) or 'supabase' (Supabase Storage).
    'upload_disk' => env('IDEAS_UPLOAD_DISK', 'public'),

    // Secret code a person must give (with their email and password) to register as Executive admin in the chatbot.
    // Empty disables Executive admin registration. Change it whenever you like; existing executives are unaffected.
    'executive_code' => env('IDEAS_EXECUTIVE_CODE', ''),

    // Demo sign-ins, created by `php artisan ideas:seed-demo`. Keep the values in the private environment only.
    'demo' => [
        'admin' => ['email' => env('IDEAS_DEMO_ADMIN_EMAIL'), 'password' => env('IDEAS_DEMO_ADMIN_PASSWORD')],
        'member' => ['email' => env('IDEAS_DEMO_MEMBER_EMAIL'), 'password' => env('IDEAS_DEMO_MEMBER_PASSWORD')],
    ],

    // Generic password given to old accounts that had none (see `php artisan ideas:set-default-passwords`).
    // Signing in with it always forces a password change.
    'default_password' => env('IDEAS_DEFAULT_PASSWORD', 'Pass123'),

    // Code a person types in WhatsApp to sign out of the account linked to their number and start sign-in again.
    // Empty disables switching.
    'switch_code' => env('IDEAS_SWITCH_CODE', ''),

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

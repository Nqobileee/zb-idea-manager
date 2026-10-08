<?php

return [
    // Restrict sign-in to one email domain. Leave empty to allow any email address (temporary, for the pilot).
    'email_domain' => env('IDEAS_EMAIL_DOMAIN', ''),

    // Demo switch: accept any 6 digits instead of the emailed code. Never enable in production.
    'accept_any_code' => (bool) env('IDEAS_ACCEPT_ANY_CODE', false),

    // Create an account the first time a valid work email verifies. Replace with a directory lookup in production.
    'auto_provision' => (bool) env('IDEAS_AUTO_PROVISION', true),

    // TEMPORARY: let people pick Employee or Executive when they sign in. Turn off once real
    // executives are assigned (then admin rights are only changed on the All users page).
    'allow_role_choice' => (bool) env('IDEAS_ALLOW_ROLE_CHOICE', true),

    // Where uploaded images, documents and avatars are stored: 'public' (server disk) or 'supabase' (Supabase Storage).
    'upload_disk' => env('IDEAS_UPLOAD_DISK', 'public'),

    // TEMPORARY: when false, a valid email address is enough to sign in or link WhatsApp (no emailed code).
    // This means anyone can sign in as anyone. Set true as soon as real mail is configured.
    'require_code' => (bool) env('IDEAS_REQUIRE_CODE', false),

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

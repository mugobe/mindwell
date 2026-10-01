<?php

/*
 * Pandora SMS (sms.thepandoranetworks.com) — the Ugandan SMS gateway the user already
 * uses for Insight360. Added 2026-09-30 for appointment reminders.
 *
 * Put the real values in .env, never here:
 *
 *   PANDORA_SMS_USERNAME=your-pandora-login
 *   PANDORA_SMS_PASSWORD=your-pandora-password
 *   PANDORA_SMS_SENDER=Mindwell            (max 11 chars; must be approved on your account)
 *
 * Pandora's public docs contradict themselves in a few places, so the uncertain parts
 * are settings — copy whatever already works in Insight360, then confirm with:
 *   php artisan mindwell:sms-test 07XXXXXXXX
 *
 * If USERNAME/PASSWORD are empty, nothing is sent: each SMS is written to
 * storage/logs/laravel.log instead (like Laravel's "log" mail driver), so the rest of
 * the app can be tested without an SMS account.
 */
return [

    'username' => env('PANDORA_SMS_USERNAME'),
    'password' => env('PANDORA_SMS_PASSWORD'),
    'sender' => env('PANDORA_SMS_SENDER', 'Mindwell'),

    // The documented bulk endpoint. Pandora also documents an "info" endpoint
    // (…/API/send_info_sms/) — switch to it here if that's what Insight360 uses.
    'endpoint' => env('PANDORA_SMS_ENDPOINT', 'https://www.sms.thepandoranetworks.com/API/send_sms/'),

    // 'post' keeps the password out of URLs/logs; Pandora documents both.
    'method' => env('PANDORA_SMS_METHOD', 'post'),

    // Only used by the send_sms endpoint.
    'message_type' => env('PANDORA_SMS_MESSAGE_TYPE', 'non_customised'),
    'message_category' => env('PANDORA_SMS_MESSAGE_CATEGORY', 'bulk'),

    // How the recipient number is written: 'local' → 0772123456,
    // 'international' → 256772123456, 'plus' → +256772123456.
    'number_format' => env('PANDORA_SMS_NUMBER_FORMAT', 'local'),

];

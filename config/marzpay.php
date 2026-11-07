<?php

/*
 * MarzPay (Marz Innovations Ltd, Kampala) — Mobile Money collections for Uganda, added
 * 2026-11-02 alongside Flutterwave. Which one takes payments is PAYMENT_GATEWAY (see
 * config/payments.php); both stay in the code so we can switch back without a deploy.
 *
 * Every value comes from .env. Real keys live ONLY in the server's .env, never in git.
 * Docs: https://wallet.wearemarz.com/documentation
 */

return [

    'base_url' => env('MARZPAY_API_BASE', 'https://wallet.wearemarz.com/api/v1'),

    // Dashboard → API Keys. The secret is shown once when the key is created.
    'api_key' => env('MARZPAY_API_KEY'),
    'api_secret' => env('MARZPAY_API_SECRET'),

    // Dashboard → webhook signing. When set, every callback must carry a valid
    // X-MarzPay-Signature; when empty, callbacks are accepted but never trusted on
    // their own (we always re-check the status with MarzPay before marking paid).
    'webhook_secret' => env('MARZPAY_WEBHOOK_SECRET'),

    // Where MarzPay posts the final status. Must be public HTTPS, so it is left out
    // automatically on a local http:// address (the waiting page's status checks still
    // work there). Empty = this app's own /webhooks/marzpay route.
    'callback_url' => env('MARZPAY_CALLBACK_URL'),

    // The business account is in sandbox mode (MarzPay's own setting, same base URL).
    // Marks payments as tests on receipts and allows the staging "Test mode" buttons.
    // Set to false once MarzPay switches the account to live.
    'sandbox' => (bool) env('MARZPAY_SANDBOX', true),

    // Write the full request/response/callback bodies to storage/logs/marzpay.log while
    // we learn the real shapes in sandbox. Turn off for live (it holds phone numbers).
    'log_payloads' => (bool) env('MARZPAY_LOG_PAYLOADS', false),

    'country' => 'UG',

    // MarzPay's collection limits for Uganda (UGX).
    'min_amount' => 500,
    'max_amount' => 10000000,

    // Shown to MarzPay and possibly on the client's Mobile Money message, so it stays
    // neutral: no names, no "therapy", nothing about health.
    'description' => env('MARZPAY_DESCRIPTION', 'Mindwell Consultancy payment'),

    // Seconds to wait for MarzPay before giving up on one request.
    'timeout' => 30,

];

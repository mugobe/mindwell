<?php

return [

    /*
     * Real Flutterwave integration, REBUILT 2026-09-27 for v4 — the first version of
     * this file assumed a classic v3 Secret/Public key pair, but testing directly
     * against the real sandbox that day confirmed this account only has the newer
     * OAuth2-style credentials below (no v3 keys exist anywhere on it). Generate these
     * from the Flutterwave dashboard (Settings > Developers > API Keys) and put the
     * actual values in .env, never here:
     *
     *   FLUTTERWAVE_CLIENT_ID=xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx
     *   FLUTTERWAVE_CLIENT_SECRET=xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
     */
    'client_id' => env('FLUTTERWAVE_CLIENT_ID'),
    'client_secret' => env('FLUTTERWAVE_CLIENT_SECRET'),

    /*
     * Only needed for CARD payments, which aren't built yet — v4 requires card number/
     * expiry/CVV to be encrypted with this key before they ever leave the browser (see
     * the "cards" follow-up round in the project doc's Open Items). Nothing in this
     * codebase reads this today; it's here so that round doesn't need a second .env pass.
     *
     *   FLUTTERWAVE_ENCRYPTION_KEY=xxxxxxxxxxxxxxxxxxxxxxxx
     */
    'encryption_key' => env('FLUTTERWAVE_ENCRYPTION_KEY'),

    // OAuth2 client-credentials token endpoint — confirmed working 2026-09-27 against
    // the real sandbox with this account's actual Client ID/Secret. Access tokens
    // expire in 600 seconds; App\Services\FlutterwaveClient caches and refreshes them
    // rather than fetching a new one on every call.
    'token_url' => env('FLUTTERWAVE_TOKEN_URL', 'https://idp.flutterwave.com/realms/flutterwave/protocol/openid-connect/token'),

    /*
     * Sandbox base URL, confirmed 2026-09-27. Flutterwave's own v4 articles don't
     * consistently state the LIVE base URL — confirm the real one (from the dashboard
     * or by asking Flutterwave support) before ever switching to live keys. Nothing
     * else in the codebase needs to change when that happens — just this one value.
     */
    'base_url' => env('FLUTTERWAVE_BASE_URL', 'https://developersandbox-api.flutterwave.com'),

    /*
     * Set in the Flutterwave dashboard under Webhooks as the "secret hash" — not
     * usable until this app has a public URL (see FlutterwavePaymentController).
     * UNVERIFIED against v4's actual webhook signing scheme: this reuses v3's
     * `verif-hash` header convention, which wasn't confirmed for v4 during this round
     * since it can't be tested without a public URL yet. Re-check before relying on
     * this in production.
     */
    'webhook_secret_hash' => env('FLUTTERWAVE_WEBHOOK_SECRET_HASH'),

    /*
     * Decided 2026-09-27: UGX, so Mobile Money (MTN/Airtel) actually works — it's the
     * only payment method this integration supports so far (see FlutterwaveClient),
     * and Mobile Money Uganda charges require UGX. The actual PRICES
     * (config('mindwell.one_off_session_price'), each SubscriptionPlan.price) are
     * separate placeholder numbers still awaiting Dr. Ntabade's sign-off.
     */
    'currency' => env('FLUTTERWAVE_CURRENCY', 'UGX'),

    /*
     * SANDBOX-ONLY testing hook — sent as the X-Scenario-Key header on every API call
     * when set (see FlutterwaveClient::headers()). Flutterwave's v4 API reference lists
     * this optional header for simulating outcomes in sandbox, but doesn't publish the
     * accepted values. Leave unset normally; never set it with live credentials.
     */
    'scenario_key' => env('FLUTTERWAVE_SCENARIO_KEY'),

];

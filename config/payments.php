<?php

/*
 * Which payment service starts NEW Mobile Money payments (2026-11-02):
 *
 *   PAYMENT_GATEWAY=flutterwave   (default, what the site used until now)
 *   PAYMENT_GATEWAY=marzpay
 *
 * Each payment remembers the service it was started with (payment_transactions.gateway),
 * so switching never strands a payment that is already waiting: it is still checked
 * with the service that started it.
 */

return [

    'gateway' => env('PAYMENT_GATEWAY', 'flutterwave'),

    // The "Simulate approval / failure" buttons on the waiting page and in the app
    // (2026-11-03). They let a payment be marked paid WITHOUT any money moving, so they
    // are OFF unless this is true, and even then only on local/staging with a sandbox.
    'test_mode' => (bool) env('PAYMENT_TEST_MODE', false),

    // How long the client waits on "Approve on your phone" before we stop (2026-11-04).
    // Mobile Money prompts expire within 1 to 2 minutes, but MarzPay keeps the payment
    // "pending" and re-sends the prompt for much longer, so we set our own deadline.
    // A late approval is still recorded: see mindwell:payments-reconcile.
    'wait_minutes' => (int) env('PAYMENT_WAIT_MINUTES', 3),

    // How long the background check keeps asking about unfinished payments.
    'reconcile_hours' => 24,

];

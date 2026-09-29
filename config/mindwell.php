<?php

return [

    /*
     * PLACEHOLDER — no real pricing decision has been made for a one-off (non-subscription)
     * session yet. This number is not final; it exists so the checkout screen has something
     * to display and actually charge via Flutterwave. The CURRENCY it's charged in is
     * config('flutterwave.currency') (UGX as of 2026-09-27, so Mobile Money shows up as
     * a payment option) — this figure is denominated in that currency. Confirm the real
     * amount with Dr. Ntabade before this goes anywhere near production; the same goes
     * for every SubscriptionPlan.price in the admin Plans list.
     */
    'one_off_session_price' => 15000.00,

    // How long a session is, in minutes. Confirmed by the client: 70.
    'session_duration_minutes' => 70,

    // Target wait time shown to the client in the lobby while routing happens.
    // Confirmed by the client: "up to ~10 minutes".
    'lobby_target_minutes' => 10,

    // Video room join window (added 2026-09-28 with LiveKit). Client and therapist can
    // enter a session's video room from this many minutes BEFORE its scheduled start...
    'video_join_early_minutes' => 10,

    // ...until this many minutes AFTER its scheduled end (start + duration). Defaults
    // chosen by the assistant, not yet confirmed with Dr. Ntabade — easy to change here.
    'video_join_late_minutes' => 30,

];

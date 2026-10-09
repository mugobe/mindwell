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

    /*
     * In-person session price (2026-10-07, Collins: "separate configurable price").
     * PLACEHOLDER like the one above — Dr. Ntabade sets the real figure. Set it in .env
     * with MINDWELL_IN_PERSON_PRICE. Plan credits and employer seats cover an in-person
     * session the same as a video one (one credit each).
     */
    'in_person_session_price' => (float) env('MINDWELL_IN_PERSON_PRICE', 20000),

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

    // "You have a new message" emails (added 2026-09-30) wait this long first, so
    // someone who's already in the app doesn't get emailed about a message they're
    // about to read anyway.
    'message_email_delay_minutes' => 10,

    // Subscription renewals (added 2026-10-01, decided with Collins). If a plan isn't
    // renewed by its renewal date it stays usable for this many more days, then expires.
    'subscription_grace_days' => 3,

    // "Your plan renews soon" reminder goes out this many days before the renewal date
    // (a second one goes out on the day itself, and a last one when the plan expires).
    'subscription_reminder_days' => 3,

    // Shown at the top of every receipt (added 2026-10-02). Fill these in .env with the
    // practice's real details before going live — receipts may be sent to employers or
    // insurers. Blank ones are simply left off the receipt.
    'receipt' => [
        'business_name' => env('MINDWELL_BUSINESS_NAME', 'Mindwell'),
        'address' => env('MINDWELL_BUSINESS_ADDRESS'),
        'phone' => env('MINDWELL_BUSINESS_PHONE'),
        'email' => env('MINDWELL_BUSINESS_EMAIL'),
        'tin' => env('MINDWELL_BUSINESS_TIN'),
    ],

    // Progress check-ins (added 2026-10-03, decided with Collins): PHQ-9 + GAD-7 every
    // 2 weeks. A client can do one early, but not within min_gap days of the last one;
    // the email/SMS nudge goes out once they're this many days overdue.
    'check_in_interval_days' => 14,
    'check_in_min_gap_days' => 7,
    'check_in_reminder_delay_days' => 2,

    // Crisis / urgent-help contacts (Phase 0, 2026-10-05). Shown on the public
    // "I need urgent help" page and both crisis screens (intake + check-in).
    //  - Mental Health Uganda's toll-free line is only answered Mon–Fri 8:30am–5pm
    //    (checked 2026-10-03), so it is never shown as the only option.
    //  - 999 / 112 is Uganda's national emergency number (Uganda Police Force).
    //  - MINDWELL_CRISIS_LINE_* : the 24/7 number Dr. Ntabade chooses. Blank = not shown.
    'crisis' => [
        'line_name' => env('MINDWELL_CRISIS_LINE_NAME'),
        'line_number' => env('MINDWELL_CRISIS_LINE_NUMBER'),
        'line_hours' => env('MINDWELL_CRISIS_LINE_HOURS', '24 hours, every day'),
    ],

    // Terms of Use / Privacy Policy (Phase 0). While is_draft is true the pages show a
    // "draft — under review" banner. Bump `version` whenever the text changes; people
    // who accepted an older version are asked to accept again.
    'legal' => [
        'version' => env('MINDWELL_LEGAL_VERSION', '2026-10-draft'),
        'is_draft' => (bool) env('MINDWELL_LEGAL_IS_DRAFT', true),
    ],

    /*
     * Calendar + cancellations (2026-10-08, roadmap 75). Collins: "make this a
     * configuration". These only PRE-TICK the boxes admin sees when approving a
     * cancellation — admin can always change them case by case.
     *   'always'  — pre-ticked for every cancellation
     *   'on_time' — pre-ticked only if asked at least notice_hours before the session
     *   'never'   — never pre-ticked
     * return_credit: give the plan credit back (session paid with a plan credit).
     * refund_payment: record a refund of a Mobile Money payment (send the money first).
     * Employer-seat sessions need nothing — seats aren't counted per session.
     */
    'cancellation' => [
        'notice_hours' => (int) env('MINDWELL_CANCEL_NOTICE_HOURS', 24),
        'return_credit' => env('MINDWELL_CANCEL_RETURN_CREDIT', 'always'),
        'refund_payment' => env('MINDWELL_CANCEL_REFUND_PAYMENT', 'on_time'),
    ],

    // Calendar day: first and last hour shown in the day/week views.
    'calendar' => [
        'day_start_hour' => 7,
        'day_end_hour' => 21,
    ],

    // Minimum age for a client account. Teen counselling is parked on the roadmap (it
    // needs parental consent + safeguarding), so sign-up is adults only for now.
    'min_client_age' => 18,

];

@extends('layouts.app')
@section('title', 'Approve on your phone')
@section('content')
{{--
    Added 2026-09-28. v4 Mobile Money has no Flutterwave page to redirect to: the charge
    comes back "pending" with an instruction to approve it on the client's phone. This
    page is where they wait. While $autoCheck is true (first 10 minutes after the charge
    was created) it re-checks by sending the browser to the callback route every 8
    seconds; callback either finalizes the payment and moves them on, or sends them
    straight back here. See FlutterwavePaymentController::waiting()/callback().
--}}

<div class="card" style="max-width:480px;margin:24px auto;text-align:center;padding:36px 28px;">
    @include('partials.mw-icon-badge', ['tint' => 'var(--mw-gold, #F6E1A8)', 'icon' => '<rect x="7" y="2.5" width="10" height="19" rx="2"/><path d="M11 18.5h2"/>'])
    <h1 style="font-size:19px;font-weight:800;margin-bottom:6px;color:var(--ink-900);">Approve the payment on your phone</h1>
    <p style="font-size:13.5px;color:var(--ink-500);margin-bottom:22px;">
        {{ $instruction ?: 'Check your phone for a Mobile Money prompt and approve it with your PIN.' }}
    </p>

    <div class="card" style="background:var(--ink-050,#f7f7f8);padding:16px;margin-bottom:22px;">
        <div style="font-size:24px;font-weight:800;color:var(--ink-900);">{{ $transaction->currency }} {{ number_format($transaction->amount, 0) }}</div>
        <div style="font-size:11.5px;color:var(--ink-300);margin-top:4px;">
            {{ $transaction->purpose === 'subscription' ? ($transaction->client_subscription_id ? 'Plan renewal' : 'Subscription payment') : 'Session payment' }} · waiting for approval
        </div>
    </div>

    {{-- 2026-11-04: a countdown, a quiet status check (no page reloads), and a way out.
         After the deadline the client is told and sent back to checkout; a late approval is
         still recorded by mindwell:payments-reconcile. --}}
    <div id="mw-wait" style="display:flex;align-items:center;justify-content:center;gap:10px;margin-bottom:16px;font-size:13px;color:var(--ink-500);">
        <span class="mw-wait-dot" aria-hidden="true" style="width:9px;height:9px;border-radius:50%;background:#00A87C;box-shadow:0 0 0 0 rgba(0,168,124,.5);animation:mwWait 1.6s infinite;"></span>
        <span>Waiting for your approval · <b id="mw-left" style="color:var(--ink-900);font-variant-numeric:tabular-nums;">{{ gmdate('i:s', max(0, (int) now()->diffInSeconds($deadline, false))) }}</b></span>
    </div>
    <style>@keyframes mwWait { 70% { box-shadow:0 0 0 8px rgba(0,168,124,0); } 100% { box-shadow:0 0 0 0 rgba(0,168,124,0); } }</style>

    <a href="{{ $checkUrl }}" class="btn coral block" style="border-radius:999px;">I've approved it</a>
    <form method="POST" action="{{ $cancelUrl }}" style="margin-top:10px;">
        @csrf
        <button type="submit" class="btn ghost block" style="border-radius:999px;width:100%;">Cancel payment</button>
    </form>
    <p id="mw-note" style="font-size:12px;color:var(--ink-500);margin-top:14px;">
        No prompt, not enough balance, or the wrong number? Cancel and try again.
    </p>
    <script>
    (function () {
        var url = @json($statusUrl), left = document.getElementById('mw-left'), secs = {{ max(0, (int) now()->diffInSeconds($deadline, false)) }};
        function show() { var m = Math.floor(secs / 60), s = secs % 60; left.textContent = (m < 10 ? '0' : '') + m + ':' + (s < 10 ? '0' : '') + s; }
        setInterval(function () { if (secs > 0) { secs--; show(); } }, 1000);
        function check() {
            fetch(url, { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
                .then(function (r) { return r.ok ? r.json() : null; })
                .then(function (d) {
                    if (d && typeof d.seconds_left === 'number') { secs = d.seconds_left; show(); }
                    if (d && d.next) { window.location.href = d.next; return; }
                    setTimeout(check, 4000);
                })
                .catch(function () { setTimeout(check, 6000); });
        }
        setTimeout(check, 4000);
    })();
    </script>

    <p style="font-size:10.5px;color:var(--ink-300);margin-top:16px;">
        If your phone asks again after you cancel, decline it.
        @if ($transaction->isTest())
            This is a test payment (sandbox), so no real money moves.
        @endif
    </p>

    @if ($canSimulate)
        {{-- Only rendered when APP_ENV=local AND pointed at Flutterwave's sandbox — see
             FlutterwavePaymentController::simulationAllowed(). Flutterwave's v4 sandbox
             never completes Mobile Money charges on its own, so this stands in for the
             client approving on their phone. --}}
        <div style="margin-top:22px;padding:14px;border:1.5px dashed #c9a227;border-radius:12px;background:#fffbea;text-align:left;">
            <div style="font-size:10.5px;font-weight:800;letter-spacing:.06em;color:#8a6d00;margin-bottom:8px;">DEV ONLY · LOCAL SANDBOX</div>
            <div style="display:flex;gap:8px;">
                <form method="POST" action="{{ route('payments.simulate', $transaction) }}" style="flex:1;">
                    @csrf
                    <input type="hidden" name="outcome" value="approve">
                    <button type="submit" class="btn ghost sm" style="width:100%;">Simulate approval</button>
                </form>
                <form method="POST" action="{{ route('payments.simulate', $transaction) }}" style="flex:1;">
                    @csrf
                    <input type="hidden" name="outcome" value="fail">
                    <button type="submit" class="btn ghost sm" style="width:100%;">Simulate failure</button>
                </form>
            </div>
            @if ($transaction->gateway === 'marzpay')
                <div style="font-size:11px;color:#8a6d00;margin-top:8px;">MarzPay's sandbox doesn't keep test payments, so this page can't see them finish on its own. Use the buttons above. (On the test site, a signed sandbox callback can also complete it.)</div>
            @endif
            <div style="font-size:10.5px;color:#8a6d00;margin-top:8px;">Never shown in production or against a live payment account.</div>
        </div>
    @endif
</div>
@endsection

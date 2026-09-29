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
            {{ $transaction->purpose === 'subscription' ? 'Subscription payment' : 'Session payment' }} · waiting for approval
        </div>
    </div>

    <a href="{{ $checkUrl }}" class="btn coral block" style="border-radius:999px;">I've approved it — check now</a>

    @if ($autoCheck)
        <p style="font-size:12px;color:var(--ink-500);margin-top:14px;">
            We'll keep checking automatically. You'll move on as soon as it's confirmed.
        </p>
        <script>
            setTimeout(function () { window.location.href = @json($checkUrl); }, 8000);
        </script>
    @else
        <p style="font-size:12px;color:var(--ink-500);margin-top:14px;">
            This is taking longer than usual. If you've approved it, tap the button above to check again.
        </p>
    @endif

    <p style="font-size:10.5px;color:var(--ink-300);margin-top:16px;">
        Please don't start a new payment while this one is waiting — you could be charged twice.
        This is currently running against Flutterwave's sandbox, so no real money moves.
    </p>

    @if ($canSimulate)
        {{-- Only rendered when APP_ENV=local AND pointed at Flutterwave's sandbox — see
             FlutterwavePaymentController::simulationAllowed(). Flutterwave's v4 sandbox
             never completes Mobile Money charges on its own, so this stands in for the
             client approving on their phone. --}}
        <div style="margin-top:22px;padding:14px;border:1.5px dashed #c9a227;border-radius:12px;background:#fffbea;text-align:left;">
            <div style="font-size:10.5px;font-weight:800;letter-spacing:.06em;color:#8a6d00;margin-bottom:8px;">DEV ONLY · LOCAL SANDBOX</div>
            <div style="display:flex;gap:8px;">
                <form method="POST" action="{{ route('payments.flutterwave.simulate', $transaction) }}" style="flex:1;">
                    @csrf
                    <input type="hidden" name="outcome" value="approve">
                    <button type="submit" class="btn ghost sm" style="width:100%;">Simulate approval</button>
                </form>
                <form method="POST" action="{{ route('payments.flutterwave.simulate', $transaction) }}" style="flex:1;">
                    @csrf
                    <input type="hidden" name="outcome" value="fail">
                    <button type="submit" class="btn ghost sm" style="width:100%;">Simulate failure</button>
                </form>
            </div>
            <div style="font-size:10.5px;color:#8a6d00;margin-top:8px;">Never shown in production or against live Flutterwave.</div>
        </div>
    @endif
</div>
@endsection

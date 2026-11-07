{{-- Shown on a checkout page after a Mobile Money payment didn't go through (2026-11-03).
     Calm, not alarming: what happened, that no money was taken, and what to check before
     trying again. Set by FlutterwavePaymentController::failedNotice(). --}}
@if ($mwFail = session('payment_failed'))
    <div role="alert" style="text-align:left;background:#FFF8EC;border:1px solid #F3D7A8;border-radius:14px;padding:16px 18px;margin:0 0 18px;">
        <div style="display:flex;gap:12px;align-items:flex-start;">
            <span style="width:34px;height:34px;border-radius:50%;background:#FBF0D9;color:#B7791F;display:inline-flex;align-items:center;justify-content:center;flex:0 0 34px;">
                <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" aria-hidden="true"><path d="M12 8v5"/><circle cx="12" cy="16.5" r=".9" fill="currentColor" stroke="none"/><circle cx="12" cy="12" r="9"/></svg>
            </span>
            <div style="min-width:0;">
                <div style="font-weight:800;font-size:15px;color:#14213A;">{{ ($mwFail['kind'] ?? '') === 'client_cancelled' ? 'Payment cancelled' : 'Payment not completed' }}</div>
                <div style="font-size:13.5px;color:#3A4A63;margin-top:3px;line-height:1.5;">{{ $mwFail['reason'] ?? 'The payment wasn\'t approved on your phone.' }}</div>
                <div style="font-size:13px;color:#00795A;font-weight:700;margin-top:6px;display:flex;align-items:center;gap:6px;">
                    <svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2.6" aria-hidden="true"><path d="M5 12l5 5 9-10"/></svg>
                    {{ in_array($mwFail['kind'] ?? '', ['client_cancelled', 'timed_out'], true) ? 'No money has been taken.' : 'You have not been charged.' }}
                </div>
            </div>
        </div>
        <div style="font-size:12.5px;color:#6B4A10;margin-top:12px;padding-top:10px;border-top:1px solid #F3D7A8;line-height:1.6;">
            <b>Before you try again:</b>
            @if (in_array($mwFail['kind'] ?? '', ['client_cancelled', 'timed_out'], true))
                if your phone asks you to approve this payment again, decline it. If you do approve it, we'll still record it and let you know.
            @elseif (($mwFail['kind'] ?? '') === 'insufficient')
                top up your Mobile Money account, or use a different number.
            @elseif (($mwFail['kind'] ?? '') === 'number')
                check the number and the network (MTN or Airtel) below.
            @else
                keep your phone unlocked, wait for the prompt, and enter your Mobile Money PIN within about a minute.
                Check the number and the network below are right.
            @endif
        </div>
    </div>
@endif

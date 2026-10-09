{{-- What happens to the money when a session is cancelled (2026-10-08). Pre-ticked from
     config('mindwell.cancellation'); admin decides each time. Needs $money from
     App\Services\ScheduleChanges::moneyDefaults(). --}}
@if ($money)
    @php $mwCur = config('flutterwave.currency'); @endphp
    @if (! $money['on_time'])
        <div style="font-size:11.5px;color:#8a5a00;">Less than {{ config('mindwell.cancellation.notice_hours') }} hours' notice.</div>
    @endif
    @if ($money['has_credit'])
        <label style="font-size:12px;color:var(--ink-700);display:flex;gap:6px;align-items:flex-start;">
            <input type="checkbox" name="return_credit" value="1" {{ $money['return_credit'] ? 'checked' : '' }} style="margin-top:2px;">
            Give the plan credit back
        </label>
    @endif
    @if ($money['has_payment'])
        <label style="font-size:12px;color:var(--ink-700);display:flex;gap:6px;align-items:flex-start;">
            <input type="checkbox" name="refund" value="1" {{ $money['refund'] ? 'checked' : '' }} style="margin-top:2px;">
            Record a refund of {{ $money['payment']->currency }} {{ number_format((float) $money['payment']->amount, 0) }} (send the money back first)
        </label>
    @endif
    @if (! $money['has_credit'] && ! $money['has_payment'])
        <div style="font-size:11.5px;color:var(--ink-500);">Nothing to give back (employer seat, voucher or no payment).</div>
    @endif
@endif

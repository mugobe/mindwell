{{-- Test-site ribbon (2026-11-03): staff only, and only when APP_ENV is local or staging.
     Says which payment service is running in test mode, so test payments are never
     mistaken for real money. Never shown on the live site. --}}
@php
    $mwGw = config('payments.gateway') === 'marzpay' ? 'marzpay' : 'flutterwave';
    $mwPaySandbox = $mwGw === 'marzpay'
        ? (bool) config('marzpay.sandbox')
        : str_contains((string) config('flutterwave.base_url'), 'sandbox');
    $mwHost = parse_url((string) config('app.url'), PHP_URL_HOST) ?: request()->getHost();
@endphp
<div class="mw-ribbon" role="note">
    <b>{{ app()->environment('local') ? 'LOCAL' : 'TEST SITE' }}</b>
    <span><span class="host">{{ $mwHost }} · </span>Payments: {{ $mwGw === 'marzpay' ? 'MarzPay' : 'Flutterwave' }} {{ $mwPaySandbox ? 'sandbox, no real money moves' : 'LIVE keys' }}</span>
</div>

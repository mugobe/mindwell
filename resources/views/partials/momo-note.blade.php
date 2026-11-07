{{-- Under the Mobile Money form on checkout pages (2026-11-02): says which test
     setup is running, without naming a service the client doesn't need to know. --}}
@php
    $mwGateway = config('payments.gateway') === 'marzpay' ? 'marzpay' : 'flutterwave';
    $mwSandbox = $mwGateway === 'marzpay'
        ? (bool) config('marzpay.sandbox')
        : str_contains((string) config('flutterwave.base_url'), 'sandbox');
@endphp
You'll get a PIN prompt on your phone to approve this payment.
@if ($mwSandbox)
    This is currently a test (sandbox) setup, so no real money moves.
    @if ($mwGateway === 'flutterwave') Use OTP <strong>123456</strong> if a test prompt asks for one. @endif
@endif
Card payments are coming in a later update.

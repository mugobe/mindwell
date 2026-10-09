@extends('layouts.app')
@section('title', 'Terms of Use')
@section('content')
{{-- Phase 0 (2026-10-05). DRAFT text written by the assistant as a starting point for
     Dr. Ntabade / a lawyer. Not legal advice. Items in [brackets] need her decision. --}}
@php $biz = config('mindwell.receipt'); $contact = $biz['email'] ?? null; @endphp
<div class="card mw-legal" style="max-width:760px;margin:0 auto;padding:28px 30px;">
    @include('legal._draft-banner')
    <h1 style="font-size:22px;font-weight:800;color:var(--ink-900);margin-bottom:4px;">Terms of Use</h1>
    <p class="mw-legal-sub">The agreement between you and {{ $biz['business_name'] ?? 'Mindwell' }} when you use Mindwell.</p>

    <h2>1. The service</h2>
    <p>Mindwell connects you with qualified counsellors and therapists for online sessions, messaging and related support. Every therapist is reviewed and approved by Mindwell before they can see clients. Mindwell's team routes and approves all bookings.</p>

    <h2>2. Not an emergency service</h2>
    <p><strong>Mindwell does not replace emergency medical care.</strong> If you are in danger or thinking about harming yourself, call 999 or 112, go to your nearest hospital, or use the numbers on our <a href="{{ route('urgent-help') }}">Get help now</a> page. Messages and alerts on Mindwell are not monitored around the clock.</p>

    <h2>3. Who can use Mindwell</h2>
    <p>You must be {{ config('mindwell.min_client_age', 18) }} or older and give accurate information when you sign up. Keep your password private; you are responsible for activity on your account.</p>

    <h2>4. Sessions</h2>
    <ul>
        <li>Sessions take place by video at the booked time. You can join a few minutes early.</li>
        <li>Counselling may not be right for everyone, and results can't be guaranteed. Mindwell does not provide diagnoses or prescribe medication; your therapist may refer you to other services.</li>
        <li>[Cancellation and missed-session rules to be confirmed by Dr. Ntabade.]</li>
    </ul>

    <h2>5. Payments</h2>
    <ul>
        <li>Prices are shown before you pay. You can pay per session or choose a plan with a number of sessions each period.</li>
        <li>Plans renew only when you renew them. Unused session credits do not carry over to the next period.</li>
        <li>[Refund policy to be confirmed by Dr. Ntabade.]</li>
    </ul>

    <h2>6. Respect and safety</h2>
    <p>Please treat therapists and staff with respect. Don't record sessions without consent, share other people's information, or misuse the service. We may suspend accounts that put others at risk.</p>

    <h2>7. Confidentiality</h2>
    <p>What you share is confidential, as explained in our <a href="{{ route('privacy') }}">Privacy Policy</a>, except where the law requires otherwise or there is a serious and immediate risk to someone's safety.</p>

    <h2>8. Ending your account</h2>
    <p>You can stop using Mindwell at any time{{ $contact ? ' and ask us to close your account at '.$contact : '' }}. Some records must be kept for legal and professional reasons.</p>

    <h2>9. Our responsibility</h2>
    <p>We work hard to keep Mindwell available and secure, but we can't promise it will always be uninterrupted. To the extent the law allows, Mindwell is not liable for indirect losses arising from use of the service.</p>

    <h2>10. Law</h2>
    <p>These terms are governed by the laws of Uganda.</p>

    <h2>11. Changes</h2>
    <p>If we change these terms we will update the version below and ask you to accept them the next time you log in.</p>

    <p class="mw-legal-sub" style="margin-top:22px;">Version {{ config('mindwell.legal.version') }}{{ $contact ? ' · Questions: '.$contact : '' }}</p>
</div>
@include('legal._styles')
@endsection

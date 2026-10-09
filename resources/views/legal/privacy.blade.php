@extends('layouts.app')
@section('title', 'Privacy Policy')
@section('content')
{{-- Phase 0 (2026-10-05). DRAFT text written by the assistant as a starting point for
     Dr. Ntabade / a lawyer, with Uganda's Data Protection and Privacy Act 2019 (DPPA) in
     mind. Not legal advice. Business details come from config('mindwell.receipt'). --}}
@php $biz = config('mindwell.receipt'); $contact = $biz['email'] ?? null; @endphp
<div class="card mw-legal" style="max-width:760px;margin:0 auto;padding:28px 30px;">
    @include('legal._draft-banner')
    <h1 style="font-size:22px;font-weight:800;color:var(--ink-900);margin-bottom:4px;">Privacy Policy</h1>
    <p class="mw-legal-sub">How {{ $biz['business_name'] ?? 'Mindwell' }} collects, uses and protects your personal information.</p>

    <h2>1. Who we are</h2>
    <p>Mindwell is an online counselling and therapy service operated by {{ $biz['business_name'] ?? 'Mindwell' }}{{ ! empty($biz['address']) ? ', '.$biz['address'] : '' }}, in Uganda. We are responsible for the personal data you share with us, under the Data Protection and Privacy Act, 2019.</p>

    <h2>2. What we collect</h2>
    <ul>
        <li><strong>Account details:</strong> your name, email, phone number, date of birth, gender (optional) and password (stored encrypted).</li>
        <li><strong>Health information you share:</strong> your answers to screening questions (such as PHQ-9 and GAD-7), check-ins, mood entries, journal entries, messages with your therapist, and the notes your therapist writes about your sessions. This is sensitive personal data and we treat it with extra care.</li>
        <li><strong>Payments:</strong> the amount, date, payment method and a masked phone number. Mobile Money payments are processed by Flutterwave; we never see your PIN.</li>
        <li><strong>Technical information:</strong> basic logs (such as when you logged in) needed to keep the service secure.</li>
    </ul>

    <h2>3. Why we use it</h2>
    <ul>
        <li>To provide counselling: matching you with a therapist, booking and running sessions, and keeping clinical records.</li>
        <li>To keep you safe: if your answers suggest you may be at risk of harm, our care team is alerted so someone can follow up.</li>
        <li>To send reminders, receipts and important account messages by email and SMS (you can turn reminders off in Settings).</li>
        <li>To take payments and meet our legal and accounting obligations.</li>
        <li>To improve the service, using anonymous or summarised information where possible.</li>
    </ul>
    <p>We use your sensitive health information only with your consent, which you give when you create your account, and only for your care.</p>

    <h2>4. Who can see it</h2>
    <ul>
        <li><strong>Your therapist</strong> sees your screening and check-in results, session notes, and anything you choose to share (journal entries and your mood chart are private unless you share them).</li>
        <li><strong>Authorised Mindwell staff</strong> see what they need to route bookings, handle safety alerts, payments and support. Access to clinical information is recorded in an audit log.</li>
        <li><strong>Service providers</strong> who help us run Mindwell, under agreements to protect your data: hosting, email delivery, SMS (Pandora Networks), payments (Flutterwave) and video calls (LiveKit). Some of these process data outside Uganda; we only use providers that protect it to a standard comparable to Ugandan law.</li>
        <li>We do <strong>not</strong> sell your data or use it for advertising.</li>
    </ul>
    <p>Video sessions are not recorded.</p>

    <h2>5. When we may share without your permission</h2>
    <p>Only where the law requires it, or where there is a serious and immediate risk to your life or someone else's — for example, contacting emergency services. Your therapist will explain these limits of confidentiality.</p>

    <h2>6. How long we keep it</h2>
    <p>We keep clinical records for as long as required for professional and legal purposes, and other information for as long as your account is active or the law requires. [Retention periods to be confirmed by Dr. Ntabade.]</p>

    <h2>7. Your rights</h2>
    <p>Under the Data Protection and Privacy Act you can ask to see the personal data we hold about you, ask us to correct it, object to certain uses, and ask us to delete it where we are not required to keep it. You can withdraw your consent at any time — this may mean we can no longer provide counselling. To make a request, contact us{{ $contact ? ' at '.$contact : '' }}. You can also complain to the Personal Data Protection Office.</p>

    <h2>8. Security</h2>
    <p>We use encrypted connections (HTTPS), encrypted passwords, access controls and audit logs. No system is perfectly secure; if a breach affects your data we will tell you and the regulator as the law requires.</p>

    <h2>9. Age</h2>
    <p>Mindwell accounts are for adults aged {{ config('mindwell.min_client_age', 18) }} and over.</p>

    <h2>10. Changes</h2>
    <p>If we change this policy we will update the version below and ask you to review and accept it the next time you log in.</p>

    <p class="mw-legal-sub" style="margin-top:22px;">Version {{ config('mindwell.legal.version') }}{{ $contact ? ' · Questions: '.$contact : '' }}</p>
</div>
@include('legal._styles')
@endsection

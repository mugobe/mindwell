@extends('layouts.app')
@section('title', 'Apply as a therapist')
@section('content')
{{-- Therapist sign-up (rebuilt 2026-11-03): step 0 of onboarding, the account only. The
     application itself follows in apply/*. See TherapistRegistrationController. --}}
<style>
    @font-face { font-family: 'Plus Jakarta Sans'; font-weight: 200 800; font-display: swap; src: url('{{ asset('fonts/plus-jakarta-sans-latin.woff2') }}') format('woff2'); }
    .rt-shell { display: grid; grid-template-columns: minmax(0, 1fr) minmax(0, 1.05fr); min-height: 100vh; margin: -28px; font-family: 'Plus Jakarta Sans', -apple-system, 'Segoe UI', Roboto, sans-serif; }
    .rt-side { background: linear-gradient(160deg, #031F3F 0%, #04305E 55%, #034C8F 100%); color: #fff; padding: 48px 44px; display: flex; flex-direction: column; }
    .rt-side h2 { font-size: 28px; font-weight: 800; letter-spacing: -.02em; line-height: 1.2; margin: 34px 0 10px; }
    .rt-side p { color: rgba(255,255,255,.8); font-size: 14.5px; line-height: 1.6; margin: 0; }
    .rt-steps { list-style: none; padding: 0; margin: 28px 0 0; display: grid; gap: 12px; }
    .rt-steps li { display: flex; gap: 12px; align-items: flex-start; font-size: 13.5px; color: rgba(255,255,255,.86); line-height: 1.45; }
    .rt-steps .n { width: 24px; height: 24px; border-radius: 50%; background: rgba(255,255,255,.12); border: 1px solid rgba(255,255,255,.22); display: inline-flex; align-items: center; justify-content: center; font-size: 11.5px; font-weight: 800; flex: 0 0 24px; }
    .rt-steps li.on .n { background: #EE7800; border-color: #EE7800; }
    .rt-steps b { color: #fff; }
    .rt-need { margin-top: auto; padding-top: 28px; font-size: 12.5px; color: rgba(255,255,255,.72); line-height: 1.6; }
    .rt-need b { color: #FFB766; }
    .rt-form { display: flex; align-items: center; justify-content: center; padding: 44px 28px; background: #fff; }
    .rt-form .in { width: 100%; max-width: 440px; }
    @media (max-width: 900px) { .rt-shell { grid-template-columns: 1fr; } .rt-side { padding: 32px 22px; } .rt-need { display: none; } }
</style>
<div class="rt-shell">
    <div class="rt-side">
        <img src="{{ asset('images/mindwell-logo-horizontal-white.png') }}" alt="Mindwell Consultancy Ltd" style="height:42px;width:auto;align-self:flex-start;">
        <h2>Join Mindwell as a therapist</h2>
        <p>Work with clients across Uganda by video or in person. Mindwell handles booking, payments and reminders, so you can focus on care.</p>
        <ol class="rt-steps">
            <li class="on"><span class="n">1</span><span><b>Create your account</b> (this page, 1 minute)</span></li>
            <li><span class="n">2</span><span>Your licence, training and experience</span></li>
            <li><span class="n">3</span><span>Fitness to practise and two references</span></li>
            <li><span class="n">4</span><span>Your profile and our agreements</span></li>
            <li><span class="n">5</span><span>Our team reviews it, usually within a few working days</span></li>
        </ol>
        <div class="rt-need"><b>Have these ready:</b> your licence or registration number and certificate, your CV, contact details for two professional references, and a clear photo. You can save and finish later.</div>
    </div>

    <div class="rt-form">
        <div class="in">
            <h1 style="font-size:22px;font-weight:800;letter-spacing:-.01em;margin:0 0 6px;color:#14213A;">Create your therapist account</h1>
            <p style="font-size:13px;color:#66758C;margin:0 0 22px;">Not a therapist? <a href="{{ route('register') }}" style="color:#034C8F;font-weight:700;text-decoration:none;">Sign up as a client</a>. Already applied? <a href="{{ route('login') }}" style="color:#034C8F;font-weight:700;text-decoration:none;">Log in</a>.</p>

            @if ($errors->any())
                <div class="mw-alert danger">
                    <ul style="margin:0;padding-left:16px;">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form method="POST" action="{{ route('register.therapist') }}" style="display:flex;flex-direction:column;gap:15px;">
                @csrf
                <div>
                    <label class="mw-label" for="name">Full name, as on your licence</label>
                    <input class="mw-field" type="text" id="name" name="name" value="{{ old('name') }}" required autofocus autocomplete="name">
                </div>
                <div>
                    <label class="mw-label" for="email">Email</label>
                    <input class="mw-field" type="email" id="email" name="email" value="{{ old('email') }}" required autocomplete="email">
                </div>
                <div>
                    <label class="mw-label" for="phone">Mobile number</label>
                    <input class="mw-field" type="tel" id="phone" name="phone" value="{{ old('phone') }}" placeholder="0772 123 456" required autocomplete="tel">
                    <div style="font-size:11.5px;color:#66758C;margin-top:4px;">So the team can reach you about your application.</div>
                </div>
                <div style="display:flex;gap:12px;">
                    <div style="flex:1;">
                        <label class="mw-label" for="password">Password</label>
                        <input class="mw-field" type="password" id="password" name="password" required autocomplete="new-password" minlength="8">
                    </div>
                    <div style="flex:1;">
                        <label class="mw-label" for="password_confirmation">Confirm password</label>
                        <input class="mw-field" type="password" id="password_confirmation" name="password_confirmation" required autocomplete="new-password">
                    </div>
                </div>
                <div style="display:flex;flex-direction:column;gap:8px;font-size:12.5px;color:#3A4A63;">
                    <label style="display:flex;gap:8px;align-items:flex-start;">
                        <input type="checkbox" name="accept_privacy" value="1" {{ old('accept_privacy') ? 'checked' : '' }} required style="margin-top:2px;accent-color:#034C8F;">
                        <span>I agree to the <a href="{{ route('privacy') }}" target="_blank" style="color:#034C8F;font-weight:700;">Privacy Policy</a></span>
                    </label>
                    <label style="display:flex;gap:8px;align-items:flex-start;">
                        <input type="checkbox" name="accept_terms" value="1" {{ old('accept_terms') ? 'checked' : '' }} required style="margin-top:2px;accent-color:#034C8F;">
                        <span>I agree to the <a href="{{ route('terms') }}" target="_blank" style="color:#034C8F;font-weight:700;">Terms of Use</a></span>
                    </label>
                </div>
                <button type="submit" class="btn block" style="margin-top:4px;background:#EE7800;color:#fff;border:0;">Create account and start my application</button>
            </form>
        </div>
    </div>
</div>
@endsection

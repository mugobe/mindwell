{{-- Where an application stands (2026-11-03). Used on /apply/status and on the dashboard of
     a therapist who isn't approved yet. Needs $app. --}}
@include('apply._style')
@php
    $S = \App\Models\TherapistApplication::class;
    $mwStepsDone = collect(array_keys(config('onboarding.steps')))->reject(fn ($s) => $s === 'review')
        ->reject(fn ($s) => isset($app->missing()[$s]))->count();
    $mwTotal = count(config('onboarding.steps')) - 1;
    $st = $app->status;
    $sentAt = $app->submitted_at;
@endphp

<div class="ap-wrap">
    <div class="ap-head">
        <div>
            <div style="font-size:12px;font-weight:800;letter-spacing:.1em;color:#EE7800;">THERAPIST APPLICATION</div>
            <h1>{{ [
                $S::DRAFT => 'Finish your application',
                $S::SUBMITTED => 'Your application is with our team',
                $S::CHANGES => 'We need a few changes',
                $S::DECLINED => 'About your application',
            ][$st] ?? 'Your application' }}</h1>
            <div class="sub">Hi {{ explode(' ', trim(auth()->user()->name))[0] }}. Here's where things stand.</div>
        </div>
        <span class="mw-chip {{ $app->statusTone() }}">{{ $app->statusLabel() }}</span>
    </div>

    <div class="ap-card">
        <div class="ap-track">
            <div class="st done"><span class="dot"></span>Account created<span class="when">{{ $app->created_at->format('j M') }}</span></div>
            <div class="st {{ $sentAt ? 'done' : 'now' }}"><span class="dot"></span>Application sent<span class="when">{{ $sentAt ? $sentAt->format('j M') : $mwStepsDone.' of '.$mwTotal.' steps done' }}</span></div>
            <div class="st {{ $st === $S::SUBMITTED ? 'now' : ($st === $S::CHANGES ? 'warn' : (in_array($st, [$S::APPROVED, $S::DECLINED], true) ? 'done' : '')) }}"><span class="dot"></span>{{ $st === $S::CHANGES ? 'Changes needed' : 'Under review' }}<span class="when">{{ $st === $S::SUBMITTED ? 'Usually a few working days' : ($st === $S::CHANGES && $app->decided_at ? $app->decided_at->format('j M') : '') }}</span></div>
            <div class="st {{ $st === $S::APPROVED ? 'done' : ($st === $S::DECLINED ? 'bad' : '') }}"><span class="dot"></span>{{ $st === $S::DECLINED ? 'Not approved' : 'Approved' }}<span class="when">{{ in_array($st, [$S::APPROVED, $S::DECLINED], true) && $app->decided_at ? $app->decided_at->format('j M') : '' }}</span></div>
        </div>
    </div>

    @if ($st === $S::DRAFT)
        <div class="ap-card" style="display:flex;align-items:center;gap:18px;flex-wrap:wrap;">
            <div style="flex:1;min-width:240px;">
                <h2>{{ $mwStepsDone }} of {{ $mwTotal }} steps done</h2>
                <p class="hint" style="margin:0;">Your answers are saved as you go. When every step is complete, check your answers and send your application.</p>
            </div>
            <a href="{{ route('apply.start') }}" class="btn primary">{{ $mwStepsDone ? 'Continue my application' : 'Start my application' }} →</a>
        </div>
        <div class="ap-card">
            <h2>What you'll need</h2>
            <ul style="margin:8px 0 0;padding-left:18px;font-size:13.5px;color:var(--ink-700);line-height:1.8;">
                <li>Your licence or registration number and expiry date, and a copy of the certificate</li>
                <li>Your CV</li>
                <li>Names and contact details for two professional references</li>
                <li>A clear photo of yourself, and a short bio for clients</li>
            </ul>
        </div>
    @elseif ($st === $S::SUBMITTED)
        <div class="ap-card">
            <h2>What happens now</h2>
            <ol style="margin:8px 0 0;padding-left:18px;font-size:13.5px;color:var(--ink-700);line-height:1.8;">
                <li>We confirm your licence with {{ $app->regulatorLabel() ?: 'your regulator' }}.</li>
                <li>We review your CV and answers, and contact your two references.</li>
                <li>We may invite you to a short call with Dr. Ntabade's team.</li>
                <li>You'll get an email with our decision. When you're approved, your therapist portal opens straight away.</li>
            </ol>
            <p class="hint" style="margin:14px 0 0;">Sent on {{ $sentAt?->format('j F Y') }}. If something changes, such as your licence or phone number, email us at {{ config('mindwell.receipt.email') ?: config('mail.from.address') }}.</p>
        </div>
    @elseif ($st === $S::CHANGES)
        <div class="ap-card" style="border-left:4px solid #B7791F;">
            <h2>What the team asked</h2>
            <div style="font-size:14px;color:var(--ink-900);line-height:1.6;white-space:pre-line;margin-top:6px;">{{ $app->decision_note }}</div>
            <div style="margin-top:16px;"><a href="{{ route('apply.start') }}" class="btn primary">Update my application →</a></div>
            <p class="hint" style="margin:12px 0 0;">Your other answers are saved. Change what the note asks for, then send it again from the last step.</p>
        </div>
    @elseif ($st === $S::DECLINED)
        <div class="ap-card" style="border-left:4px solid #D64545;">
            <h2>We're not able to approve your application</h2>
            @if ($app->decision_note)
                <div style="font-size:14px;color:var(--ink-900);line-height:1.6;white-space:pre-line;margin-top:6px;">{{ $app->decision_note }}</div>
            @endif
            <p class="hint" style="margin:12px 0 0;">Thank you for your interest in Mindwell. If you have questions about this decision, email us at {{ config('mindwell.receipt.email') ?: config('mail.from.address') }}.</p>
        </div>
    @endif
</div>

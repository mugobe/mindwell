{{-- Step 6: check everything, then send. --}}
@php
    $mwMissing = $missing;
    $mwSteps = config('onboarding.steps');
@endphp

@if ($mwMissing)
    <div class="ap-missing">
        <b>Before you can send your application, please add:</b>
        @foreach ($mwMissing as $s => $items)
            <div style="margin-top:8px;"><a href="{{ route('apply.step', $s) }}" style="color:#6B4A10;font-weight:800;">{{ $mwSteps[$s] ?? $s }} →</a>
                <ul>@foreach ($items as $m)<li>{{ $m }}</li>@endforeach</ul>
            </div>
        @endforeach
    </div>
@else
    <div class="mw-alert success">Everything is filled in. Check your answers, then send your application at the bottom of the page.</div>
@endif

@include('apply._summary', ['mwEdit' => true])

<form method="POST" action="{{ route('apply.submit') }}" class="ap-card">
    @csrf
    <label class="agree" style="display:flex;gap:10px;align-items:flex-start;font-size:13.5px;font-weight:600;color:var(--ink-900);">
        <input type="checkbox" name="confirm" value="1" style="margin-top:3px;width:17px;height:17px;accent-color:#034C8F;" {{ $mwMissing ? 'disabled' : '' }}>
        <span>Everything in this application is true and complete. I understand that Mindwell will check my licence with the regulator and contact my references, and that false information means my application, or my place on Mindwell, can be withdrawn.</span>
    </label>
    @error('confirm')<div class="ap-err">{{ $message }}</div>@enderror
    <div class="ap-actions">
        <a href="{{ route('apply.step', 'consents') }}" class="btn muted sm">← Back</a>
        <button type="submit" class="btn primary" {{ $mwMissing ? 'disabled' : '' }} style="{{ $mwMissing ? 'opacity:.5;cursor:not-allowed;' : '' }}">{{ $app->status === \App\Models\TherapistApplication::CHANGES ? 'Send my updated application' : 'Send my application' }}</button>
    </div>
</form>

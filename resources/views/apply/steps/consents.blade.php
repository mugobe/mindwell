{{-- Step 5: recorded consents (code of conduct, confidentiality, data protection). --}}
@foreach (config('onboarding.consents') as $key => $doc)
    @php $rec = $consents[$key] ?? null; @endphp
    <div class="ap-card ap-consent">
        <div style="display:flex;align-items:center;justify-content:space-between;gap:10px;">
            <h2>{{ $doc['title'] }}</h2>
            <span class="mw-chip grey">Version {{ $doc['version'] }}</span>
        </div>
        <div class="doc" tabindex="0" aria-label="{{ $doc['title'] }} text">
            <ol style="margin:0;padding-left:18px;">
                @foreach ($doc['points'] as $p)<li>{{ $p }}</li>@endforeach
            </ol>
        </div>
        @if ($rec)
            <div class="agree" style="color:#00795A;">
                <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.4" style="flex:0 0 18px;margin-top:1px;"><path d="M5 12l5 5 9-10"/></svg>
                <span>You agreed on {{ $rec->accepted_at->format('j F Y, g:i A') }}.</span>
            </div>
        @else
            <label class="agree">
                <input type="checkbox" name="agree[{{ $key }}]" value="1" @checked(old("agree.$key"))>
                <span>I have read and agree to the {{ strtolower($doc['title']) }}.</span>
            </label>
        @endif
    </div>
@endforeach
<p class="ap-help" style="margin-top:-4px;">Your agreement is recorded with the date, time and version, and kept with your application.</p>

{{-- Step 4: client-facing profile. --}}
@php
    $a = fn ($k, $d = null) => old($k, $app->answer('profile.'.$k, $d));
    $photo = $app->answer('profile.photo_path');
@endphp
<div class="ap-card">
    <h2>Photo</h2>
    <p class="hint">A clear, recent head-and-shoulders photo with a plain background. Clients see this when they choose who to book.</p>
    <div style="display:flex;align-items:center;gap:18px;flex-wrap:wrap;">
        <div style="width:92px;height:92px;border-radius:50%;overflow:hidden;background:#E3EDF8;display:flex;align-items:center;justify-content:center;flex:0 0 92px;box-shadow:0 0 0 1px var(--mw-line);">
            @if ($photo)
                <img src="{{ asset('storage/'.$photo) }}" alt="Your photo" style="width:100%;height:100%;object-fit:cover;">
            @else
                <svg viewBox="0 0 24 24" width="40" height="40" fill="none" stroke="#034C8F" stroke-width="1.6"><circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0116 0"/></svg>
            @endif
        </div>
        <div>
            <input type="file" name="photo" accept="image/jpeg,image/png,image/webp" style="font-size:13px;">
            <div class="ap-help">JPG, PNG or WEBP, up to 2 MB.</div>
            @error('photo')<div class="ap-err">{{ $message }}</div>@enderror
        </div>
    </div>
</div>

<div class="ap-card">
    <h2>About you</h2>
    <div class="ap-grid">
        <div>
            <label class="mw-label" for="professional_title">Professional title</label>
            <input class="mw-field" id="professional_title" name="professional_title" value="{{ $a('professional_title') }}" placeholder="e.g. Counselling Psychologist" maxlength="120">
            @error('professional_title')<div class="ap-err">{{ $message }}</div>@enderror
        </div>
        <div>
            <label class="mw-label" for="fee">Your usual fee per session, {{ config('flutterwave.currency', 'UGX') }} <span class="ap-opt">(optional)</span></label>
            <input class="mw-field" type="number" id="fee" name="fee" value="{{ $a('fee') }}" min="0" step="500" inputmode="numeric">
            <div class="ap-help">For our records. Mindwell agrees final client prices with you.</div>
            @error('fee')<div class="ap-err">{{ $message }}</div>@enderror
        </div>
        <div class="full">
            <label class="mw-label" for="bio">Short bio</label>
            <textarea class="mw-field" id="bio" name="bio" rows="6" maxlength="1500" placeholder="Who you help, how you work, and what a first session with you is like." oninput="document.getElementById('bio-n').textContent = this.value.length">{{ $a('bio') }}</textarea>
            <div class="ap-help"><span id="bio-n">{{ mb_strlen((string) $a('bio')) }}</span> / 1500 characters, at least 80. Write to a future client, in plain words.</div>
            @error('bio')<div class="ap-err">{{ $message }}</div>@enderror
        </div>
    </div>
</div>

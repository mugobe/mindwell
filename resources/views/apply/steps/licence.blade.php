{{-- Step 1: licence and registration. --}}
@php $a = fn ($k, $d = null) => old($k, $app->answer('licence.'.$k, $d)); @endphp
<div class="ap-card">
    <h2>Your profession</h2>
    <p class="hint">Choose the title you are registered or licensed under.</p>
    <div class="ap-grid">
        <div>
            <label class="mw-label" for="profession">Profession</label>
            <select class="mw-field" id="profession" name="profession" onchange="document.getElementById('prof-other').style.display = this.value === 'other' ? '' : 'none'">
                <option value="">Choose…</option>
                @foreach (config('onboarding.professions') as $k => $label)
                    <option value="{{ $k }}" @selected($a('profession') === $k)>{{ $label }}</option>
                @endforeach
            </select>
            @error('profession')<div class="ap-err">{{ $message }}</div>@enderror
        </div>
        <div id="prof-other" style="{{ $a('profession') === 'other' ? '' : 'display:none;' }}">
            <label class="mw-label" for="profession_other">Your profession</label>
            <input class="mw-field" id="profession_other" name="profession_other" value="{{ $a('profession_other') }}" maxlength="120">
        </div>
    </div>
</div>

<div class="ap-card">
    <h2>Registration</h2>
    <p class="hint">The body that registers or licenses you, and your number with them. We confirm this directly with them before approving you.</p>
    <div class="ap-grid">
        <div class="full">
            <label class="mw-label" for="regulator">Registered with</label>
            <select class="mw-field" id="regulator" name="regulator" onchange="document.getElementById('reg-other').style.display = this.value === 'other' ? '' : 'none'">
                <option value="">Choose…</option>
                @foreach (config('onboarding.regulators') as $k => $label)
                    <option value="{{ $k }}" @selected($a('regulator') === $k)>{{ $label }}</option>
                @endforeach
            </select>
            @error('regulator')<div class="ap-err">{{ $message }}</div>@enderror
        </div>
        <div class="full" id="reg-other" style="{{ $a('regulator') === 'other' ? '' : 'display:none;' }}">
            <label class="mw-label" for="regulator_other">Name of the body</label>
            <input class="mw-field" id="regulator_other" name="regulator_other" value="{{ $a('regulator_other') }}" maxlength="160">
        </div>
        <div class="full">
            <label class="mw-label" for="licence_number">Licence or registration number</label>
            <input class="mw-field" id="licence_number" name="licence_number" value="{{ $a('licence_number') }}" maxlength="80" autocomplete="off">
            @error('licence_number')<div class="ap-err">{{ $message }}</div>@enderror
        </div>
        <div>
            <label class="mw-label" for="licence_issued">Issued on <span class="ap-opt">(optional)</span></label>
            <input class="mw-field" type="date" id="licence_issued" name="licence_issued" value="{{ $a('licence_issued') }}" max="{{ now()->toDateString() }}">
            @error('licence_issued')<div class="ap-err">{{ $message }}</div>@enderror
        </div>
        <div>
            <label class="mw-label" for="licence_expiry">Expires on</label>
            <input class="mw-field" type="date" id="licence_expiry" name="licence_expiry" value="{{ $a('licence_expiry') }}" min="{{ now()->addDay()->toDateString() }}">
            <div class="ap-help">We'll remind you before it runs out.</div>
            @error('licence_expiry')<div class="ap-err">{{ $message }}</div>@enderror
        </div>
    </div>
</div>

<div class="ap-card">
    <h2>Documents</h2>
    <p class="hint">A clear photo or scan is fine. Files are stored privately; only the Mindwell team can open them.</p>
    <div style="display:grid;gap:10px;">
        @include('apply._file', ['kind' => 'licence'])
        @include('apply._file', ['kind' => 'national_id'])
    </div>
</div>

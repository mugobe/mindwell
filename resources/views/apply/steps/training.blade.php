{{-- Step 2: training and experience. --}}
@php
    $a = fn ($k, $d = null) => old($k, $app->answer('training.'.$k, $d));
    $picked = fn ($k) => (array) old($k, $app->answer('training.'.$k, []));
@endphp
<div class="ap-card">
    <h2>Qualifications</h2>
    <p class="hint">Your highest qualification in psychology, counselling, psychiatry or a related field.</p>
    <div class="ap-grid">
        <div>
            <label class="mw-label" for="qualification_level">Level</label>
            <select class="mw-field" id="qualification_level" name="qualification_level">
                <option value="">Choose…</option>
                @foreach (config('onboarding.qualification_levels') as $k => $label)
                    <option value="{{ $k }}" @selected($a('qualification_level') === $k)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="mw-label" for="qualification_title">Name of the qualification</label>
            <input class="mw-field" id="qualification_title" name="qualification_title" value="{{ $a('qualification_title') }}" placeholder="e.g. MSc Counselling Psychology" maxlength="160">
        </div>
        <div>
            <label class="mw-label" for="institution">Where you studied</label>
            <input class="mw-field" id="institution" name="institution" value="{{ $a('institution') }}" placeholder="e.g. Makerere University" maxlength="160">
        </div>
        <div>
            <label class="mw-label" for="qualification_year">Year completed</label>
            <input class="mw-field" type="number" id="qualification_year" name="qualification_year" value="{{ $a('qualification_year') }}" min="1960" max="{{ now()->year }}" inputmode="numeric">
            @error('qualification_year')<div class="ap-err">{{ $message }}</div>@enderror
        </div>
        <div class="full">
            <label class="mw-label" for="other_qualifications">Other training and certificates <span class="ap-opt">(optional)</span></label>
            <textarea class="mw-field" id="other_qualifications" name="other_qualifications" rows="3" maxlength="2000" placeholder="e.g. Certificate in Trauma-Focused CBT, 2021">{{ $a('other_qualifications') }}</textarea>
        </div>
    </div>
</div>

<div class="ap-card">
    <h2>Experience and supervision</h2>
    <div class="ap-grid">
        <div>
            <label class="mw-label" for="years_practice">Years in practice since qualifying</label>
            <input class="mw-field" type="number" id="years_practice" name="years_practice" value="{{ $a('years_practice') }}" min="0" max="60" inputmode="numeric">
            @error('years_practice')<div class="ap-err">{{ $message }}</div>@enderror
        </div>
        <div>
            <span class="mw-label">Do you receive regular clinical supervision?</span>
            <div class="ap-yn" role="radiogroup">
                <label>Yes<input type="radio" name="supervision" value="yes" @checked($a('supervision') === 'yes') onchange="document.getElementById('sup').style.display=''"></label>
                <label>No<input type="radio" name="supervision" value="no" @checked($a('supervision') === 'no') onchange="document.getElementById('sup').style.display='none'"></label>
            </div>
        </div>
        <div class="full ap-grid" id="sup" style="{{ $a('supervision') === 'yes' ? '' : 'display:none;' }}">
            <div>
                <label class="mw-label" for="supervisor_name">Supervisor's name <span class="ap-opt">(optional)</span></label>
                <input class="mw-field" id="supervisor_name" name="supervisor_name" value="{{ $a('supervisor_name') }}" maxlength="120">
            </div>
            <div>
                <label class="mw-label" for="supervisor_contact">Their email or phone <span class="ap-opt">(optional)</span></label>
                <input class="mw-field" id="supervisor_contact" name="supervisor_contact" value="{{ $a('supervisor_contact') }}" maxlength="160">
            </div>
        </div>
    </div>
    <div style="margin-top:16px;">
        @include('apply._file', ['kind' => 'cv'])
    </div>
</div>

<div class="ap-card">
    <h2>Areas you work with</h2>
    <p class="hint">Tick all that apply. Clients are matched to you on these.</p>
    <div class="ap-checks">
        @foreach (config('onboarding.specialties') as $item)
            <label class="ap-check"><input type="checkbox" name="specialties[]" value="{{ $item }}" @checked(in_array($item, $picked('specialties'), true))>{{ $item }}</label>
        @endforeach
    </div>
</div>

<div class="ap-card">
    <h2>Therapy approaches</h2>
    <p class="hint">The approaches you are trained in and use.</p>
    <div class="ap-checks">
        @foreach (config('onboarding.approaches') as $item)
            <label class="ap-check"><input type="checkbox" name="approaches[]" value="{{ $item }}" @checked(in_array($item, $picked('approaches'), true))>{{ $item }}</label>
        @endforeach
    </div>
</div>

<div class="ap-card">
    <h2>Languages, clients and session types</h2>
    <span class="mw-label">Languages you can hold sessions in</span>
    <div class="ap-checks">
        @foreach (config('onboarding.languages') as $item)
            <label class="ap-check"><input type="checkbox" name="languages[]" value="{{ $item }}" @checked(in_array($item, $picked('languages'), true))>{{ $item }}</label>
        @endforeach
    </div>
    <div style="margin-top:10px;max-width:420px;">
        <label class="mw-label" for="languages_other">Other languages <span class="ap-opt">(optional, separate with commas)</span></label>
        <input class="mw-field" id="languages_other" name="languages_other" value="{{ $a('languages_other') }}" maxlength="160">
    </div>

    <span class="mw-label" style="margin-top:18px;display:block;">Who you work with</span>
    <div class="ap-checks">
        @foreach (config('onboarding.age_groups') as $k => $label)
            <label class="ap-check"><input type="checkbox" name="age_groups[]" value="{{ $k }}" @checked(in_array($k, $picked('age_groups'), true))>{{ $label }}</label>
        @endforeach
    </div>

    <span class="mw-label" style="margin-top:18px;display:block;">How you can see clients</span>
    <div class="ap-checks">
        @foreach (config('onboarding.session_types') as $k => $label)
            <label class="ap-check"><input type="checkbox" name="session_types[]" value="{{ $k }}" @checked(in_array($k, $picked('session_types'), true))>{{ $label }}</label>
        @endforeach
    </div>
</div>

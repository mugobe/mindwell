{{-- "About you" sections (2026-10-09, roadmap 63 — Dr. Ntabade's CLIENT DRAFT item 3).
     Used two ways:
       mode 'wizard' — one step per section inside the intake wizard, with Continue / Skip;
       mode 'page'   — all sections as cards on the "About you" page (existing clients).
     Only sections an admin has switched on are shown (App\Support\IntakeQuestions). --}}
@php
    $IQ = \App\Support\IntakeQuestions::class;
    $mode = $mode ?? 'wizard';
    $d = $detail ?? null;
    $wrap = $mode === 'wizard' ? 'mw-step mw-about-step' : 'card mw-about-card';
    $val = fn ($key, $field) => old($key, $d?->{$field});
    $picked = fn ($key, $field) => (array) old($key, $d?->{$field} ?? []);
    $moodColors = [1 => '#E9A9A9', 2 => '#F2C4A0', 3 => '#F3DC9A', 4 => '#BCDDBF', 5 => '#86C7AE'];
    $total = count($sectionsOn);
@endphp

@foreach ($sectionsOn as $i => $section)
    <div class="{{ $wrap }}" data-section="{{ $section }}">
        <div class="mw-step-eyebrow">About you{{ $mode === 'wizard' ? ' · '.($i + 1).' of '.$total : '' }} · optional</div>

        @if ($section === 'topics')
            <div class="mw-step-question">What brings you here?</div>
            <div class="mw-about-help">Pick anything that fits — as many as you like.</div>
            <div class="mw-chips" role="group" aria-label="Topics">
                @foreach ($IQ::topicChoices($d) as $t)
                    <label class="mw-chip-opt"><input type="checkbox" name="about_topics[]" value="{{ $t }}" {{ in_array($t, $picked('about_topics', 'topics'), true) ? 'checked' : '' }}><span>{{ $t }}</span></label>
                @endforeach
            </div>
            <label class="mw-label" for="about_own_words_{{ $mode }}" style="margin-top:14px;">In your own words</label>
            <textarea class="mw-field" id="about_own_words_{{ $mode }}" name="about_own_words" rows="3" maxlength="2000" placeholder="e.g. I haven't been sleeping since I lost my job, and I'm snapping at my family.">{{ $val('about_own_words', 'own_words') }}</textarea>

        @elseif ($section === 'mood')
            <div class="mw-step-question">How are you feeling right now?</div>
            <div class="mw-about-help">Just today — there's no right answer.</div>
            <div class="mw-chips" role="radiogroup" aria-label="Mood right now">
                @foreach (\App\Models\JournalEntry::MOODS as $v => $label)
                    <label class="mw-chip-opt"><input type="radio" name="about_mood" value="{{ $v }}" {{ (int) $val('about_mood', 'mood_now') === $v ? 'checked' : '' }}><span><i style="background:{{ $moodColors[$v] }};"></i>{{ $label }}</span></label>
                @endforeach
            </div>

        @elseif ($section === 'background')
            <div class="mw-step-question">A little background</div>
            <div class="mw-about-help">This helps your therapist start in the right place.</div>
            <div class="mw-label" style="margin-top:4px;">Have you had counselling or therapy before?</div>
            <div class="mw-chips" role="radiogroup" aria-label="Counselling before">
                @foreach ($IQ::COUNSELLING as $v => $label)
                    <label class="mw-chip-opt"><input type="radio" name="about_counselling" value="{{ $v }}" {{ $val('about_counselling', 'counselling_before') === $v ? 'checked' : '' }}><span>{{ $label }}</span></label>
                @endforeach
            </div>
            <div class="mw-label" style="margin-top:14px;">Are you taking any medication for your mental health?</div>
            <div class="mw-chips" role="radiogroup" aria-label="Medication">
                @foreach ($IQ::MEDICATION as $v => $label)
                    <label class="mw-chip-opt"><input type="radio" name="about_medication" value="{{ $v }}" {{ $val('about_medication', 'medication') === $v ? 'checked' : '' }}><span>{{ $label }}</span></label>
                @endforeach
            </div>
            <label class="mw-label" for="about_background_{{ $mode }}" style="margin-top:14px;">Anything else important we should know?</label>
            <textarea class="mw-field" id="about_background_{{ $mode }}" name="about_background" rows="3" maxlength="2000" placeholder="e.g. a recent loss, a big change, a health condition">{{ $val('about_background', 'background_notes') }}</textarea>

        @elseif ($section === 'goals')
            <div class="mw-step-question">What would you like to get out of therapy?</div>
            <div class="mw-about-help">Pick any that fit. You can change your mind later with your therapist.</div>
            <div class="mw-chips" role="group" aria-label="Goals">
                @foreach ($IQ::goalChoices($d) as $g)
                    <label class="mw-chip-opt"><input type="checkbox" name="about_goals[]" value="{{ $g }}" {{ in_array($g, $picked('about_goals', 'goals'), true) ? 'checked' : '' }}><span>{{ $g }}</span></label>
                @endforeach
            </div>
            <label class="mw-label" for="about_goals_text_{{ $mode }}" style="margin-top:14px;">In your own words</label>
            <textarea class="mw-field" id="about_goals_text_{{ $mode }}" name="about_goals_text" rows="3" maxlength="2000" placeholder="e.g. I want to sleep properly again and stop worrying all the time.">{{ $val('about_goals_text', 'goals_text') }}</textarea>

        @elseif ($section === 'preferences')
            <div class="mw-step-question">Any preferences?</div>
            <div class="mw-about-help">We'll share these with the team. We can't always promise them, but we'll try.</div>
            <div class="mw-label" style="margin-top:4px;">I'd feel most comfortable with</div>
            <div class="mw-chips" role="radiogroup" aria-label="Therapist preference">
                @foreach ($IQ::PREF_GENDER as $v => $label)
                    <label class="mw-chip-opt"><input type="radio" name="about_pref_gender" value="{{ $v }}" {{ $val('about_pref_gender', 'pref_therapist_gender') === $v ? 'checked' : '' }}><span>{{ $label }}</span></label>
                @endforeach
            </div>
            <div class="mw-label" style="margin-top:14px;">Sessions by</div>
            <div class="mw-chips" role="radiogroup" aria-label="Session type">
                @foreach ($IQ::PREF_MODE as $v => $label)
                    <label class="mw-chip-opt"><input type="radio" name="about_pref_mode" value="{{ $v }}" {{ $val('about_pref_mode', 'pref_mode') === $v ? 'checked' : '' }}><span>{{ $label }}</span></label>
                @endforeach
            </div>
            <label class="mw-label" for="about_pref_language_{{ $mode }}" style="margin-top:14px;">Language you'd like to speak in</label>
            <input class="mw-field" id="about_pref_language_{{ $mode }}" name="about_pref_language" maxlength="60" list="mw-lang-list" value="{{ $val('about_pref_language', 'pref_language') }}" placeholder="{{ filled($languageDefault ?? null) ? 'e.g. '.$languageDefault : 'e.g. English, Luganda' }}">
            <datalist id="mw-lang-list">@foreach ($IQ::LANGUAGE_SUGGESTIONS as $l)<option value="{{ $l }}">@endforeach</datalist>
        @endif

        @if ($mode === 'wizard')
            <div class="mw-about-actions">
                <button type="button" class="btn primary mw-about-next" style="border-radius:999px;">Continue</button>
                <button type="button" class="mw-about-skip" data-section="{{ $section }}">Skip this</button>
            </div>
        @endif
    </div>
@endforeach

@once
<style>
    .mw-about-help { font-size:13px; color:var(--ink-500, #5f6f6b); margin:-14px 0 16px; }
    .mw-chips { display:flex; flex-wrap:wrap; gap:8px; }
    .mw-chip-opt input { position:absolute; opacity:0; width:0; height:0; }
    .mw-chip-opt span { display:inline-flex; align-items:center; gap:7px; padding:9px 15px; border-radius:999px; border:1.5px solid rgba(16,48,43,.14); background:#fff; font-size:13.5px; font-weight:600; color:var(--ink-700, #3a4a47); cursor:pointer; transition:all .12s; }
    .mw-chip-opt span i { width:12px; height:12px; border-radius:50%; display:inline-block; }
    .mw-chip-opt span:hover { border-color:var(--teal-600, #2f6f66); }
    .mw-chip-opt input:checked + span { border-color:var(--teal-600, #2f6f66); background:var(--teal-600, #2f6f66); color:#fff; }
    .mw-chip-opt input:focus-visible + span { outline:2px solid var(--mw-orange, #E8825E); outline-offset:2px; }
    .mw-about-actions { display:flex; align-items:center; gap:16px; margin-top:22px; }
    .mw-about-skip { background:none; border:0; padding:6px 2px; font-size:13px; font-weight:700; color:var(--ink-500, #5f6f6b); cursor:pointer; text-decoration:underline; text-underline-offset:3px; }
    .mw-about-skip:hover { color:var(--ink-900, #1d2b29); }
    .mw-about-card { margin-bottom:14px; }
    .mw-about-card .mw-step-eyebrow { font-size:11px; font-weight:700; letter-spacing:.02em; color:var(--teal-600, #2f6f66); text-transform:uppercase; margin-bottom:8px; }
    .mw-about-card .mw-step-question { font-size:17px; font-weight:800; color:var(--ink-900, #1d2b29); margin-bottom:18px; }
</style>
@endonce

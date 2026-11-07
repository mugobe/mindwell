{{-- Step 3: fitness to practise and references. --}}
@php $a = fn ($k, $d = null) => old($k, $app->answer('fitness.'.$k, $d)); @endphp
<div class="ap-card">
    <h2>Declarations</h2>
    <p class="hint">Please answer honestly. A "yes" does not mean you can't join: tell us what happened and the team will talk it through with you. Not telling us about something we later find is treated far more seriously.</p>
    @foreach (config('onboarding.declarations') as $key => $question)
        @php $ans = old("declarations.$key.answer", $app->answer("fitness.declarations.$key.answer")); @endphp
        <div class="ap-decl">
            <div class="q">
                <span>{{ $question }}</span>
                <div class="ap-yn" role="radiogroup" aria-label="{{ $question }}">
                    <label class="yes">Yes<input type="radio" name="declarations[{{ $key }}][answer]" value="yes" @checked($ans === 'yes') onchange="document.getElementById('d-{{ $key }}').style.display=''"></label>
                    <label>No<input type="radio" name="declarations[{{ $key }}][answer]" value="no" @checked($ans === 'no') onchange="document.getElementById('d-{{ $key }}').style.display='none'"></label>
                </div>
            </div>
            <div id="d-{{ $key }}" style="margin-top:10px;{{ $ans === 'yes' ? '' : 'display:none;' }}">
                <label class="mw-label" for="det-{{ $key }}">Please give details: what happened, when, and the outcome</label>
                <textarea class="mw-field" id="det-{{ $key }}" name="declarations[{{ $key }}][details]" rows="3" maxlength="2000">{{ old("declarations.$key.details", $app->answer("fitness.declarations.$key.details")) }}</textarea>
            </div>
        </div>
    @endforeach
</div>

<div class="ap-card">
    <h2>Professional indemnity insurance</h2>
    <div class="ap-decl" style="border:0;padding:0;">
        <div class="q">
            <span>Do you have professional indemnity (malpractice) insurance?</span>
            <div class="ap-yn" role="radiogroup">
                <label>Yes<input type="radio" name="insurance" value="yes" @checked($a('insurance') === 'yes') onchange="document.getElementById('ins').style.display=''"></label>
                <label>No<input type="radio" name="insurance" value="no" @checked($a('insurance') === 'no') onchange="document.getElementById('ins').style.display='none'"></label>
            </div>
        </div>
    </div>
    <div id="ins" style="margin-top:14px;{{ $a('insurance') === 'yes' ? '' : 'display:none;' }}">
        <div class="ap-grid">
            <div>
                <label class="mw-label" for="insurer">Insurer <span class="ap-opt">(optional)</span></label>
                <input class="mw-field" id="insurer" name="insurer" value="{{ $a('insurer') }}" maxlength="160">
            </div>
            <div>
                <label class="mw-label" for="insurance_expiry">Cover ends <span class="ap-opt">(optional)</span></label>
                <input class="mw-field" type="date" id="insurance_expiry" name="insurance_expiry" value="{{ $a('insurance_expiry') }}">
            </div>
        </div>
        <div style="margin-top:12px;">@include('apply._file', ['kind' => 'insurance'])</div>
    </div>
</div>

<div class="ap-card">
    <h2>Two professional references</h2>
    <p class="hint">People who have seen your clinical work, such as a supervisor, manager or senior colleague. Not family or friends. We will only contact them about this application.</p>
    @for ($i = 0; $i < 2; $i++)
        <div style="{{ $i ? 'border-top:1px solid var(--mw-line);padding-top:16px;margin-top:16px;' : '' }}">
            <div style="font-weight:800;font-size:13px;color:var(--ink-700);margin-bottom:10px;">Reference {{ $i + 1 }}</div>
            <div class="ap-grid">
                <div>
                    <label class="mw-label" for="r{{ $i }}n">Full name</label>
                    <input class="mw-field" id="r{{ $i }}n" name="references[{{ $i }}][name]" value="{{ $a("references.$i.name") }}" maxlength="120">
                </div>
                <div>
                    <label class="mw-label" for="r{{ $i }}r">Their job title <span class="ap-opt">(optional)</span></label>
                    <input class="mw-field" id="r{{ $i }}r" name="references[{{ $i }}][role]" value="{{ $a("references.$i.role") }}" maxlength="120">
                </div>
                <div>
                    <label class="mw-label" for="r{{ $i }}o">Organisation <span class="ap-opt">(optional)</span></label>
                    <input class="mw-field" id="r{{ $i }}o" name="references[{{ $i }}][organisation]" value="{{ $a("references.$i.organisation") }}" maxlength="160">
                </div>
                <div>
                    <label class="mw-label" for="r{{ $i }}x">How they know you</label>
                    <input class="mw-field" id="r{{ $i }}x" name="references[{{ $i }}][relationship]" value="{{ $a("references.$i.relationship") }}" placeholder="e.g. My clinical supervisor, 2021 to 2024" maxlength="160">
                </div>
                <div>
                    <label class="mw-label" for="r{{ $i }}e">Email</label>
                    <input class="mw-field" type="email" id="r{{ $i }}e" name="references[{{ $i }}][email]" value="{{ $a("references.$i.email") }}" maxlength="160">
                    @error("references.$i.email")<div class="ap-err">{{ $message }}</div>@enderror
                </div>
                <div>
                    <label class="mw-label" for="r{{ $i }}p">Phone</label>
                    <input class="mw-field" type="tel" id="r{{ $i }}p" name="references[{{ $i }}][phone]" value="{{ $a("references.$i.phone") }}" maxlength="30">
                </div>
            </div>
        </div>
    @endfor
    <div class="ap-help" style="margin-top:10px;">An email or a phone number is needed for each reference.</div>
</div>

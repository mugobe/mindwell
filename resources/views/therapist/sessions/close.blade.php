@extends('layouts.app')
@section('title', 'Close Session')
@section('content')

{{-- 2026-10-08 redesign (round 3): page kit head with the client's bubble. --}}
<div class="mw-page-head">
    <div style="display:flex;align-items:center;gap:14px;">
        @include('partials.avatar', ['name' => $session->clientProfile?->user?->name ?? 'Client', 'size' => 46])
        <div>
            <h1>Close out · {{ $session->clientProfile?->user?->name ?? 'client' }}</h1>
            <div class="sub">{{ $session->scheduled_at?->format('l, j M Y · g:i A') }} · {{ $session->isInPerson() ? 'In person' : 'Video' }}{{ $session->clientProfile?->user?->clientLabel() ? ' · '.$session->clientProfile->user->clientLabel() : '' }}</div>
        </div>
    </div>
    <div class="actions">
        {{-- Added 2026-10-03: check-in scores since intake. --}}
        <a href="{{ route('therapist.clients.show', $session->client_profile_id) }}" class="btn ghost sm">Client file</a>
        <a href="{{ route('therapist.clients.progress', $session->client_profile_id) }}" class="btn ghost sm">Progress</a>
        <a href="{{ route('therapist.sessions.index') }}" class="btn ghost sm">Back to sessions</a>
    </div>
</div>

<div class="mw-close-layout">
    <div class="card mw-assessment-card">
        {{-- Richer intake (2026-10-09, roadmap 63): always visible, above the scores. --}}
        @if (\App\Models\ClientIntakeDetail::tableReady())
            <h2 style="font-size:15px;margin:0 0 10px;">What they told us</h2>
            @include('partials.about-client', ['profileId' => $session->client_profile_id, 'compact' => true])
            <div style="border-top:1px solid rgba(16,48,43,.08);margin:16px 0;"></div>
        @endif
        <div style="display:flex;align-items:center;justify-content:space-between;cursor:pointer;" onclick="mwToggleAssessment()">
            <h2 style="font-size:15px;margin:0;">Client's intake assessment</h2>
            <span id="mw-assessment-caret" style="font-size:12px;color:var(--ink-500);">Show ▾</span>
        </div>

        <div id="mw-assessment-body" style="display:none;margin-top:16px;">
            @if (! $intake)
                <p style="font-size:12.5px;color:var(--ink-500);">
                    No intake assessment on file for this client — this session was booked directly rather than
                    through the intake flow.
                </p>
            @else
                <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:center;margin-bottom:16px;">
                    @php
                        $riskPill = match ($intake->risk_level) {
                            'crisis', 'high' => 'danger',
                            'moderate' => 'warn',
                            default => 'success',
                        };
                    @endphp
                    <span class="pill {{ $riskPill }}">Risk: {{ ucfirst($intake->risk_level ?? 'unknown') }}</span>
                    <span class="pill teal">PHQ-9: {{ $intake->phq9_score ?? '—' }}</span>
                    <span class="pill teal">GAD-7: {{ $intake->gad7_score ?? '—' }}</span>
                    <span style="font-size:11.5px;color:var(--ink-300);">
                        Taken {{ $intake->created_at?->format('M j, Y') ?? '—' }}
                    </span>
                </div>

                @if ($intakeResponses)
                    <div style="margin-bottom:16px;">
                        <div class="mw-label" style="margin-bottom:6px;">PHQ-9</div>
                        <div style="display:flex;flex-direction:column;gap:2px;">
                            @foreach ($phq9Items as $num => $label)
                                <div style="display:flex;justify-content:space-between;gap:12px;font-size:12.5px;padding:6px 0;border-bottom:1px solid rgba(0,0,0,0.06);">
                                    <span style="color:var(--ink-700);flex:1;">{{ $label }}</span>
                                    <span style="color:var(--ink-900);font-weight:600;white-space:nowrap;">
                                        {{ $answerOptions[$intakeResponses['phq9'][$num] ?? null] ?? '—' }}
                                    </span>
                                </div>
                            @endforeach
                        </div>
                    </div>

                    <div>
                        <div class="mw-label" style="margin-bottom:6px;">GAD-7</div>
                        <div style="display:flex;flex-direction:column;gap:2px;">
                            @foreach ($gad7Items as $num => $label)
                                <div style="display:flex;justify-content:space-between;gap:12px;font-size:12.5px;padding:6px 0;border-bottom:1px solid rgba(0,0,0,0.06);">
                                    <span style="color:var(--ink-700);flex:1;">{{ $label }}</span>
                                    <span style="color:var(--ink-900);font-weight:600;white-space:nowrap;">
                                        {{ $answerOptions[$intakeResponses['gad7'][$num] ?? null] ?? '—' }}
                                    </span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @else
                    <p style="font-size:12.5px;color:var(--ink-500);">Intake found, but the answer detail couldn't be read.</p>
                @endif
            @endif
        </div>
    </div>

    <div class="card mw-form-card">
        {{-- Added 2026-09-29: only journal entries the client explicitly chose to share. --}}
        @if ($sharedNotes->isNotEmpty())
            <div style="background:var(--mw-teal-tint, #DCEDE8);border-radius:12px;padding:14px 16px;margin-bottom:20px;">
                <div style="font-size:13.5px;font-weight:800;color:var(--ink-900);">Notes {{ $session->clientProfile?->user?->name ?? 'your client' }} shared with you</div>
                <div style="font-size:11px;color:var(--ink-500);margin:2px 0 10px;">From their private journal — they chose to share these.</div>
                <div style="display:flex;flex-direction:column;gap:10px;max-height:260px;overflow:auto;">
                    @foreach ($sharedNotes as $note)
                        <div style="border-left:3px solid var(--mw-teal-dot, #6FA99B);padding:2px 0 2px 10px;">
                            <div style="font-size:11px;color:var(--ink-500);font-weight:700;margin-bottom:2px;">
                                {{ $note->therapy_session_id === $session->id ? 'During this session' : ($note->kind === 'session' ? 'During an earlier session' : 'Journal') }}
                                · {{ $note->created_at?->format('M j, g:i A') }}
                                @if ($note->moodLabel())
                                    · feeling {{ strtolower($note->moodLabel()) }}
                                @endif
                            </div>
                            <div style="font-size:12.5px;color:var(--ink-700);white-space:pre-line;">{{ $note->body }}</div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        <p style="font-size:12.5px;color:var(--ink-500);margin-bottom:20px;">
            Scheduled for {{ $session->scheduled_at?->format('M j, Y g:i A') ?? '—' }}. Once you close this out it
            moves to "completed" and this client's next dashboard visit will offer to book another session with you.
        </p>

        @if ($errors->any())
            <div class="mw-alert danger">
                <ul style="margin:0;padding-left:16px;">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('therapist.sessions.store', $session) }}" id="mw-close-form" style="display:flex;flex-direction:column;gap:18px;">
            @csrf

            <div>
                <label class="mw-label" for="closure_outcome">Outcome / recommendation</label>
                <select class="mw-field" id="closure_outcome" name="closure_outcome" required onchange="mwToggleFollowUp(this.value)">
                    <option value="">Choose…</option>
                    @foreach ($outcomes as $value => $label)
                        <option value="{{ $value }}" {{ old('closure_outcome') === $value ? 'selected' : '' }}>{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            @php
                $oldFollowUpAt = old('follow_up_at');
                $oldFollowUpLabel = null;
                if ($oldFollowUpAt) {
                    try {
                        $oldFollowUpLabel = \Illuminate\Support\Carbon::parse($oldFollowUpAt)->format('l, M j \a\t g:i A');
                    } catch (\Throwable $e) {
                        $oldFollowUpLabel = null;
                    }
                }
                $anySlotsAtAll = $calendarDays->contains(fn ($d) => $d['slots']->isNotEmpty());
            @endphp

            <div id="mw-followup-field" style="{{ old('closure_outcome') === 'schedule_next_session' ? '' : 'display:none;' }}">
                <label class="mw-label">Follow-up time</label>

                @if (! $anySlotsAtAll)
                    <p style="font-size:12.5px;color:var(--ink-500);margin:4px 0 0;">
                        You don't have any open slots in the next 7 days — add availability first, or come back to
                        close this out once you do.
                    </p>
                @else
                    <div class="mw-calendar-strip">
                        @foreach ($calendarDays as $day)
                            <button type="button"
                                class="mw-calendar-day {{ $day['slots']->isEmpty() ? 'empty' : '' }}"
                                data-date="{{ $day['date'] }}"
                                {{ $day['slots']->isEmpty() ? 'disabled' : '' }}
                                onclick="mwOpenCalendarPopup('{{ $day['date'] }}')">
                                <span class="mw-calendar-weekday">{{ $day['weekday'] }}</span>
                                <span class="mw-calendar-daynum">{{ $day['day'] }}</span>
                                <span class="mw-calendar-dot"></span>
                            </button>
                        @endforeach
                    </div>

                    <p id="mw-followup-summary" style="font-size:12.5px;color:var(--ink-700);margin:10px 0 0;{{ $oldFollowUpLabel ? '' : 'display:none;' }}">
                        Follow-up set for <strong id="mw-followup-summary-text">{{ $oldFollowUpLabel }}</strong>
                        — <a href="#" onclick="event.preventDefault(); mwReopenSelected();">change</a>
                    </p>
                    <p id="mw-followup-error" style="font-size:12px;color:#c0392b;margin:8px 0 0;display:none;"></p>
                    <p style="font-size:11.5px;color:var(--ink-300);margin:6px 0 0;">
                        Click a day above to pick a time — this books the follow-up right away. It still needs
                        admin/routing to approve it before the client sees it as confirmed.
                    </p>
                @endif

                @if ($session->therapistProfile?->offers_in_person)
                    {{-- In-person (2026-10-07): only if admin has switched it on for you. --}}
                    <label class="mw-label" for="follow_up_mode" style="margin-top:12px;">Follow-up will be</label>
                    <select class="mw-field" id="follow_up_mode" name="follow_up_mode" style="max-width:240px;">
                        @foreach (\App\Models\TherapySession::MODES as $mwMode => $mwModeLabel)
                            <option value="{{ $mwMode }}" {{ old('follow_up_mode', $session->session_mode ?: 'video') === $mwMode ? 'selected' : '' }}>{{ $mwModeLabel }}</option>
                        @endforeach
                    </select>
                @endif
                <input type="hidden" id="follow_up_at" name="follow_up_at" value="{{ $oldFollowUpAt }}">
                <input type="hidden" id="follow_up_notes" name="follow_up_notes" value="{{ old('follow_up_notes') }}">
            </div>

            <div class="mw-report-grid">
                <div>
                    <label class="mw-label" for="presenting_concerns">Presenting concerns</label>
                    <textarea class="mw-field" id="presenting_concerns" name="presenting_concerns" rows="4" placeholder="What the client brought to this session." required>{{ old('presenting_concerns') }}</textarea>
                </div>
                <div>
                    <label class="mw-label" for="risk_assessment">Risk assessment</label>
                    <textarea class="mw-field" id="risk_assessment" name="risk_assessment" rows="4" placeholder="Current risk level and reasoning — state explicitly if there is none." required>{{ old('risk_assessment') }}</textarea>
                </div>
                <div class="mw-span-2">
                    <label class="mw-label" for="session_summary">Session summary</label>
                    <textarea class="mw-field" id="session_summary" name="session_summary" rows="{{ $session->therapist_live_notes ? 7 : 4 }}" placeholder="What happened in the session — topics covered, interventions used." required>{{ old('session_summary', $session->therapist_live_notes) }}</textarea>
                    @if ($session->therapist_live_notes && ! old('session_summary'))
                        <div style="font-size:11px;color:var(--ink-300);margin-top:4px;">Pre-filled from the notes you took during the video call — edit freely.</div>
                    @endif
                </div>
                <div>
                    <label class="mw-label" for="progress_notes">Progress since last visit</label>
                    <textarea class="mw-field" id="progress_notes" name="progress_notes" rows="3" placeholder="Any change since the previous session (or baseline, if this was the first)." required>{{ old('progress_notes') }}</textarea>
                </div>
                <div>
                    <label class="mw-label" for="plan_notes">Plan / next steps</label>
                    <textarea class="mw-field" id="plan_notes" name="plan_notes" rows="3" placeholder="What happens next, and why this outcome was chosen." required>{{ old('plan_notes') }}</textarea>
                </div>
                {{-- Added 2026-10-06 (Dr. Ntabade's THERAPIST DRAFT). Optional. --}}
                <div>
                    <label class="mw-label" for="interventions_used">Interventions used <span style="font-weight:400;color:var(--ink-300);">(optional)</span></label>
                    <textarea class="mw-field" id="interventions_used" name="interventions_used" rows="3" placeholder="e.g. CBT thought record, grounding, psychoeducation on sleep.">{{ old('interventions_used') }}</textarea>
                </div>
                <div>
                    <label class="mw-label" for="homework">Homework / activities given <span style="font-weight:400;color:var(--ink-300);">(optional)</span></label>
                    <textarea class="mw-field" id="homework" name="homework" rows="3" placeholder="What the client agreed to try before next time.">{{ old('homework') }}</textarea>
                </div>
                <div class="mw-span-2">
                    <label class="mw-label" for="goals_progress">Progress toward goals <span style="font-weight:400;color:var(--ink-300);">(optional)</span></label>
                    <textarea class="mw-field" id="goals_progress" name="goals_progress" rows="2" placeholder="e.g. Sleep: 5 of 7 nights in bed by 11pm. Anxiety: using breathing exercise most days.">{{ old('goals_progress') }}</textarea>
                </div>
            </div>
            <div style="font-size:11.5px;color:var(--ink-500);margin:-4px 0 12px;">The session number is filled in automatically when you close the session.</div>

            <button type="submit" class="btn primary block" style="margin-top:6px;">Close session</button>
        </form>
    </div>
</div>

<p style="font-size:11px;color:var(--ink-300);margin-top:14px;">
    Admin can see this report under Session Reports once it's saved. Choosing "Refer out" sends the case to
    the routing team's referral queue to assign a facility — you don't pick one yourself. None of this is
    shown to the client.
</p>

<div id="mw-calendar-popup-overlay" class="mw-popup-overlay" style="display:none;" onclick="if(event.target===this) mwCloseCalendarPopup()">
    <div class="mw-popup-box">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px;">
            <h3 id="mw-popup-date-label" style="margin:0;font-size:15px;"></h3>
            <button type="button" class="btn ghost sm" onclick="mwCloseCalendarPopup()">✕</button>
        </div>

        <div class="mw-label" style="margin-bottom:6px;">Pick a time</div>
        <div id="mw-popup-times" style="display:flex;flex-wrap:wrap;gap:8px;margin-bottom:16px;"></div>

        <label class="mw-label" for="mw-popup-notes">Notes for this follow-up (optional)</label>
        <textarea class="mw-field" id="mw-popup-notes" rows="3" placeholder="What to focus on next time — for you and admin, not shown to the client."></textarea>

        <div style="display:flex;gap:8px;margin-top:16px;">
            <button type="button" class="btn primary" id="mw-popup-confirm" disabled onclick="mwConfirmCalendarPopup()">Confirm time</button>
            <button type="button" class="btn ghost" onclick="mwCloseCalendarPopup()">Cancel</button>
        </div>
    </div>
</div>

<style>
.mw-close-layout {
    display: grid;
    grid-template-columns: 340px 1fr;
    gap: 22px;
    align-items: start;
}
@media (max-width: 900px) {
    .mw-close-layout {
        grid-template-columns: 1fr;
    }
}
.mw-assessment-card {
    position: sticky;
    top: 16px;
}
@media (max-width: 900px) {
    .mw-assessment-card {
        position: static;
    }
}
.mw-calendar-strip {
    display: grid;
    grid-template-columns: repeat(7, 1fr);
    gap: 8px;
    margin-top: 6px;
}
.mw-calendar-day {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 4px;
    padding: 10px 4px;
    border: 1px solid rgba(0,0,0,0.1);
    border-radius: 10px;
    background: #fff;
    cursor: pointer;
    font-family: inherit;
}
.mw-calendar-day:hover:not(:disabled) {
    border-color: var(--teal-600, #1f7a6c);
}
.mw-calendar-day.empty,
.mw-calendar-day:disabled {
    opacity: 0.35;
    cursor: not-allowed;
}
.mw-calendar-day.selected {
    border-color: var(--teal-600, #1f7a6c);
    background: rgba(31,122,108,0.08);
}
.mw-calendar-weekday {
    font-size: 10.5px;
    text-transform: uppercase;
    color: var(--ink-500, #777);
    letter-spacing: 0.03em;
}
.mw-calendar-daynum {
    font-size: 16px;
    font-weight: 700;
    color: var(--ink-900, #222);
}
.mw-calendar-dot {
    width: 5px;
    height: 5px;
    border-radius: 50%;
    background: var(--coral-500, #e8734a);
}
.mw-calendar-day.empty .mw-calendar-dot,
.mw-calendar-day:disabled .mw-calendar-dot {
    visibility: hidden;
}
.mw-report-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 16px;
}
.mw-report-grid .mw-span-2 {
    grid-column: 1 / -1;
}
@media (max-width: 640px) {
    .mw-report-grid {
        grid-template-columns: 1fr;
    }
    .mw-report-grid .mw-span-2 {
        grid-column: auto;
    }
}
.mw-popup-overlay {
    position: fixed;
    inset: 0;
    background: rgba(20,20,20,0.45);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 1000;
    padding: 16px;
}
.mw-popup-box {
    background: #fff;
    border-radius: 14px;
    padding: 22px;
    max-width: 420px;
    width: 100%;
    box-shadow: 0 20px 50px rgba(0,0,0,0.25);
}
.mw-popup-time-btn {
    padding: 8px 12px;
    border-radius: 8px;
    border: 1px solid rgba(0,0,0,0.12);
    background: #fff;
    font-size: 12.5px;
    cursor: pointer;
    font-family: inherit;
}
.mw-popup-time-btn.selected {
    background: var(--teal-600, #1f7a6c);
    border-color: var(--teal-600, #1f7a6c);
    color: #fff;
}
</style>

<script>
const mwCalendarData = @json($calendarDays->keyBy('date'));
let mwSelectedDate = null;
let mwSelectedTime = null;

function mwToggleAssessment() {
    var body = document.getElementById('mw-assessment-body');
    var caret = document.getElementById('mw-assessment-caret');
    var show = body.style.display === 'none';
    body.style.display = show ? '' : 'none';
    caret.textContent = show ? 'Hide ▴' : 'Show ▾';
}

function mwToggleFollowUp(value) {
    var field = document.getElementById('mw-followup-field');
    if (field) {
        field.style.display = value === 'schedule_next_session' ? '' : 'none';
    }
}
var mwOutcomeEl = document.getElementById('closure_outcome');
if (mwOutcomeEl) {
    mwToggleFollowUp(mwOutcomeEl.value);
}

function mwOpenCalendarPopup(dateKey) {
    var day = mwCalendarData[dateKey];
    if (!day || !day.slots.length) return;

    mwSelectedDate = dateKey;
    mwSelectedTime = null;

    document.getElementById('mw-popup-date-label').textContent = day.fullLabel;

    var timesWrap = document.getElementById('mw-popup-times');
    timesWrap.innerHTML = '';
    day.slots.forEach(function (slot) {
        var btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'mw-popup-time-btn';
        btn.textContent = slot.label;
        btn.onclick = function () {
            mwSelectedTime = slot.iso;
            Array.from(timesWrap.children).forEach(function (c) { c.classList.remove('selected'); });
            btn.classList.add('selected');
            document.getElementById('mw-popup-confirm').disabled = false;
        };
        timesWrap.appendChild(btn);
    });

    document.getElementById('mw-popup-notes').value = document.getElementById('follow_up_notes').value || '';
    document.getElementById('mw-popup-confirm').disabled = true;
    document.getElementById('mw-calendar-popup-overlay').style.display = 'flex';

    document.querySelectorAll('.mw-calendar-day').forEach(function (el) {
        el.classList.toggle('selected', el.dataset.date === dateKey);
    });
}

function mwReopenSelected() {
    var current = document.getElementById('follow_up_at').value;
    if (current) {
        mwOpenCalendarPopup(current.split(' ')[0]);
    }
}

function mwCloseCalendarPopup() {
    document.getElementById('mw-calendar-popup-overlay').style.display = 'none';
}

function mwConfirmCalendarPopup() {
    if (!mwSelectedTime) return;

    document.getElementById('follow_up_at').value = mwSelectedTime;
    document.getElementById('follow_up_notes').value = document.getElementById('mw-popup-notes').value;

    var day = mwCalendarData[mwSelectedDate];
    var slot = day.slots.find(function (s) { return s.iso === mwSelectedTime; });
    var summary = document.getElementById('mw-followup-summary');
    if (summary) {
        document.getElementById('mw-followup-summary-text').textContent = day.fullLabel + ' at ' + (slot ? slot.label : '');
        summary.style.display = '';
    }
    var errorEl = document.getElementById('mw-followup-error');
    if (errorEl) {
        errorEl.style.display = 'none';
    }

    mwCloseCalendarPopup();
}

var mwCloseForm = document.getElementById('mw-close-form');
if (mwCloseForm) {
    mwCloseForm.addEventListener('submit', function (e) {
        var outcome = document.getElementById('closure_outcome').value;
        var followUpAt = document.getElementById('follow_up_at').value;
        var errorEl = document.getElementById('mw-followup-error');
        if (outcome === 'schedule_next_session' && !followUpAt && errorEl) {
            e.preventDefault();
            errorEl.textContent = 'Pick a follow-up time from the calendar before closing this session.';
            errorEl.style.display = '';
        }
    });
}
</script>
@endsection

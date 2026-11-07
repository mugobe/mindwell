{{-- Calendar grid (2026-10-08, roadmap 75). Shared by the therapist and admin calendars.
     Needs: $cal (App\Services\CalendarBuilder::build), $calRoute (route name), $calQuery
     (extra query params to keep, e.g. ['therapist' => id]), $focus (selected event id or null).
     2026-11-03 staff refresh: blocks show the time, the client's short name and video/in
     person so nothing is cut off (full details on hover and in the side panel). Optional
     $calTherapists: when the admin sees every therapist at once, each therapist gets their
     own colour stripe and a legend. --}}
@php
    $hourH = 56;
    $mwPalette = ['#034C8F', '#00A87C', '#7B5CC4', '#EE7800', '#1C64A8', '#B7791F', '#0F8B8D', '#9B4F96'];
    $mwByTherapist = isset($calTherapists) && count($calTherapists) > 1 && empty($calQuery['therapist']);
    $mwTColour = [];
    if ($mwByTherapist) {
        foreach (collect($calTherapists)->values() as $i => $t) {
            $mwTColour[$t->id] = $mwPalette[$i % count($mwPalette)];
        }
    }
    $mwShort = function (?string $name) {
        $p = preg_split('/\s+/', trim((string) $name));
        return trim(($p[0] ?? 'Client').(isset($p[1]) ? ' '.mb_substr($p[1], 0, 1).'.' : ''));
    };
    $hStart = $cal['hourStart'];
    $hEnd = $cal['hourEnd'];
    $gridH = ($hEnd - $hStart) * $hourH;
    $link = fn (array $extra) => route($calRoute, array_merge($calQuery, ['view' => $cal['view'], 'date' => $cal['anchor']->toDateString()], $extra));
    $now = now();
@endphp
<style>
    .mw-cal-bar { display:flex; align-items:center; justify-content:space-between; gap:10px; flex-wrap:wrap; margin-bottom:14px; }
    .mw-cal-bar .nav { display:flex; align-items:center; gap:6px; }
    .mw-cal-bar .title { font-size:16px; font-weight:800; color:var(--ink-900); margin:0 6px; }
    .mw-cal-seg { display:inline-flex; border:1px solid var(--mw-line, #E3E9F2); background:#fff; border-radius:10px; overflow:hidden; }
    .mw-cal-seg a { padding:7px 14px; font-size:12.5px; font-weight:700; color:var(--ink-500); text-decoration:none; }
    .mw-cal-seg a.on { background:#034C8F; color:#fff; }
    .mw-cal-scroll { overflow-x:auto; }
    .mw-cal-time { display:grid; min-width:640px; }
    .mw-cal-head { font-size:11.5px; font-weight:700; color:var(--ink-500); text-align:center; padding:8px 4px; border-bottom:1px solid rgba(0,0,0,.08); }
    .mw-cal-head.today { color:#034C8F; }
    .mw-cal-head .n { display:block; font-size:17px; color:var(--ink-900); }
    .mw-cal-head.today .n { color:#fff; background:#034C8F; border-radius:50%; width:30px; height:30px; line-height:30px; margin:2px auto 0; }
    .mw-cal-hours { position:relative; }
    .mw-cal-hours div { position:absolute; right:6px; font-size:10.5px; color:var(--ink-300); transform:translateY(-6px); }
    .mw-cal-col { position:relative; border-left:1px solid rgba(0,0,0,.06); background-image:repeating-linear-gradient(to bottom, transparent 0, transparent {{ $hourH - 1 }}px, rgba(0,0,0,.06) {{ $hourH - 1 }}px, rgba(0,0,0,.06) {{ $hourH }}px); }
    .mw-cal-col.today { background-color:#F6F9FD; }
    .mw-cal-ev { position:absolute; box-sizing:border-box; padding:4px 7px; border-radius:9px; font-size:11.5px; line-height:1.3; overflow:hidden; text-decoration:none; color:var(--ink-900); border-left:4px solid; }
    .mw-cal-ev .tm { font-weight:800; font-size:10.5px; font-variant-numeric:tabular-nums; }
    .mw-cal-ev .t { font-weight:800; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
    .mw-cal-ev .s { color:var(--ink-500); font-size:10.5px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
    .mw-cal-ev .s .flag { color:#8A5A10; font-weight:700; }
    .mw-cal-ev.focus { box-shadow:0 0 0 2px #034C8F; z-index:3; }
    .mw-cal-ev:hover { z-index:2; box-shadow:0 6px 16px -8px rgba(3,31,63,.35); }
    .tone-video { background:#E3EDF8; border-color:#034C8F; }
    .tone-inperson { background:#DDF4EC; border-color:#00A87C; }
    .tone-waiting { background:#FBF0D9; border:1.5px dashed #B7791F !important; border-left-width:4px !important; }
    .tone-changing { background:#FFEBD6; border-color:#EE7800; }
    .tone-done { background:#EEF1F5; border-color:#9AA6B8; color:var(--ink-500); opacity:.75; }
    .tone-off { background:repeating-linear-gradient(135deg, #e9ebea 0 6px, #f4f5f4 6px 12px); border-color:#5f6f6b; }
    .tone-off-pending { background:repeating-linear-gradient(135deg, #fbefcf 0 6px, #fdf7e6 6px 12px); border-color:#a0740c; border-left-style:dashed; }
    .mw-cal-now { position:absolute; left:0; right:0; height:2px; background:#EE7800; z-index:4; }
    .mw-cal-now::before { content:''; position:absolute; left:-5px; top:-4px; width:10px; height:10px; border-radius:50%; background:#EE7800; }
    .mw-cal-month { display:grid; grid-template-columns:repeat(7, minmax(0,1fr)); min-width:640px; }
    .mw-cal-cell { min-height:104px; border-top:1px solid rgba(0,0,0,.06); border-left:1px solid rgba(0,0,0,.06); padding:6px; }
    .mw-cal-cell.out { background:rgba(0,0,0,.025); }
    .mw-cal-cell .d { font-size:12px; font-weight:800; color:var(--ink-700); text-decoration:none; }
    .mw-cal-cell.today .d { color:#fff; background:#034C8F; border-radius:999px; padding:1px 7px; }
    .mw-cal-chip { display:block; margin-top:4px; padding:2px 6px; border-radius:6px; font-size:11px; text-decoration:none; color:var(--ink-900); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; border-left:3px solid; }
    .mw-cal-legend { display:flex; gap:12px; flex-wrap:wrap; margin-top:10px; font-size:11px; color:var(--ink-500); }
    .mw-cal-legend span i { display:inline-block; width:10px; height:10px; border-radius:3px; margin-right:4px; vertical-align:-1px; border-left:3px solid; }
</style>

<div class="mw-cal-bar">
    <div class="nav">
        <a href="{{ route($calRoute, array_merge($calQuery, ['view' => $cal['view'], 'date' => $cal['prev']->toDateString()])) }}" class="btn ghost sm" aria-label="Previous">‹</a>
        <a href="{{ route($calRoute, array_merge($calQuery, ['view' => $cal['view'], 'date' => now()->toDateString()])) }}" class="btn ghost sm">Today</a>
        <a href="{{ route($calRoute, array_merge($calQuery, ['view' => $cal['view'], 'date' => $cal['next']->toDateString()])) }}" class="btn ghost sm" aria-label="Next">›</a>
        <span class="title">{{ $cal['title'] }}</span>
    </div>
    <div class="mw-cal-seg">
        @foreach (['day' => 'Day', 'week' => 'Week', 'month' => 'Month'] as $v => $label)
            <a href="{{ route($calRoute, array_merge($calQuery, ['view' => $v, 'date' => $cal['anchor']->toDateString()])) }}" class="{{ $cal['view'] === $v ? 'on' : '' }}">{{ $label }}</a>
        @endforeach
    </div>
</div>

<div class="card" style="padding:0;">
    <div class="mw-cal-scroll">
    @if ($cal['view'] === 'month')
        <div class="mw-cal-month">
            @foreach (['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'] as $wd)
                <div class="mw-cal-head">{{ $wd }}</div>
            @endforeach
            @foreach ($cal['days'] as $day)
                <div class="mw-cal-cell {{ $day['inMonth'] ? '' : 'out' }} {{ $day['date']->isToday() ? 'today' : '' }}">
                    <a class="d" href="{{ route($calRoute, array_merge($calQuery, ['view' => 'day', 'date' => $day['date']->toDateString()])) }}">{{ $day['date']->format('j') }}</a>
                    @foreach ($day['events']->take(3) as $ev)
                        <a class="mw-cal-chip tone-{{ $ev['tone'] }}" href="{{ $link(['focus' => $ev['id']]) }}" title="{{ $ev['title'] }}">
                            {{ $ev['kind'] === 'session' ? $ev['start']->format('g:i') : '' }} {{ $ev['title'] }}
                        </a>
                    @endforeach
                    @if ($day['events']->count() > 3)
                        <a class="mw-cal-chip" style="border-left-color:transparent;color:var(--ink-500);" href="{{ route($calRoute, array_merge($calQuery, ['view' => 'day', 'date' => $day['date']->toDateString()])) }}">+{{ $day['events']->count() - 3 }} more</a>
                    @endif
                </div>
            @endforeach
        </div>
    @else
        @php $cols = $cal['days']->count(); @endphp
        <div class="mw-cal-time" style="grid-template-columns:52px repeat({{ $cols }}, minmax(0,1fr));{{ $cols === 1 ? 'min-width:0;' : '' }}">
            <div class="mw-cal-head"></div>
            @foreach ($cal['days'] as $day)
                <div class="mw-cal-head {{ $day['date']->isToday() ? 'today' : '' }}">
                    {{ $day['date']->format('D') }}<span class="n">{{ $day['date']->format('j') }}</span>
                </div>
            @endforeach

            <div class="mw-cal-hours" style="height:{{ $gridH }}px;">
                @for ($h = $hStart + 1; $h < $hEnd; $h++)
                    <div style="top:{{ ($h - $hStart) * $hourH }}px;">{{ \Illuminate\Support\Carbon::createFromTime($h)->format('g A') }}</div>
                @endfor
            </div>
            @foreach ($cal['days'] as $day)
                <div class="mw-cal-col {{ $day['date']->isToday() ? 'today' : '' }}" style="height:{{ $gridH }}px;">
                    @foreach ($day['events'] as $ev)
                        @php
                            $dayTop = $day['date']->copy()->setTime($hStart, 0);
                            $dayBottom = $day['date']->copy()->setTime($hEnd, 0);
                            $s = $ev['start']->lt($dayTop) ? $dayTop : $ev['start'];
                            $e = $ev['end']->gt($dayBottom) ? $dayBottom : $ev['end'];
                            $top = max(0, $dayTop->diffInMinutes($s, false)) / 60 * $hourH;
                            $height = max(22, $s->diffInMinutes($e, false) / 60 * $hourH);
                            $w = 100 / $ev['lanes'];
                        @endphp
                        @if ($e->gt($dayTop) && $s->lt($dayBottom))
                            @php
                                $mwSes = $ev['kind'] === 'session' ? ($ev['session'] ?? null) : null;
                                $mwStripe = $mwSes && $mwByTherapist ? ($mwTColour[$mwSes->therapist_profile_id] ?? null) : null;
                                $mwTip = $ev['start']->format('g:i A').' · '.$ev['title'].' · '.$ev['sub'].($ev['pendingChange'] ? ' · change requested' : '');
                            @endphp
                            <a class="mw-cal-ev tone-{{ $ev['tone'] }} {{ $focus === $ev['id'] ? 'focus' : '' }}" href="{{ $link(['focus' => $ev['id']]) }}" title="{{ $mwTip }}"
                               style="top:{{ $top }}px;height:{{ $height - 2 }}px;left:calc({{ $ev['lane'] * $w }}% + 2px);width:calc({{ $w }}% - 4px);{{ $mwStripe ? 'border-left-color:'.$mwStripe.';' : '' }}">
                                @if ($mwSes)
                                    <div class="tm">{{ $ev['start']->format('g:i') }}</div>
                                    <div class="t">{{ $mwShort($mwSes->clientProfile?->user?->name) }}</div>
                                    <div class="s">{{ $mwSes->isInPerson() ? 'In person' : 'Video' }}@if ($ev['tone'] === 'waiting') · <span class="flag">Awaiting</span>@elseif ($ev['pendingChange']) · <span class="flag">Change asked</span>@endif</div>
                                @else
                                    <div class="t">{{ $ev['title'] }}</div>
                                    <div class="s">{{ $ev['sub'] }}</div>
                                @endif
                            </a>
                        @endif
                    @endforeach
                    @if ($day['date']->isToday() && $now->hour >= $hStart && $now->hour < $hEnd)
                        <div class="mw-cal-now" style="top:{{ (($now->hour - $hStart) * 60 + $now->minute) / 60 * $hourH }}px;"></div>
                    @endif
                </div>
            @endforeach
        </div>
    @endif
    </div>
</div>

<div class="mw-cal-legend">
    @if ($mwByTherapist)
        @foreach (collect($calTherapists)->values() as $t)
            <span style="font-weight:700;color:var(--ink-700);"><i style="background:{{ $mwTColour[$t->id] }};border-left-color:{{ $mwTColour[$t->id] }};"></i>{{ $t->user?->name }}</span>
        @endforeach
        <span style="width:100%;height:0;"></span>
    @endif
    <span><i class="tone-video"></i>Video</span>
    <span><i class="tone-inperson"></i>In person</span>
    <span><i class="tone-waiting"></i>Waiting for approval</span>
    <span><i class="tone-changing"></i>Change requested</span>
    <span><i class="tone-done"></i>Completed</span>
    <span><i class="tone-off"></i>Time off</span>
    <span><i class="tone-off-pending"></i>Time off (waiting)</span>
</div>

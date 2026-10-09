{{-- Calendar grid (2026-10-08, roadmap 75). Shared by the therapist and admin calendars.
     Needs: $cal (App\Services\CalendarBuilder::build), $calRoute (route name), $calQuery
     (extra query params to keep, e.g. ['therapist' => id]), $focus (selected event id or null). --}}
@php
    $hourH = 48;
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
    .mw-cal-seg { display:inline-flex; border:1.5px solid var(--teal-600, #2f6f66); border-radius:999px; overflow:hidden; }
    .mw-cal-seg a { padding:6px 14px; font-size:12.5px; font-weight:700; color:var(--teal-600, #2f6f66); text-decoration:none; }
    .mw-cal-seg a.on { background:var(--teal-600, #2f6f66); color:#fff; }
    .mw-cal-scroll { overflow-x:auto; }
    .mw-cal-time { display:grid; min-width:640px; }
    .mw-cal-head { font-size:11.5px; font-weight:700; color:var(--ink-500); text-align:center; padding:8px 4px; border-bottom:1px solid rgba(0,0,0,.08); }
    .mw-cal-head.today { color:var(--teal-600, #2f6f66); }
    .mw-cal-head .n { display:block; font-size:17px; color:var(--ink-900); }
    .mw-cal-head.today .n { color:var(--teal-600, #2f6f66); }
    .mw-cal-hours { position:relative; }
    .mw-cal-hours div { position:absolute; right:6px; font-size:10.5px; color:var(--ink-300); transform:translateY(-6px); }
    .mw-cal-col { position:relative; border-left:1px solid rgba(0,0,0,.06); background-image:repeating-linear-gradient(to bottom, transparent 0, transparent {{ $hourH - 1 }}px, rgba(0,0,0,.06) {{ $hourH - 1 }}px, rgba(0,0,0,.06) {{ $hourH }}px); }
    .mw-cal-col.today { background-color:rgba(47,111,102,.035); }
    .mw-cal-ev { position:absolute; box-sizing:border-box; padding:4px 6px; border-radius:8px; font-size:11.5px; line-height:1.3; overflow:hidden; text-decoration:none; color:var(--ink-900); border-left:3px solid; }
    .mw-cal-ev .t { font-weight:800; }
    .mw-cal-ev .s { color:var(--ink-700); font-size:10.5px; }
    .mw-cal-ev.focus { box-shadow:0 0 0 2px var(--ink-900); z-index:3; }
    .tone-video { background:#DCEDE8; border-color:#2f6f66; }
    .tone-inperson { background:#DEE8CE; border-color:#5b7a2e; }
    .tone-waiting { background:#F6E1A8; border-color:#a0740c; }
    .tone-changing { background:#FCE1D3; border-color:#d9693f; }
    .tone-done { background:#eceeed; border-color:#98a5a1; color:var(--ink-500); }
    .tone-off { background:repeating-linear-gradient(135deg, #e9ebea 0 6px, #f4f5f4 6px 12px); border-color:#5f6f6b; }
    .tone-off-pending { background:repeating-linear-gradient(135deg, #fbefcf 0 6px, #fdf7e6 6px 12px); border-color:#a0740c; border-left-style:dashed; }
    .mw-cal-now { position:absolute; left:0; right:0; height:2px; background:#d9693f; z-index:4; }
    .mw-cal-month { display:grid; grid-template-columns:repeat(7, minmax(0,1fr)); min-width:640px; }
    .mw-cal-cell { min-height:104px; border-top:1px solid rgba(0,0,0,.06); border-left:1px solid rgba(0,0,0,.06); padding:6px; }
    .mw-cal-cell.out { background:rgba(0,0,0,.025); }
    .mw-cal-cell .d { font-size:12px; font-weight:800; color:var(--ink-700); text-decoration:none; }
    .mw-cal-cell.today .d { color:#fff; background:var(--teal-600, #2f6f66); border-radius:999px; padding:1px 7px; }
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
                            <a class="mw-cal-ev tone-{{ $ev['tone'] }} {{ $focus === $ev['id'] ? 'focus' : '' }}" href="{{ $link(['focus' => $ev['id']]) }}"
                               style="top:{{ $top }}px;height:{{ $height - 2 }}px;left:calc({{ $ev['lane'] * $w }}% + 2px);width:calc({{ $w }}% - 4px);">
                                <div class="t">{{ $ev['kind'] === 'session' ? $ev['start']->format('g:i').' ' : '' }}{{ $ev['title'] }}</div>
                                <div class="s">{{ $ev['sub'] }}{{ $ev['pendingChange'] ? ' · change requested' : '' }}</div>
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
    <span><i class="tone-video"></i>Video</span>
    <span><i class="tone-inperson"></i>In person</span>
    <span><i class="tone-waiting"></i>Waiting for approval</span>
    <span><i class="tone-changing"></i>Change requested</span>
    <span><i class="tone-done"></i>Completed</span>
    <span><i class="tone-off"></i>Time off</span>
    <span><i class="tone-off-pending"></i>Time off (waiting)</span>
</div>

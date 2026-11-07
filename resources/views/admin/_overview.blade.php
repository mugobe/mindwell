{{-- Admin Overview. 2026-10-08 redesign (Doctolib-style), refreshed 2026-11-03 with the
     staff look: a greeting that ranks the day, number tiles with a trend line, the work
     queue most urgent first, today's agenda and the payment service card.
     See App\Services\AdminOverview. --}}
@php
    $ov = app(\App\Services\AdminOverview::class);
    $queue = $ov->attention();
    $today = $ov->today();
    $kpi = $ov->kpis();
    $series = $ov->series();
    $pay = $ov->paymentService();
    $cur = config('flutterwave.currency', 'UGX');
    $hour = now()->hour;
    $greet = $hour < 12 ? 'Good morning' : ($hour < 17 ? 'Good afternoon' : 'Good evening');
    $first = explode(' ', trim(auth()->user()->name))[0] ?: 'there';
    $urgent = collect($queue)->where('tone', 'urgent')->sum('count');
    $others = collect($queue)->where('tone', '!=', 'urgent')->sum('count');
    $chip = function ($now, $before) {
        if (! $before) {
            return $now ? '<span class="mw-chip green">▲ New</span>' : '<span class="mw-chip grey">No change</span>';
        }
        $pct = (int) round(($now - $before) / $before * 100);

        return $pct >= 0 ? '<span class="mw-chip green">▲ '.$pct.'%</span>' : '<span class="mw-chip red">▼ '.abs($pct).'%</span>';
    };
    $icons = [
        'alert' => '<path d="M12 3.5l9 15.5H3l9-15.5z"/><path d="M12 10v4"/><circle cx="12" cy="17" r=".9" fill="currentColor" stroke="none"/>',
        'route' => '<circle cx="6" cy="6" r="2"/><circle cx="18" cy="18" r="2"/><path d="M8 6h6a4 4 0 010 8h-4a4 4 0 000 8h6"/>',
        'check' => '<circle cx="12" cy="12" r="9"/><path d="M8.5 12.5l2.5 2.5 4.5-5"/>',
        'swap' => '<path d="M4 8h12l-3-3M20 16H8l3 3"/>',
        'share' => '<circle cx="6" cy="12" r="2"/><circle cx="18" cy="6" r="2"/><circle cx="18" cy="18" r="2"/><path d="M8 11l8-4M8 13l8 4"/>',
        'inbox' => '<path d="M4 13l2.5-8h11L20 13"/><path d="M4 13v6h16v-6h-5l-1 2h-4l-1-2H4z"/>',
        'badge' => '<path d="M12 3l7 3v5c0 4.5-3 8-7 9-4-1-7-4.5-7-9V6l7-3z"/><path d="M9 12.5l2 2 4-4.5"/>',
        'cal' => '<rect x="4" y="5" width="16" height="15" rx="2"/><path d="M4 9h16"/><path d="M8 3v4M16 3v4"/>',
        'wallet' => '<path d="M3 7h16a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2z"/><path d="M3 7l12-4v4M16 13h2"/>',
        'users' => '<circle cx="9" cy="8" r="3.2"/><path d="M3 20c1-3.3 3.4-5 6-5s5 1.7 6 5"/><circle cx="17" cy="9" r="2.5"/><path d="M16 14.6c2.2.2 3.9 1.7 5 4.4"/>',
        'star' => '<path d="M12 3.5l2.6 5.3 5.9.9-4.3 4.1 1 5.8L12 16.9l-5.2 2.7 1-5.8-4.3-4.1 5.9-.9L12 3.5z"/>',
    ];
    $svg = fn ($name, $color = 'currentColor') => '<svg viewBox="0 0 24 24" fill="none" stroke="'.$color.'" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'.$icons[$name].'</svg>';
    $action = [
        'urgent' => ['Open', 'background:#D64545;color:#fff;border:0;'],
        'lobby' => ['Assign', 'background:#EE7800;color:#fff;border:0;'],
    ];
@endphp

<div class="mw-page-head">
    <div>
        <h1>{{ $greet }}, {{ $first }}</h1>
        <div class="sub">
            {{ now()->format('l, j F') }} ·
            @if ($urgent && $others)
                <b style="color:#D64545;">{{ $urgent }} urgent</b> and {{ $others }} other {{ $others === 1 ? 'thing needs' : 'things need' }} you
            @elseif ($urgent)
                <b style="color:#D64545;">{{ $urgent }} urgent {{ $urgent === 1 ? 'thing needs' : 'things need' }} you</b>
            @elseif ($others)
                {{ $others }} {{ $others === 1 ? 'thing needs' : 'things need' }} your attention
            @else
                Nothing is waiting on you right now
            @endif
        </div>
    </div>
    <div class="actions">
        <a href="{{ route('admin.calendar') }}" class="btn ghost sm">Open calendar</a>
        <a href="{{ route('admin.payments.index') }}" class="btn primary sm">Payments</a>
    </div>
</div>

<div class="mw-kpis">
    <a class="mw-kpi" href="{{ route('admin.calendar') }}">
        <div class="k"><span class="ic" style="background:#E3EDF8;">{!! $svg('cal', '#034C8F') !!}</span>Sessions this week</div>
        @include('partials.sparkline', ['points' => $series['sessions'], 'color' => '#034C8F'])
        <div class="v">{{ $kpi['sessions_week'][0] }}</div>
        <div class="d">{!! $chip($kpi['sessions_week'][0], $kpi['sessions_week'][1]) !!} vs {{ $kpi['sessions_week'][1] }} last week</div>
    </a>
    <a class="mw-kpi" href="{{ route('admin.payments.index') }}">
        <div class="k"><span class="ic" style="background:#DDF4EC;">{!! $svg('wallet', '#00A87C') !!}</span>Revenue this month (net)</div>
        @include('partials.sparkline', ['points' => $series['revenue'], 'color' => '#00A87C'])
        <div class="v"><small>{{ $cur }}</small>{{ number_format($kpi['revenue_month'][0], 0) }}</div>
        <div class="d">{!! $chip($kpi['revenue_month'][0], $kpi['revenue_month'][1]) !!} vs this point last month</div>
    </a>
    <a class="mw-kpi" href="{{ route('admin.clients.index') }}">
        <div class="k"><span class="ic" style="background:#EEE8FA;">{!! $svg('users', '#7B5CC4') !!}</span>Active clients</div>
        @include('partials.sparkline', ['points' => $series['active'], 'color' => '#7B5CC4'])
        <div class="v">{{ $kpi['active_clients'] }}</div>
        <div class="d">Had a session in the last 60 days</div>
    </a>
    <a class="mw-kpi" href="{{ route('admin.ratings.index') }}">
        <div class="k"><span class="ic" style="background:#FFEBD6;">{!! $svg('star', '#EE7800') !!}</span>Average rating</div>
        @include('partials.sparkline', ['points' => $series['rating'], 'color' => '#EE7800'])
        <div class="v">{{ $kpi['rating'][0] !== null ? $kpi['rating'][0] : '—' }}</div>
        <div class="d">{!! $kpi['rating'][1] ? '<span class="mw-chip grey">'.$kpi['rating'][1].' ratings</span> last 90 days' : 'No ratings yet' !!}</div>
    </a>
</div>

<div class="mw-ov-grid" style="display:grid;grid-template-columns:minmax(0,1.2fr) minmax(0,1fr);gap:16px;align-items:start;">
    <div class="card" style="padding:0;">
        <div class="mw-card-head">
            <h2>Needs your attention</h2>
            @if (count($queue) > 1)<span style="font-size:12px;color:var(--ink-500);font-weight:600;">Most urgent first</span>@endif
        </div>
        @if (count($queue))
            <ul class="mw-queue">
                @foreach ($queue as $item)
                    @php [$label, $style] = $item['key'] === 'lobby' ? $action['lobby'] : ($item['tone'] === 'urgent' ? $action['urgent'] : ['Review', null]); @endphp
                    <li>
                        <span class="ic {{ $item['tone'] }}">{!! $svg($item['icon']) !!}</span>
                        <div style="min-width:0;">
                            <div class="t">{{ $item['title'] }}</div>
                            <div class="s">{{ $item['sub'] }}</div>
                        </div>
                        <span class="num {{ $item['tone'] === 'urgent' ? 'urgent' : '' }}">{{ $item['count'] }}</span>
                        <a href="{{ route($item['route']) }}" class="btn {{ $style ? '' : 'ghost' }} sm" style="white-space:nowrap;{{ $style }}">{{ $label }}</a>
                    </li>
                @endforeach
            </ul>
        @else
            <div class="mw-empty">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M8.5 12.5l2.5 2.5 4.5-5"/></svg>
                <div class="big">Nothing waiting</div>
                No crisis flags, approvals or requests right now. New ones will show here.
            </div>
        @endif
    </div>

    <div>
        <div class="card" style="padding:0;">
            <div class="mw-card-head">
                <h2>Today · {{ $today->count() }} {{ $today->count() === 1 ? 'session' : 'sessions' }}</h2>
                <a href="{{ route('admin.calendar', ['view' => 'day']) }}">Day view →</a>
            </div>
            @if ($today->isNotEmpty())
                <ul class="mw-agenda">
                    @foreach ($today as $s)
                        @php
                            $past = $s->plannedEndAt()?->isPast();
                            $client = $s->clientProfile?->user;
                            $mins = (int) now()->diffInMinutes($s->scheduled_at, false);
                            $live = $s->status === 'scheduled' && ! $past && $mins <= 60;
                        @endphp
                        <li>
                            <div class="time {{ $past ? 'past' : '' }}">{{ $s->scheduled_at->format('g:i A') }}</div>
                            @include('partials.avatar', ['name' => $client?->name ?? 'Client', 'size' => 30])
                            <div style="min-width:0;">
                                <div class="who">{{ $client?->name ?? 'Client' }}</div>
                                <div class="meta">{{ $s->therapistProfile?->user?->name ?? 'No therapist yet' }} · {{ $s->isInPerson() ? 'In person'.($s->venue ? ', '.$s->venue->name : '') : 'Video' }}</div>
                            </div>
                            @if ($s->status === 'completed')
                                <span class="mw-chip green">Done</span>
                            @elseif ($s->status === 'pending_approval')
                                <span class="mw-chip amber">Awaiting approval</span>
                            @elseif ($past)
                                <span class="mw-chip grey">To close</span>
                            @elseif ($live)
                                <span class="mw-chip live"><span class="dot"></span>{{ $mins <= 0 ? 'Now' : 'In '.$mins.' min' }}</span>
                            @else
                                <span class="mw-chip navy">Booked</span>
                            @endif
                        </li>
                    @endforeach
                </ul>
            @else
                <div class="mw-empty">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="5" width="16" height="15" rx="2"/><path d="M4 9h16"/><path d="M8 3v4M16 3v4"/></svg>
                    <div class="big">No sessions today</div>
                    <a href="{{ route('admin.calendar') }}" style="color:var(--teal-600);font-weight:700;">See the week ahead</a>
                </div>
            @endif
        </div>

        <div class="card" style="padding:14px 18px;margin-top:14px;display:flex;align-items:center;gap:14px;">
            <span style="width:40px;height:40px;border-radius:11px;background:{{ $pay['sandbox'] ? '#FBF0D9' : '#DDF4EC' }};display:inline-flex;align-items:center;justify-content:center;flex:0 0 40px;">
                <span style="width:19px;height:19px;display:block;">{!! $svg('wallet', $pay['sandbox'] ? '#B7791F' : '#00A87C') !!}</span>
            </span>
            <div style="flex:1;min-width:0;">
                <div style="font-weight:800;font-size:14px;">Payment service: {{ $pay['name'] }}</div>
                <div style="font-size:12px;color:var(--ink-500);margin-top:2px;">
                    {{ $pay['sandbox'] ? 'Sandbox' : 'Live' }} · {{ $pay['configured'] ? 'keys set' : 'keys missing' }}@if ($pay['name'] === 'MarzPay') · {{ $pay['last_callback'] ? 'last callback '.$pay['last_callback']->diffForHumans() : 'no callbacks yet' }}@endif
                </div>
            </div>
            @if ($pay['sandbox'])
                <span class="mw-chip test">Sandbox</span>
            @elseif (! $pay['configured'])
                <span class="mw-chip red">Keys missing</span>
            @else
                <span class="mw-chip green"><span class="dot"></span>Live</span>
            @endif
        </div>
    </div>
</div>
<style>@media (max-width: 1100px) { .mw-ov-grid { grid-template-columns: 1fr !important; } }</style>

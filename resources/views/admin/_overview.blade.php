{{-- Admin Overview (2026-10-08 redesign, Doctolib-style): what needs you first, today's
     agenda across therapists, then the headline numbers. See App\Services\AdminOverview. --}}
@php
    $ov = app(\App\Services\AdminOverview::class);
    $queue = $ov->attention();
    $today = $ov->today();
    $kpi = $ov->kpis();
    $cur = config('flutterwave.currency', 'UGX');
    $hour = now()->hour;
    $greet = $hour < 12 ? 'Good morning' : ($hour < 17 ? 'Good afternoon' : 'Good evening');
    $first = explode(' ', trim(auth()->user()->name))[0] ?: 'there';
    $trend = function ($now, $before, bool $money = false) use ($cur) {
        if (! $before) {
            return $now ? '<span class="up">New</span> vs last period' : 'No change';
        }
        $pct = (int) round(($now - $before) / $before * 100);

        return ($pct >= 0 ? '<span class="up">▲ '.$pct.'%</span>' : '<span class="down">▼ '.abs($pct).'%</span>').' vs '.($money ? $cur.' '.number_format($before, 0) : $before);
    };
    $icons = [
        'alert' => '<path d="M12 3.5l9 15.5H3l9-15.5z"/><path d="M12 10v4"/><circle cx="12" cy="17" r=".9" fill="currentColor" stroke="none"/>',
        'route' => '<circle cx="6" cy="6" r="2"/><circle cx="18" cy="18" r="2"/><path d="M8 6h6a4 4 0 010 8h-4a4 4 0 000 8h6"/>',
        'check' => '<circle cx="12" cy="12" r="9"/><path d="M8.5 12.5l2.5 2.5 4.5-5"/>',
        'swap' => '<path d="M4 8h12l-3-3M20 16H8l3 3"/>',
        'share' => '<circle cx="6" cy="12" r="2"/><circle cx="18" cy="6" r="2"/><circle cx="18" cy="18" r="2"/><path d="M8 11l8-4M8 13l8 4"/>',
        'inbox' => '<path d="M4 13l2.5-8h11L20 13"/><path d="M4 13v6h16v-6h-5l-1 2h-4l-1-2H4z"/>',
        'badge' => '<path d="M12 3l7 3v5c0 4.5-3 8-7 9-4-1-7-4.5-7-9V6l7-3z"/><path d="M9 12.5l2 2 4-4.5"/>',
    ];
@endphp

<div class="mw-page-head">
    <div>
        <h1>{{ $greet }}, {{ $first }}</h1>
        <div class="sub">{{ now()->format('l, j F') }} · {{ count($queue) ? count($queue).' '.(count($queue) === 1 ? 'thing needs' : 'things need').' your attention' : 'Nothing is waiting on you right now' }}</div>
    </div>
    <div class="actions">
        <a href="{{ route('admin.calendar') }}" class="btn ghost sm">Open calendar</a>
        <a href="{{ route('admin.payments.index') }}" class="btn ghost sm">Payments</a>
    </div>
</div>

<div class="mw-kpis">
    <a class="mw-kpi" href="{{ route('admin.calendar') }}">
        <div class="k">Sessions this week</div>
        <div class="v">{{ $kpi['sessions_week'][0] }}</div>
        <div class="d">{!! $trend($kpi['sessions_week'][0], $kpi['sessions_week'][1]) !!}</div>
    </a>
    <a class="mw-kpi" href="{{ route('admin.payments.index') }}">
        <div class="k">Revenue this month (net)</div>
        <div class="v">{{ $cur }} {{ number_format($kpi['revenue_month'][0], 0) }}</div>
        <div class="d">{!! $trend($kpi['revenue_month'][0], $kpi['revenue_month'][1], true) !!}</div>
    </a>
    <a class="mw-kpi" href="{{ route('admin.clients.index') }}">
        <div class="k">Active clients</div>
        <div class="v">{{ $kpi['active_clients'] }}</div>
        <div class="d">Had or have a session in the last 60 days</div>
    </a>
    <a class="mw-kpi" href="{{ route('admin.ratings.index') }}">
        <div class="k">Average session rating</div>
        <div class="v">{{ $kpi['rating'][0] !== null ? $kpi['rating'][0].' / 5' : '—' }}</div>
        <div class="d">{{ $kpi['rating'][1] ? $kpi['rating'][1].' ratings in the last 90 days' : 'No ratings yet' }}</div>
    </a>
</div>

<div class="mw-ov-grid" style="display:grid;grid-template-columns:minmax(0,1.25fr) minmax(0,1fr);gap:16px;align-items:start;">
    <div class="card" style="padding:0;">
        <div class="mw-card-head">
            <h2>Needs your attention</h2>
        </div>
        @if (count($queue))
            <ul class="mw-queue">
                @foreach ($queue as $item)
                    <li>
                        <span class="ic {{ $item['tone'] }}"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">{!! $icons[$item['icon']] !!}</svg></span>
                        <div style="min-width:0;">
                            <div class="t">{{ $item['title'] }}</div>
                            <div class="s">{{ $item['sub'] }}</div>
                        </div>
                        <span class="num {{ $item['tone'] === 'urgent' ? 'urgent' : '' }}">{{ $item['count'] }}</span>
                        <a href="{{ route($item['route']) }}" class="btn {{ $item['tone'] === 'urgent' ? 'primary' : 'ghost' }} sm" style="white-space:nowrap;">Review</a>
                    </li>
                @endforeach
            </ul>
        @else
            <div class="mw-empty">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M8.5 12.5l2.5 2.5 4.5-5"/></svg>
                <div class="big">All clear</div>
                No crisis flags, approvals or requests waiting. We'll show them here the moment they come in.
            </div>
        @endif
    </div>

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
                    @endphp
                    <li>
                        <div class="time {{ $past ? 'past' : '' }}">{{ $s->scheduled_at->format('g:i A') }}</div>
                        @include('partials.avatar', ['name' => $client?->name ?? 'Client', 'size' => 30])
                        <div style="min-width:0;">
                            <div class="who">{{ $client?->name ?? 'Client' }}</div>
                            <div class="meta">with {{ $s->therapistProfile?->user?->name ?? '—' }} · {{ $s->isInPerson() ? 'In person'.($s->venue ? ', '.$s->venue->name : '') : 'Video' }}</div>
                        </div>
                        <span class="mw-dot {{ $s->status === 'completed' ? 'ok' : ($s->status === 'pending_approval' ? 'wait' : '') }}">{{ ['completed' => 'Done', 'pending_approval' => 'To approve', 'scheduled' => $past ? 'To close' : 'Booked'][$s->status] ?? ucfirst($s->status) }}</span>
                    </li>
                @endforeach
            </ul>
        @else
            <div class="mw-empty">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="5" width="16" height="15" rx="2"/><path d="M4 9h16"/><path d="M8 3v4M16 3v4"/></svg>
                <div class="big">No sessions today</div>
                <a href="{{ route('admin.calendar') }}" style="color:var(--teal-600,#2f6f66);font-weight:700;">See the week ahead</a>
            </div>
        @endif
    </div>
</div>
<style>@media (max-width: 1100px) { .mw-ov-grid { grid-template-columns: 1fr !important; } }</style>

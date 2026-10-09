@extends('layouts.app')
@section('title', 'Search')
@section('content')
{{-- Admin global search results (2026-10-08). See Admin\SearchController. --}}

<div class="mw-page-head">
    <div>
        <h1>{{ $q !== '' ? 'Results for “'.$q.'”' : 'Search' }}</h1>
        <div class="sub">
            @if (mb_strlen($q) < 2)
                Type at least two letters — a name, email, client ID (e.g. 0007) or receipt number.
            @else
                {{ $total }} {{ $total === 1 ? 'match' : 'matches' }} across clients, therapists and payments.
            @endif
        </div>
    </div>
</div>

@if (mb_strlen($q) >= 2 && $total === 0)
    <div class="card mw-empty">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"><circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5"/></svg>
        <div class="big">Nothing found</div>
        Check the spelling, or try an email, phone number or client ID.
    </div>
@endif

@if ($results['clients']->isNotEmpty())
    <div class="card" style="padding:0;margin-bottom:16px;">
        <div class="mw-card-head"><h2>Clients · {{ $results['clients']->count() }}</h2></div>
        <ul class="mw-queue">
            @foreach ($results['clients'] as $u)
                <li>
                    @include('partials.avatar', ['name' => $u->name])
                    <div>
                        <div class="t">{{ $u->name }}</div>
                        <div class="s">{{ $u->clientLabel() ?? 'Client' }} · {{ $u->email }}</div>
                    </div>
                    @if ($u->clientProfile)
                        <a href="{{ route('admin.clients.show', $u->clientProfile) }}" class="btn ghost sm" style="margin-left:auto;">Open file</a>
                    @endif
                </li>
            @endforeach
        </ul>
    </div>
@endif

@if ($results['therapists']->isNotEmpty())
    <div class="card" style="padding:0;margin-bottom:16px;">
        <div class="mw-card-head"><h2>Therapists · {{ $results['therapists']->count() }}</h2></div>
        <ul class="mw-queue">
            @foreach ($results['therapists'] as $u)
                <li>
                    @include('partials.avatar', ['name' => $u->name])
                    <div>
                        <div class="t">{{ $u->name }}</div>
                        <div class="s">{{ $u->email }} · {{ $u->therapistProfile?->credentials_verified ? 'Verified' : 'Awaiting approval' }}</div>
                    </div>
                    <div style="margin-left:auto;display:flex;gap:6px;">
                        @if ($u->therapistProfile)
                            <a href="{{ route('admin.calendar', ['therapist' => $u->therapistProfile->id]) }}" class="btn ghost sm">Calendar</a>
                        @endif
                        <a href="{{ route('admin.therapists.index') }}" class="btn ghost sm">Therapists</a>
                    </div>
                </li>
            @endforeach
        </ul>
    </div>
@endif

@if ($results['payments']->isNotEmpty())
    <div class="card" style="padding:0;margin-bottom:16px;">
        <div class="mw-card-head"><h2>Payments · {{ $results['payments']->count() }}</h2></div>
        <ul class="mw-queue">
            @foreach ($results['payments'] as $p)
                <li>
                    <span class="ic warm"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="6" width="18" height="13" rx="2"/><path d="M3 10h18"/></svg></span>
                    <div>
                        <div class="t">{{ $p->receipt_number ?? $p->tx_ref }} · {{ $p->currency }} {{ number_format((float) $p->amount, 0) }}</div>
                        <div class="s">{{ $p->clientProfile?->user?->name ?? '—' }} · {{ $p->created_at->format('j M Y') }} · {{ ucfirst($p->status) }}</div>
                    </div>
                    @if ($p->status === 'successful')
                        <a href="{{ route('admin.payments.receipt', $p) }}" class="btn ghost sm" style="margin-left:auto;">Receipt</a>
                    @endif
                </li>
            @endforeach
        </ul>
    </div>
@endif
@endsection

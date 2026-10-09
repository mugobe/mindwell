@extends('layouts.app')
@section('title', 'Employers')
@section('content')
{{-- Employer accounts (2026-10-08 redesign): page head, KPI tiles, company bubbles, seat meter. --}}
@php
    $mwEnding = $employers->filter(fn ($e) => $e->isEndingSoon());
    $seatsUsed = $employers->sum(fn ($e) => $e->activeSeatsCount());
    $seatsAll = $employers->sum('seats_count');
@endphp

<div class="mw-page-head">
    <div>
        <h1>Employers</h1>
        <div class="sub">Companies that pay for their staff's sessions. Each seat is a code one employee redeems.</div>
    </div>
    <div class="actions"><a href="{{ route('admin.employers.create') }}" class="btn primary sm">Add employer</a></div>
</div>

{{-- Contracts ending within 30 days (2026-10-06, roadmap 27). Admins are also emailed. --}}
@if ($mwEnding->isNotEmpty())
    <div class="mw-alert" style="background:#FDF1EC;color:#8a4326;border:1px solid rgba(232,130,94,.35);margin-bottom:14px;">
        <strong>Contracts ending soon:</strong>
        @foreach ($mwEnding as $e)
            <a href="{{ route('admin.employers.show', $e) }}" style="color:inherit;font-weight:700;">{{ $e->company_name }}</a> ({{ $e->contract_renewal_date->format('j M') }}){{ $loop->last ? '' : ',' }}
        @endforeach
        — contact them about renewing, then update the renewal date.
    </div>
@endif

<div class="mw-kpis">
    <div class="mw-kpi">
        <div class="k">Active employers</div>
        <div class="v">{{ $employers->where('status', 'active')->count() }}</div>
        <div class="d">{{ $employers->count() }} in total</div>
    </div>
    <div class="mw-kpi">
        <div class="k">Seats in use</div>
        <div class="v">{{ $seatsUsed }}<span style="font-size:14px;color:var(--ink-300);font-weight:700;"> / {{ $seatsAll }}</span></div>
        <div class="d">{{ $seatsAll ? round($seatsUsed / $seatsAll * 100).'% taken up' : 'No seats yet' }}</div>
    </div>
    <div class="mw-kpi">
        <div class="k">Renewing in 30 days</div>
        <div class="v" style="{{ $mwEnding->isNotEmpty() ? 'color:var(--mw-orange, #E8825E);' : '' }}">{{ $mwEnding->count() }}</div>
        <div class="d">{{ $mwEnding->isNotEmpty() ? 'Time to talk renewals' : 'Nothing due' }}</div>
    </div>
</div>

<div class="card" style="padding:0;">
    <table class="mw-table">
        <thead>
            <tr>
                <th style="padding-left:18px;">Company</th>
                <th>Contact</th>
                <th>Seats</th>
                <th>Status</th>
                <th>Renews</th>
                <th style="padding-right:18px;text-align:right;"></th>
            </tr>
        </thead>
        <tbody>
            @forelse ($employers as $employer)
                @php
                    $used = $employer->activeSeatsCount();
                    $all = (int) $employer->seats_count;
                @endphp
                <tr>
                    <td style="padding-left:18px;">@include('partials.person', ['name' => $employer->company_name, 'href' => route('admin.employers.show', $employer)])</td>
                    <td>
                        @if ($employer->contact_name || $employer->contact_email)
                            <div style="font-size:13px;color:var(--ink-900);">{{ $employer->contact_name ?? '—' }}</div>
                            @if ($employer->contact_email)<div style="font-size:11.5px;color:var(--ink-500);">{{ $employer->contact_email }}</div>@endif
                        @else
                            <span style="color:var(--ink-300);">—</span>
                        @endif
                    </td>
                    <td style="white-space:nowrap;">
                        <div style="display:flex;align-items:center;gap:8px;">
                            <strong style="font-variant-numeric:tabular-nums;">{{ $used }}</strong><span style="font-size:11px;color:var(--ink-300);">of {{ $all }}</span>
                            <span class="mw-meter"><span style="width:{{ $all ? min(100, round($used / $all * 100)) : 0 }}%;background:var(--teal-600, #2f6f66);"></span></span>
                        </div>
                    </td>
                    <td><span class="mw-dot {{ $employer->status === 'active' ? 'ok' : 'bad' }}">{{ ucfirst($employer->status) }}</span></td>
                    <td style="white-space:nowrap;">
                        <div style="font-size:13px;color:{{ $employer->isEndingSoon() ? 'var(--mw-orange, #E8825E)' : 'var(--ink-900)' }};{{ $employer->isEndingSoon() ? 'font-weight:700;' : '' }}">{{ $employer->contract_renewal_date?->format('j M Y') ?? '—' }}</div>
                        @if ($employer->isEndingSoon())<div style="font-size:11.5px;color:var(--mw-orange, #E8825E);">Ending soon</div>@endif
                    </td>
                    <td style="padding-right:18px;text-align:right;">
                        <a href="{{ route('admin.employers.show', $employer) }}" class="btn ghost sm">View seats</a>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="6">
                        <div class="mw-empty">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M4 21V5l8-2v18"/><path d="M12 9h8v12"/><path d="M7 8h2M7 12h2M7 16h2M15 13h2M15 17h2"/></svg>
                            <div class="big">No employers yet</div>
                            Add a company to give its staff sessions on the company's account.
                        </div>
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>
@endsection

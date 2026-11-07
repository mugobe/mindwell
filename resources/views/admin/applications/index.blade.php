@extends('layouts.app')
@section('title', 'Applications')
@section('content')
{{-- Admin → People → Applications (therapist onboarding, 2026-11-03). --}}
<div class="mw-page-head">
    <div>
        <h1>Therapist applications</h1>
        <div class="sub">Check each applicant's licence, documents and references, then approve, ask for changes or decline.</div>
    </div>
</div>

<nav class="mw-tabs" aria-label="Status">
    @foreach ($tabs as $key => $label)
        <a href="{{ route('admin.applications.index', ['tab' => $key]) }}" class="{{ $tab === $key ? 'on' : '' }}">{{ $label }} <span class="n">{{ $counts[$key] ?? 0 }}</span></a>
    @endforeach
</nav>

<div class="card" style="padding:0;">
    <table class="mw-table">
        <thead>
            <tr>
                <th style="padding-left:16px;">Applicant</th>
                <th>Profession</th>
                <th>Registered with</th>
                <th>Licence expires</th>
                <th>Flags</th>
                <th>{{ $tab === 'draft' ? 'Last saved' : ($tab === 'submitted' ? 'Sent' : 'Updated') }}</th>
                <th style="padding-right:16px;"></th>
            </tr>
        </thead>
        <tbody>
            @forelse ($apps as $app)
                @php
                    $exp = $app->licenceExpiry();
                    $flags = count($app->flaggedDeclarations());
                    $docsOk = collect(config('onboarding.documents'))->filter(fn ($d) => $d['required'])->keys()->every(fn ($k) => $app->document($k));
                @endphp
                <tr>
                    <td style="padding-left:16px;">@include('partials.person', ['name' => $app->user?->name ?? 'Deleted user', 'sub' => $app->user?->email])</td>
                    <td>{{ $app->professionLabel() ?? '—' }}</td>
                    <td style="max-width:220px;">{{ $app->regulatorLabel() ?? '—' }}</td>
                    <td>
                        @if ($exp)
                            {{ $exp->format('j M Y') }}
                            @if ($exp->isPast()) <span class="mw-chip red">Expired</span>
                            @elseif ($exp->lt(now()->addMonths(3))) <span class="mw-chip amber">Soon</span>@endif
                        @else
                            —
                        @endif
                    </td>
                    <td>
                        @if ($flags)<span class="mw-chip amber">{{ $flags }} "yes" {{ $flags === 1 ? 'answer' : 'answers' }}</span>@endif
                        @unless ($docsOk)<span class="mw-chip red">Documents missing</span>@endunless
                        @if (! $flags && $docsOk)<span class="mw-chip grey">None</span>@endif
                    </td>
                    <td>{{ ($tab === 'draft' ? $app->updated_at : ($tab === 'submitted' ? $app->submitted_at : ($app->decided_at ?? $app->updated_at)))?->diffForHumans() }}</td>
                    <td style="padding-right:16px;text-align:right;"><a href="{{ route('admin.applications.show', $app) }}" class="btn {{ $tab === 'submitted' ? 'primary' : 'ghost' }} sm">{{ $tab === 'submitted' ? 'Review' : 'Open' }}</a></td>
                </tr>
            @empty
                <tr><td colspan="7"><div class="mw-empty" style="padding:22px;"><div class="big">Nothing here</div>{{ $tab === 'submitted' ? 'New applications appear here as soon as a therapist sends theirs.' : 'No applications with this status.' }}</div></td></tr>
            @endforelse
        </tbody>
    </table>
</div>
@endsection

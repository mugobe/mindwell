@extends('layouts.app')
@section('title', 'Intake Reviews')
@section('content')

<div class="dash-head">
    <h1>Client intake assessments</h1>
    <div style="display:flex;gap:8px;">
        @if ($crisisCount > 0)
            <span class="pill danger">{{ $crisisCount }} crisis</span>
        @endif
        @if ($highCount > 0)
            <span class="pill warn">{{ $highCount }} high risk</span>
        @endif
    </div>
</div>

<div class="card" style="padding:0;">
    <table class="mw-table">
        <thead>
            <tr>
                <th style="padding-left:16px;">Client</th>
                <th>PHQ-9</th>
                <th>GAD-7</th>
                <th>Risk level</th>
                <th style="padding-right:16px;">Submitted</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($assessments as $assessment)
            <tr>
                <td style="padding-left:16px;">
                    {{ $assessment->client?->user?->name ?? 'Unknown client' }}
                    <div style="font-size:10.5px;color:var(--ink-300);">{{ $assessment->client?->user?->email }}</div>
                </td>
                <td>{{ $assessment->phq9_score }} / 27</td>
                <td>{{ $assessment->gad7_score }} / 21</td>
                <td>
                    <span class="pill {{ match($assessment->risk_level) {
                        'crisis' => 'danger',
                        'high' => 'danger',
                        'moderate' => 'warn',
                        default => 'success',
                    } }}">
                        {{ ucfirst($assessment->risk_level) }}
                    </span>
                </td>
                <td style="padding-right:16px;">{{ $assessment->created_at->format('M j, Y g:i A') }}</td>
            </tr>
            @empty
            <tr><td colspan="5" style="padding:16px;color:var(--ink-500);">No intake assessments submitted yet.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

<div style="margin-top:16px;">{{ $assessments->links() }}</div>

<p style="font-size:11px;color:var(--ink-300);margin-top:12px;">
    A crisis-level submission automatically opens a case in <a href="{{ route('admin.crisis.index') }}" style="color:var(--teal-600);">Crisis Flags</a> —
    check that queue to acknowledge and resolve them. High-risk (non-crisis) rows still only show up here.
</p>
@endsection

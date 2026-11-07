<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title>Session report</title>
<style>
    /* Branded clinical report (2026-11-01, Collins: brand it like the receipts). Same
       header as payments/receipt-pdf: logo left, practice details right, navy rules.
       dompdf understands plain blocks and tables only (no flexbox or grid).
       DejaVu Sans is built into dompdf and can draw any character a therapist types. */
    @page { margin: 26px 34px 56px; }
    body { font-family: "DejaVu Sans", sans-serif; font-size: 10.5px; color: #1a1a1a; margin: 0; }
    table { width: 100%; border-collapse: collapse; }
    .head td { vertical-align: top; }
    .practice { text-align: right; font-size: 9.5px; line-height: 1.55; color: #333; }
    .practice .name { font-size: 12.5px; font-weight: bold; color: #034C8F; text-transform: uppercase; margin-bottom: 2px; }
    .rule { border-top: 2.5px solid #034C8F; margin: 10px 0 0; }
    .rule-thin { border-top: 1px solid #00B888; margin: 2px 0 12px; }
    .title { text-align: center; font-size: 15px; font-weight: bold; letter-spacing: 0.16em; color: #034C8F; margin: 0 0 4px; }
    .conf { text-align: center; font-size: 9px; font-weight: bold; letter-spacing: 0.12em; color: #B3261E; margin-bottom: 12px; }
    .box td { padding: 5px 8px; font-size: 10px; border: 1px solid #d6dde5; vertical-align: top; }
    .box .k { background: #f2f5f8; color: #444; width: 17%; font-weight: bold; }
    .outcome { margin: 12px 0 4px; padding: 7px 10px; border-left: 4px solid #00A87C; background: #EEF8F4; font-size: 10.5px; }
    .outcome b { color: #034C8F; }
    .followup { margin: 6px 0 0; font-size: 9.5px; color: #555; }
    .sec { margin-top: 12px; page-break-inside: avoid; }
    .sec-h { background: #034C8F; color: #fff; font-size: 9.5px; font-weight: bold; letter-spacing: 0.06em; text-transform: uppercase; padding: 5px 8px; }
    .sec-h span { color: #9EC3EA; margin-right: 6px; }
    .sec-b { border: 1px solid #d6dde5; border-top: 0; padding: 8px 10px; font-size: 10.5px; line-height: 1.55; white-space: pre-wrap; min-height: 14px; }
    .sec-b.empty { color: #999; }
    .risk .sec-h { background: #8A1C1C; }
    .sign { margin-top: 22px; }
    .sign td { font-size: 9.5px; padding-top: 22px; vertical-align: bottom; }
    .sign .line { border-top: 1px solid #888; padding-top: 4px; color: #555; }
    .foot { position: fixed; bottom: -36px; left: 0; right: 0; border-top: 1px solid #d6dde5; padding-top: 6px; font-size: 8.5px; color: #777; }
</style>
</head>
<body>
    @php
        $business = config('mindwell.receipt', []);
        $client = $session->clientProfile?->user;
        $therapist = $session->therapistProfile;
        $clientNo = rescue(fn () => $client?->clientLabel(), null, false);
        $logoPath = public_path('images/mindwell-logo-horizontal.png');
        $logo = is_file($logoPath) ? 'data:image/png;base64,'.base64_encode(file_get_contents($logoPath)) : null;
        $empty = 'Not recorded';
        $n = 0;
        $sections = [];
        if ($session->session_summary || $session->presenting_concerns || $session->progress_notes || $session->risk_assessment || $session->plan_notes) {
            $sections = [
                ['Presenting concerns', $session->presenting_concerns, false],
                ['Session summary', $session->session_summary, false],
                ['Progress since last visit', $session->progress_notes, false],
                ['Risk assessment', $session->risk_assessment, true],
                ['Plan and next steps', $session->plan_notes, false],
            ];
            foreach (['interventions_used' => 'Interventions used', 'homework' => 'Homework and activities given', 'goals_progress' => 'Progress toward goals'] as $field => $label) {
                if ($session->{$field}) {
                    $sections[] = [$label, $session->{$field}, false];
                }
            }
        } else {
            $sections = [['Report', $session->closure_report, false]];
        }
    @endphp

    {{-- Repeats at the bottom of every page --}}
    <div class="foot">
        <table><tr>
            <td>CONFIDENTIAL. Internal clinical record of {{ $business['business_name'] ?? 'Mindwell' }}. Not shared with the client. Do not forward.</td>
            <td style="text-align:right;white-space:nowrap;">Ref. {{ strtoupper(substr((string) $session->id, 0, 8)) }}</td>
        </tr></table>
    </div>

    <table class="head">
        <tr>
            <td style="width:52%;">
                @if ($logo)
                    <img src="{{ $logo }}" style="height:54px;" alt="Mindwell">
                @else
                    <div style="font-size:20px;font-weight:bold;color:#034C8F;">Mindwell</div>
                @endif
            </td>
            <td class="practice">
                <div class="name">{{ $business['business_name'] ?? 'Mindwell' }}</div>
                @if (! empty($business['address'])){{ $business['address'] }}<br>@endif
                @if (! empty($business['phone']))Tel: {{ $business['phone'] }}<br>@endif
                @if (! empty($business['email']))Email: {{ $business['email'] }}@endif
            </td>
        </tr>
    </table>
    <div class="rule"></div>
    <div class="rule-thin"></div>

    <div class="title">SESSION REPORT</div>
    <div class="conf">CONFIDENTIAL CLINICAL RECORD</div>

    <table class="box">
        <tr>
            <td class="k">Client</td>
            <td style="font-weight:bold;">{{ $client?->name ?? 'Unknown client' }}</td>
            <td class="k">Client No.</td>
            <td>{{ $clientNo ?? 'N/A' }}</td>
        </tr>
        <tr>
            <td class="k">Date of birth</td>
            <td>{{ $client?->date_of_birth?->format('d/m/Y') ?? 'N/A' }}</td>
            <td class="k">Session No.</td>
            <td>{{ $session->session_number ?? 'N/A' }}</td>
        </tr>
        <tr>
            <td class="k">Therapist</td>
            <td style="font-weight:bold;">{{ $therapist?->user?->name ?? 'Unknown therapist' }}</td>
            <td class="k">Licence No.</td>
            <td>{{ $therapist?->license_number ?: 'N/A' }}</td>
        </tr>
        <tr>
            <td class="k">Session date</td>
            <td>{{ $session->scheduled_at?->format('d/m/Y, h:i A') ?? 'N/A' }}</td>
            <td class="k">Type</td>
            <td>{{ $session->isInPerson() ? 'In person'.($session->locationText() ? ', '.$session->locationText() : '') : 'Video session' }}{{ $session->duration_minutes ? ', '.$session->duration_minutes.' min' : '' }}</td>
        </tr>
        <tr>
            <td class="k">Closed on</td>
            <td>{{ $session->closed_at?->format('d/m/Y, h:i A') ?? 'N/A' }}</td>
            <td class="k">Printed</td>
            <td>{{ now()->format('d/m/Y, h:i A') }}</td>
        </tr>
    </table>

    <div class="outcome">Outcome: <b>{{ $outcomes[$session->closure_outcome] ?? $session->closure_outcome ?? 'Not recorded' }}</b></div>
    @if ($session->previous_session_id)
        <div class="followup">
            Booked as a follow-up from an earlier session.
            @if ($session->follow_up_notes)
                Notes at the time: "{{ $session->follow_up_notes }}"
            @endif
        </div>
    @endif

    @foreach ($sections as [$label, $text, $isRisk])
        <div class="sec {{ $isRisk ? 'risk' : '' }}">
            <div class="sec-h"><span>{{ ++$n }}.</span>{{ $label }}</div>
            <div class="sec-b {{ filled($text) ? '' : 'empty' }}">{{ filled($text) ? $text : $empty }}</div>
        </div>
    @endforeach
    @if (count($sections) === 1)
        <div class="followup">This session was closed before the structured report format was added.</div>
    @endif

    <table class="sign">
        <tr>
            <td style="width:45%;"><div class="line">Prepared by: {{ $therapist?->user?->name ?? 'Therapist' }}{{ $therapist?->license_number ? ', licence '.$therapist->license_number : '' }}</div></td>
            <td style="width:10%;"></td>
            <td style="width:45%;"><div class="line">Closed electronically{{ $session->closed_at ? ' on '.$session->closed_at->format('d/m/Y') : '' }}. No signature needed.</div></td>
        </tr>
    </table>
</body>
</html>

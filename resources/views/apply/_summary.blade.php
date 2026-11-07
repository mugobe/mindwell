{{-- Every answer in an application, grouped by step (2026-11-03). Used on the applicant's
     review step ($mwEdit = true: "Edit" links) and on the admin review screen
     ($mwAdmin = true: documents open, reference contacts shown in full). --}}
@php
    $mwEdit = $mwEdit ?? false;
    $mwAdmin = $mwAdmin ?? false;
    $ans = fn ($p, $d = null) => $app->answer($p, $d);
    $list = fn ($p, $cfg = null) => collect((array) $ans($p, []))->map(fn ($v) => $cfg ? config("onboarding.$cfg.$v", $v) : $v)->implode(', ');
    $dash = '<span style="color:var(--ink-300);font-weight:500;">Not answered</span>';
    $show = fn ($v) => filled($v) ? e($v) : $dash;
    $date = fn ($v) => $v ? rescue(fn () => \Illuminate\Support\Carbon::parse($v)->format('j M Y'), $v, false) : null;
    $doc = function ($kind) use ($app, $mwAdmin, $dash) {
        $d = $app->document($kind);
        if (! $d) {
            return config("onboarding.documents.$kind.required") ? '<span class="mw-chip red">Missing</span>' : $dash;
        }
        $name = e(\Illuminate\Support\Str::limit($d->original_name, 40)).' <span style="color:var(--ink-500);font-weight:500;">· '.$d->sizeLabel().'</span>';

        return $mwAdmin
            ? '<a href="'.route('admin.applications.document', [$app, $d]).'" target="_blank" rel="noopener" style="color:#034C8F;">'.$name.' ↗</a>'
            : $name;
    };
    $head = function ($title, $step) use ($mwEdit) {
        return '<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;"><h2 style="margin:0;">'.e($title).'</h2>'
            .($mwEdit ? '<a class="edit" href="'.route('apply.step', $step).'">Edit</a>' : '').'</div>';
    };
    $expiry = $app->licenceExpiry();
@endphp

<div class="ap-card ap-sum">
    {!! $head('Licence and registration', 'licence') !!}
    <dl>
        <dt>Profession</dt><dd>{!! $show($app->professionLabel()) !!}</dd>
        <dt>Registered with</dt><dd>{!! $show($app->regulatorLabel()) !!}</dd>
        <dt>Licence number</dt><dd>{!! $show($ans('licence.licence_number')) !!}</dd>
        <dt>Issued</dt><dd>{!! $show($date($ans('licence.licence_issued'))) !!}</dd>
        <dt>Expires</dt><dd>{!! $show($date($ans('licence.licence_expiry'))) !!}
            @if ($expiry && $expiry->isPast()) <span class="mw-chip red">Expired</span>
            @elseif ($expiry && $expiry->lt(now()->addMonths(3))) <span class="mw-chip amber">Within 3 months</span>@endif</dd>
        <dt>Licence certificate</dt><dd>{!! $doc('licence') !!}</dd>
        <dt>National ID or passport</dt><dd>{!! $doc('national_id') !!}</dd>
    </dl>
</div>

<div class="ap-card ap-sum">
    {!! $head('Training and experience', 'training') !!}
    <dl>
        <dt>Highest qualification</dt><dd>{!! $show(trim(implode(', ', array_filter([config('onboarding.qualification_levels.'.$ans('training.qualification_level')), $ans('training.qualification_title'), $ans('training.institution'), $ans('training.qualification_year')])))) !!}</dd>
        <dt>Other training</dt><dd>{!! filled($ans('training.other_qualifications')) ? nl2br(e($ans('training.other_qualifications'))) : $dash !!}</dd>
        <dt>Years in practice</dt><dd>{!! $show($ans('training.years_practice')) !!}</dd>
        <dt>Clinical supervision</dt><dd>{!! $ans('training.supervision') === 'yes' ? 'Yes'.(filled($ans('training.supervisor_name')) ? ', with '.e($ans('training.supervisor_name')).($mwAdmin && filled($ans('training.supervisor_contact')) ? ' ('.e($ans('training.supervisor_contact')).')' : '') : '') : ($ans('training.supervision') === 'no' ? 'No' : $dash) !!}</dd>
        <dt>CV</dt><dd>{!! $doc('cv') !!}</dd>
        <dt>Works with</dt><dd>{!! $show($list('training.specialties')) !!}</dd>
        <dt>Approaches</dt><dd>{!! $show($list('training.approaches')) !!}</dd>
        <dt>Languages</dt><dd>{!! $show(trim($list('training.languages').(filled($ans('training.languages_other')) ? ', '.$ans('training.languages_other') : ''), ', ')) !!}</dd>
        <dt>Clients</dt><dd>{!! $show($list('training.age_groups', 'age_groups')) !!}</dd>
        <dt>Session types</dt><dd>{!! $show($list('training.session_types', 'session_types')) !!}</dd>
    </dl>
</div>

<div class="ap-card ap-sum">
    {!! $head('Fitness to practise', 'fitness') !!}
    <dl>
        @foreach (config('onboarding.declarations') as $key => $q)
            @php $yn = $ans("fitness.declarations.$key.answer"); @endphp
            <dt style="line-height:1.45;">{{ $q }}</dt>
            <dd>
                @if ($yn === 'yes')
                    <span class="mw-chip amber">Yes</span>
                    <div style="font-weight:500;margin-top:6px;white-space:pre-line;">{{ $ans("fitness.declarations.$key.details") ?: 'No details given.' }}</div>
                @elseif ($yn === 'no')
                    <span class="mw-chip grey">No</span>
                @else
                    {!! $dash !!}
                @endif
            </dd>
        @endforeach
        <dt>Indemnity insurance</dt><dd>{!! $ans('fitness.insurance') === 'yes' ? 'Yes'.(filled($ans('fitness.insurer')) ? ', '.e($ans('fitness.insurer')) : '').(filled($ans('fitness.insurance_expiry')) ? ', until '.e($date($ans('fitness.insurance_expiry'))) : '') : ($ans('fitness.insurance') === 'no' ? 'No' : $dash) !!}</dd>
        <dt>Insurance certificate</dt><dd>{!! $doc('insurance') !!}</dd>
        @for ($i = 0; $i < 2; $i++)
            <dt>Reference {{ $i + 1 }}</dt>
            <dd>
                @if (filled($ans("fitness.references.$i.name")))
                    {{ $ans("fitness.references.$i.name") }}@if (filled($ans("fitness.references.$i.role"))), {{ $ans("fitness.references.$i.role") }}@endif @if (filled($ans("fitness.references.$i.organisation")))at {{ $ans("fitness.references.$i.organisation") }}@endif
                    <div style="font-weight:500;color:var(--ink-500);">{{ $ans("fitness.references.$i.relationship") }}</div>
                    <div style="font-weight:500;">{{ implode(' · ', array_filter([$ans("fitness.references.$i.email"), $ans("fitness.references.$i.phone")])) }}</div>
                @else
                    {!! $dash !!}
                @endif
            </dd>
        @endfor
    </dl>
</div>

<div class="ap-card ap-sum">
    {!! $head('Profile', 'profile') !!}
    <div style="display:flex;gap:16px;align-items:flex-start;">
        <div style="width:72px;height:72px;border-radius:50%;overflow:hidden;background:#E3EDF8;flex:0 0 72px;">
            @if ($ans('profile.photo_path'))<img src="{{ asset('storage/'.$ans('profile.photo_path')) }}" alt="" style="width:100%;height:100%;object-fit:cover;">@endif
        </div>
        <dl style="flex:1;">
            <dt>Professional title</dt><dd>{!! $show($ans('profile.professional_title')) !!}</dd>
            <dt>Usual fee</dt><dd>{!! filled($ans('profile.fee')) ? e(config('flutterwave.currency', 'UGX').' '.number_format((float) $ans('profile.fee'))) : $dash !!}</dd>
            <dt>Bio</dt><dd style="font-weight:500;white-space:pre-line;">{!! $show($ans('profile.bio')) !!}</dd>
        </dl>
    </div>
</div>

<div class="ap-card ap-sum">
    {!! $head('Agreements', 'consents') !!}
    <dl>
        @foreach (config('onboarding.consents') as $key => $docCfg)
            @php $rec = $consents[$key] ?? null; @endphp
            <dt>{{ $docCfg['title'] }} (v{{ $docCfg['version'] }})</dt>
            <dd>
                @if ($rec)
                    <span class="mw-chip green">Agreed</span> <span style="font-weight:500;color:var(--ink-500);">{{ $rec->accepted_at->format('j M Y, g:i A') }}@if ($mwAdmin && $rec->ip) · from {{ $rec->ip }}@endif</span>
                @else
                    <span class="mw-chip red">Not agreed yet</span>
                @endif
            </dd>
        @endforeach
    </dl>
</div>

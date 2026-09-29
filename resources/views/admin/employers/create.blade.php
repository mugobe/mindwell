@extends('layouts.app')
@section('title', 'Add Employer')
@section('content')

<div class="dash-head">
    <h1>Add an employer account</h1>
    <a href="{{ route('admin.employers.index') }}" class="btn ghost sm">Back to employers</a>
</div>

<div class="card" style="max-width:560px;">
    <p style="font-size:12.5px;color:var(--ink-500);margin-bottom:20px;">
        One redeemable seat code is generated automatically for every seat purchased. Hand those codes to the
        employer to distribute to their staff.
    </p>

    @if ($errors->any())
        <div class="mw-alert danger">
            <ul style="margin:0;padding-left:16px;">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('admin.employers.store') }}" style="display:flex;flex-direction:column;gap:16px;">
        @csrf
        <div>
            <label class="mw-label" for="company_name">Company name</label>
            <input class="mw-field" type="text" id="company_name" name="company_name" value="{{ old('company_name') }}" required autofocus>
        </div>
        <div style="display:flex;gap:12px;">
            <div style="flex:1;">
                <label class="mw-label" for="contact_name">Contact name</label>
                <input class="mw-field" type="text" id="contact_name" name="contact_name" value="{{ old('contact_name') }}">
            </div>
            <div style="flex:1;">
                <label class="mw-label" for="contact_email">Contact email</label>
                <input class="mw-field" type="email" id="contact_email" name="contact_email" value="{{ old('contact_email') }}">
            </div>
        </div>
        <div>
            <label class="mw-label" for="seats_purchased">Seats purchased</label>
            <input class="mw-field" type="number" min="1" max="1000" id="seats_purchased" name="seats_purchased" value="{{ old('seats_purchased', 10) }}" required>
        </div>
        <div style="display:flex;gap:12px;">
            <div style="flex:1;">
                <label class="mw-label" for="contract_start">Contract start</label>
                <input class="mw-field" type="date" id="contract_start" name="contract_start" value="{{ old('contract_start') }}">
            </div>
            <div style="flex:1;">
                <label class="mw-label" for="contract_renewal_date">Renewal date</label>
                <input class="mw-field" type="date" id="contract_renewal_date" name="contract_renewal_date" value="{{ old('contract_renewal_date') }}">
            </div>
        </div>
        <button type="submit" class="btn primary block" style="margin-top:6px;">Create account + generate seats</button>
    </form>
</div>
@endsection

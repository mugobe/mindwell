@extends('layouts.app')
@section('title', 'Your application')
@section('content')
{{-- Therapist onboarding status (2026-11-03). See TherapistApplicationController::status(). --}}
@include('apply._status-body')
@endsection

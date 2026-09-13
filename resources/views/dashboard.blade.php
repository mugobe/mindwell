@extends('layouts.app')
@section('title', 'Dashboard')
@section('content')
<div class="bg-white p-8 rounded-lg shadow-sm">
    <h1 class="text-xl font-semibold mb-2">Welcome, {{ auth()->user()->name }}</h1>
    <p class="text-gray-600">Role: {{ auth()->user()->role }}</p>
</div>
@endsection
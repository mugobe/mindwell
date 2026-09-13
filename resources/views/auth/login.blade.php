@extends('layouts.app')
@section('title', 'Log in')
@section('content')
<div class="bg-white p-8 rounded-lg shadow-sm max-w-md mx-auto">
    <h1 class="text-xl font-semibold mb-6">Log in</h1>

    @if ($errors->any())
        <div class="mb-4 text-sm text-red-600">
            <ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    <form method="POST" action="{{ route('login') }}" class="space-y-4">
        @csrf
        <div>
            <label class="block text-sm font-medium mb-1">Email</label>
            <input type="email" name="email" value="{{ old('email') }}" class="w-full border rounded-md px-3 py-2" required>
        </div>
        <div>
            <label class="block text-sm font-medium mb-1">Password</label>
            <input type="password" name="password" class="w-full border rounded-md px-3 py-2" required>
        </div>
        <button type="submit" class="w-full bg-gray-900 text-white rounded-md py-2 font-medium">Log in</button>
    </form>
    <p class="mt-4 text-sm text-gray-600">No account? <a href="{{ route('register') }}" class="underline">Register</a></p>
</div>
@endsection
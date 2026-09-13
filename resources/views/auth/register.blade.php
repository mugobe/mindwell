@extends('layouts.app')
@section('title', 'Register')
@section('content')
<div class="bg-white p-8 rounded-lg shadow-sm max-w-md mx-auto">
    <h1 class="text-xl font-semibold mb-6">Create an account</h1>

    @if ($errors->any())
        <div class="mb-4 text-sm text-red-600">
            <ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    <form method="POST" action="{{ route('register') }}" class="space-y-4">
        @csrf
        <div>
            <label class="block text-sm font-medium mb-1">Name</label>
            <input type="text" name="name" value="{{ old('name') }}" class="w-full border rounded-md px-3 py-2" required>
        </div>
        <div>
            <label class="block text-sm font-medium mb-1">Email</label>
            <input type="email" name="email" value="{{ old('email') }}" class="w-full border rounded-md px-3 py-2" required>
        </div>
        <div>
            <label class="block text-sm font-medium mb-1">Password</label>
            <input type="password" name="password" class="w-full border rounded-md px-3 py-2" required>
        </div>
        <div>
            <label class="block text-sm font-medium mb-1">Confirm password</label>
            <input type="password" name="password_confirmation" class="w-full border rounded-md px-3 py-2" required>
        </div>
        <button type="submit" class="w-full bg-gray-900 text-white rounded-md py-2 font-medium">Register</button>
    </form>
    <p class="mt-4 text-sm text-gray-600">Already have an account? <a href="{{ route('login') }}" class="underline">Log in</a></p>
</div>
@endsection
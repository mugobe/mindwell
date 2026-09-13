<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mindwell — @yield('title')</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-50 min-h-screen">
    <nav class="bg-white border-b px-6 py-4 flex justify-between items-center">
        <span class="font-semibold text-lg">Mindwell</span>
        @auth
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button class="text-sm text-gray-600 hover:text-gray-900">Log out</button>
            </form>
        @endauth
    </nav>
    <main class="max-w-3xl mx-auto mt-10 px-4">
        @yield('content')
    </main>
</body>
</html>
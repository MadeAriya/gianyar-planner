<!DOCTYPE html>
<html lang="id" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'GianyarPlanner')</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-bg text-text font-inter antialiased min-h-screen flex flex-col">
<header class="w-full border-b border-line bg-white">
    <div class="max-w-[1440px] mx-auto px-6 sm:px-12 lg:px-20 h-20 flex items-center">
        <a href="/" class="font-playfair font-medium text-text text-[26px] tracking-[-0.5px]">GianyarPlanner</a>
    </div>
</header>
<main class="flex-1 flex items-center justify-center px-6 py-16">
    <div class="w-full max-w-[520px]">
        @if(session('success'))
            <x-alert type="success" class="mb-6">{{ session('success') }}</x-alert>
        @endif
        @if(session('error'))
            <x-alert type="error" class="mb-6">{{ session('error') }}</x-alert>
        @endif
        @yield('content')
    </div>
</main>
</body>
</html>

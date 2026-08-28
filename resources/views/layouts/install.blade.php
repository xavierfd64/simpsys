<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ $title ?? config('app.name') }}</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="min-h-screen bg-app-bg font-sans text-ink">
    <div class="flex min-h-screen flex-col items-center px-4 py-10 sm:py-16">
        <div class="mb-8 flex items-center gap-2 text-xl font-semibold text-ink">
            <x-lucide-store class="h-7 w-7 text-primary-600" />
            {{ config('app.name') }}
        </div>

        <div class="w-full max-w-2xl">
            {{ $slot }}
        </div>
    </div>

    @livewireScripts
</body>
</html>

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
<body class="min-h-screen bg-app-bg font-sans text-ink" x-data="{ mobileMenuOpen: false }">
    <header class="border-b border-hairline bg-surface">
        <div class="mx-auto flex h-16 max-w-6xl items-center justify-between px-4 lg:px-6">
            <a href="{{ route('home') }}" class="flex items-center gap-2 text-lg font-semibold">
                <x-lucide-store class="h-6 w-6 text-primary-600" />
                {{ config('app.name') }}
            </a>

            <nav class="hidden items-center gap-8 text-sm font-medium text-muted md:flex">
                <a href="{{ route('home') }}#features" class="hover:text-ink">Features</a>
                <a href="{{ route('pricing') }}" class="hover:text-ink">Pricing</a>
            </nav>

            <div class="hidden items-center gap-3 md:flex">
                <a href="{{ route('login') }}" class="text-sm font-medium text-muted hover:text-ink">Log In</a>
                <a href="{{ route('register') }}" class="rounded-lg bg-primary-600 px-4 py-2 text-sm font-semibold text-white hover:bg-primary-700">
                    Start Free Trial
                </a>
            </div>

            <button type="button" class="text-muted md:hidden" @click="mobileMenuOpen = !mobileMenuOpen">
                <x-lucide-menu class="h-6 w-6" />
            </button>
        </div>

        <div x-cloak x-show="mobileMenuOpen" class="border-t border-hairline px-4 py-4 md:hidden">
            <div class="flex flex-col gap-3 text-sm font-medium">
                <a href="{{ route('home') }}#features" class="text-muted">Features</a>
                <a href="{{ route('pricing') }}" class="text-muted">Pricing</a>
                <a href="{{ route('login') }}" class="text-muted">Log In</a>
                <a href="{{ route('register') }}" class="rounded-lg bg-primary-600 px-4 py-2 text-center font-semibold text-white">Start Free Trial</a>
            </div>
        </div>
    </header>

    <main>
        {{ $slot }}
    </main>

    <footer class="border-t border-hairline bg-surface">
        <div class="mx-auto max-w-6xl px-4 py-8 text-sm text-muted lg:px-6">
            &copy; {{ now()->year }} {{ config('app.name') }}. All rights reserved.
        </div>
    </footer>

    @livewireScripts
</body>
</html>

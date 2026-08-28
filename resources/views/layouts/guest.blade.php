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
    <div class="flex min-h-screen">
        <div class="hidden w-1/2 flex-col justify-between bg-primary-700 p-12 text-white lg:flex">
            <a href="/" class="flex items-center gap-2 text-xl font-semibold">
                <x-lucide-store class="h-7 w-7" />
                {{ config('app.name') }}
            </a>

            <div class="max-w-md space-y-4">
                <h1 class="text-3xl font-semibold leading-tight">
                    Run your food business without the chaos.
                </h1>
                <p class="text-primary-100">
                    Sales, inventory, kitchen orders, expenses, and reports &mdash; simple, fast, and built for
                    small food vendors.
                </p>
            </div>

            <p class="text-sm text-primary-200">&copy; {{ now()->year }} {{ config('app.name') }}</p>
        </div>

        <div class="flex w-full flex-1 flex-col items-center justify-center px-6 py-12 lg:w-1/2">
            <div class="mb-8 flex items-center gap-2 text-xl font-semibold text-ink lg:hidden">
                <x-lucide-store class="h-7 w-7 text-primary-600" />
                {{ config('app.name') }}
            </div>

            <div class="w-full max-w-sm">
                {{ $slot }}
            </div>
        </div>
    </div>

    @livewireScripts
</body>
</html>

@php
    $platform = \App\Models\PlatformSetting::current();
    $adminNavItems = [
        ['route' => 'admin.dashboard', 'match' => 'admin.dashboard', 'label' => 'Dashboard', 'icon' => 'layout-dashboard'],
        ['route' => 'admin.businesses.index', 'match' => 'admin.businesses.*', 'label' => 'Businesses', 'icon' => 'building-2'],
        ['route' => 'admin.plans.index', 'match' => 'admin.plans.*', 'label' => 'Plans', 'icon' => 'tag'],
        ['route' => 'admin.promotions.index', 'match' => 'admin.promotions.*', 'label' => 'Promotions', 'icon' => 'percent'],
        ['route' => 'admin.notifications.index', 'match' => 'admin.notifications.*', 'label' => 'Notifications', 'icon' => 'bell'],
        ['route' => 'admin.branches.index', 'match' => 'admin.branches.*', 'label' => 'Branches', 'icon' => 'building-2'],
        ['route' => 'admin.settings', 'match' => 'admin.settings', 'label' => 'Settings', 'icon' => 'settings'],
    ];
    $adminNavItems = array_filter($adminNavItems, fn ($item) => Route::has($item['route']));
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ $title ?? $platform->displayName().' Admin' }}</title>

    @if ($platform->favicon_path)
        <link rel="icon" href="{{ \App\Support\TenantStorage::url($platform->favicon_path) }}">
    @endif

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="min-h-screen bg-app-bg font-sans text-ink">
    <div class="flex min-h-screen">
        <aside class="hidden w-64 shrink-0 flex-col bg-ink text-slate-200 lg:flex print:hidden">
            <div class="flex h-16 items-center gap-2 border-b border-white/10 px-5 text-lg font-semibold text-white">
                @if ($platform->logo_path)
                    <img src="{{ \App\Support\TenantStorage::url($platform->logo_path) }}" class="h-6 w-6 shrink-0 rounded object-cover" alt="{{ $platform->displayName() }}">
                @else
                    <x-lucide-shield class="h-6 w-6 shrink-0 text-primary-400" />
                @endif
                <span class="truncate">{{ $platform->displayName() }}</span> <span class="text-xs font-normal text-slate-400">Admin</span>
            </div>

            <nav class="flex-1 space-y-1 overflow-y-auto px-3 py-4">
                @foreach ($adminNavItems as $item)
                    @php $isActive = request()->routeIs($item['match']); @endphp
                    <a href="{{ Route::has($item['route']) ? route($item['route']) : '#' }}"
                       class="flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium transition
                              {{ $isActive ? 'bg-white/10 text-white' : 'text-slate-400 hover:bg-white/5 hover:text-white' }}">
                        <x-dynamic-component :component="'lucide-'.$item['icon']" class="h-5 w-5 shrink-0" />
                        {{ $item['label'] }}
                    </a>
                @endforeach
            </nav>

            <div class="border-t border-white/10 p-3">
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="flex w-full items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium text-slate-400 hover:bg-white/5 hover:text-white">
                        <x-lucide-log-out class="h-5 w-5" />
                        Log Out
                    </button>
                </form>
            </div>
        </aside>

        <div class="flex min-w-0 flex-1 flex-col">
            <header class="flex h-16 items-center justify-between border-b border-hairline bg-surface px-4 lg:px-6 print:hidden">
                <div class="text-sm font-medium text-muted">Platform Administration</div>

                <div class="flex items-center gap-3">
                    <span class="hidden text-sm text-muted sm:inline">{{ auth()->user()?->name }}</span>
                    <span class="flex h-9 w-9 items-center justify-center rounded-full bg-ink text-sm font-semibold text-white">
                        {{ Str::of(auth()->user()?->name)->substr(0, 1) }}
                    </span>
                </div>
            </header>

            <main class="flex-1 p-4 lg:p-6">
                {{ $slot }}
            </main>
        </div>
    </div>

    @livewireScripts
</body>
</html>

@php
    use App\Enums\TenantMembershipRole as Role;

    $tenant = app(\App\Services\TenantContext::class)->tenant();
    $role = app(\App\Services\TenantContext::class)->role();

    $navItems = [
        ['route' => 'app.dashboard', 'match' => 'app.dashboard', 'label' => 'Dashboard', 'icon' => 'layout-dashboard', 'roles' => [Role::Owner]],
        ['route' => 'app.pos', 'match' => 'app.pos', 'label' => 'POS', 'icon' => 'shopping-cart', 'roles' => [Role::Owner, Role::Cashier]],
        ['route' => 'app.products.index', 'match' => 'app.products.*', 'label' => 'Products', 'icon' => 'package', 'roles' => [Role::Owner]],
        ['route' => 'app.inventory.index', 'match' => 'app.inventory.*', 'label' => 'Inventory', 'icon' => 'clipboard-list', 'roles' => [Role::Owner]],
        ['route' => 'app.supplies.index', 'match' => 'app.supplies.*', 'label' => 'Supplies', 'icon' => 'tag', 'roles' => [Role::Owner]],
        ['route' => 'app.kitchen', 'match' => 'app.kitchen', 'label' => 'Kitchen', 'icon' => 'chef-hat', 'roles' => [Role::Owner, Role::KitchenStaff]],
        ['route' => 'app.sales.index', 'match' => 'app.sales.*', 'label' => 'Sales', 'icon' => 'receipt', 'roles' => [Role::Owner, Role::Cashier]],
        ['route' => 'app.expenses.index', 'match' => 'app.expenses.*', 'label' => 'Expenses', 'icon' => 'credit-card', 'roles' => [Role::Owner]],
        ['route' => 'app.reports', 'match' => 'app.reports*', 'label' => 'Reports', 'icon' => 'bar-chart', 'roles' => [Role::Owner]],
        ['route' => 'app.users.index', 'match' => 'app.users.*', 'label' => 'Users', 'icon' => 'users', 'roles' => [Role::Owner]],
        ['route' => 'app.settings', 'match' => 'app.settings*', 'label' => 'Settings', 'icon' => 'settings', 'roles' => [Role::Owner]],
    ];

    $kitchenEnabled = $tenant?->settings?->kitchen_enabled ?? true;
    $platform = \App\Models\PlatformSetting::current();

    $visibleNavItems = array_filter($navItems, function ($item) use ($role, $kitchenEnabled) {
        if (! in_array($role, $item['roles'], true) || ! Route::has($item['route'])) {
            return false;
        }

        // Kitchen staff always need their one screen regardless of the
        // setting; the toggle only declutters the owner's own sidebar.
        if ($item['route'] === 'app.kitchen' && ! $kitchenEnabled && $role !== Role::KitchenStaff) {
            return false;
        }

        return true;
    });
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ $title ?? $platform->displayName() }}</title>

    @if ($platform->favicon_path)
        <link rel="icon" href="{{ \App\Support\TenantStorage::url($platform->favicon_path) }}">
    @endif

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="min-h-screen bg-app-bg font-sans text-ink" x-data="{ mobileNavOpen: false }">
    <div class="flex min-h-screen">
        <aside class="hidden w-64 shrink-0 flex-col border-r border-hairline bg-surface lg:flex">
            <div class="flex h-16 items-center gap-2 border-b border-hairline px-5 text-lg font-semibold">
                @if ($tenant?->logo_path)
                    <img src="{{ \App\Support\TenantStorage::url($tenant->logo_path) }}" class="h-7 w-7 shrink-0 rounded object-cover" alt="{{ $tenant->name }}">
                @else
                    <x-lucide-store class="h-6 w-6 shrink-0 text-primary-600" />
                @endif
                <span class="truncate">{{ $tenant?->name ?? config('app.name') }}</span>
            </div>

            <nav class="flex-1 space-y-1 overflow-y-auto px-3 py-4">
                @foreach ($visibleNavItems as $item)
                    @php $isActive = Route::has($item['route']) && request()->routeIs($item['route'].'*'); @endphp
                    <a href="{{ Route::has($item['route']) ? route($item['route']) : '#' }}"
                       class="flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium transition
                              {{ $isActive ? 'bg-primary-50 text-primary-700' : 'text-muted hover:bg-app-bg hover:text-ink' }}">
                        <x-dynamic-component :component="'lucide-'.$item['icon']" class="h-5 w-5 shrink-0" />
                        {{ $item['label'] }}
                    </a>
                @endforeach
            </nav>

            <div class="border-t border-hairline p-3">
                <div class="truncate px-3 py-1 text-xs font-medium uppercase tracking-wide text-muted">
                    {{ $tenant?->name ?? 'Business' }}
                </div>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="flex w-full items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium text-muted hover:bg-app-bg hover:text-ink">
                        <x-lucide-log-out class="h-5 w-5" />
                        Log Out
                    </button>
                </form>
            </div>
        </aside>

        <div class="flex min-w-0 flex-1 flex-col">
            <header class="flex h-16 items-center justify-between border-b border-hairline bg-surface px-4 lg:px-6">
                <button type="button" class="text-muted lg:hidden" @click="mobileNavOpen = true">
                    <x-lucide-menu class="h-6 w-6" />
                </button>

                <div class="text-sm font-medium text-muted">
                    {{ $tenant?->name }}
                </div>

                <div class="flex items-center gap-3">
                    <span class="hidden text-sm text-muted sm:inline">{{ auth()->user()?->name }}</span>
                    <span class="flex h-9 w-9 items-center justify-center rounded-full bg-primary-100 text-sm font-semibold text-primary-700">
                        {{ Str::of(auth()->user()?->name)->substr(0, 1) }}
                    </span>
                </div>
            </header>

            <main class="flex-1 p-4 pb-20 lg:p-6 lg:pb-6">
                {{ $slot }}
            </main>
        </div>

        <div x-cloak x-show="mobileNavOpen" class="fixed inset-0 z-40 lg:hidden">
            <div class="absolute inset-0 bg-black/40" @click="mobileNavOpen = false"></div>
            <aside class="absolute inset-y-0 left-0 flex w-72 flex-col bg-surface shadow-xl">
                <div class="flex h-16 items-center justify-between border-b border-hairline px-5">
                    <span class="text-lg font-semibold">{{ config('app.name') }}</span>
                    <button type="button" class="text-muted" @click="mobileNavOpen = false">
                        <x-lucide-x class="h-5 w-5" />
                    </button>
                </div>
                <nav class="flex-1 space-y-1 overflow-y-auto px-3 py-4">
                    @foreach ($visibleNavItems as $item)
                        <a href="{{ Route::has($item['route']) ? route($item['route']) : '#' }}"
                           class="flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium text-muted hover:bg-app-bg hover:text-ink">
                            <x-dynamic-component :component="'lucide-'.$item['icon']" class="h-5 w-5 shrink-0" />
                            {{ $item['label'] }}
                        </a>
                    @endforeach
                </nav>
            </aside>
        </div>

        <nav class="fixed inset-x-0 bottom-0 z-30 flex border-t border-hairline bg-surface lg:hidden">
            @foreach (array_slice($visibleNavItems, 0, 4) as $item)
                <a href="{{ Route::has($item['route']) ? route($item['route']) : '#' }}"
                   class="flex flex-1 flex-col items-center gap-1 py-2 text-xs font-medium text-muted">
                    <x-dynamic-component :component="'lucide-'.$item['icon']" class="h-5 w-5" />
                    {{ $item['label'] }}
                </a>
            @endforeach
        </nav>
    </div>

    @livewireScripts
</body>
</html>

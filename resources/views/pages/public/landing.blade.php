<?php

use App\Models\SubscriptionPlan;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts.public')] #[Title('BizManager — Simple Business Management for Small Food Vendors')] class extends Component
{
    public $plans;

    public function mount(): void
    {
        $this->plans = SubscriptionPlan::query()->where('is_active', true)->orderBy('sort_order')->get();
    }
}; ?>

<div>
    <section class="mx-auto max-w-6xl px-4 py-20 lg:px-6">
        <div class="max-w-2xl">
            <h1 class="text-4xl font-semibold leading-tight text-ink lg:text-5xl">
                The all-in-one management system for
                <span class="text-primary-600">small food businesses</span>
            </h1>
            <p class="mt-6 text-lg text-muted">
                Manage sales, inventory, kitchen orders, expenses, and reports &mdash; simple, fast, and
                affordable. Built for fishball carts, siomai stalls, milk tea shops, and every small food
                vendor in between.
            </p>
            <div class="mt-8 flex flex-wrap gap-4">
                <a href="{{ route('register') }}" class="rounded-lg bg-primary-600 px-6 py-3 text-sm font-semibold text-white hover:bg-primary-700">
                    Start Free Trial
                </a>
                <a href="{{ route('pricing') }}" class="rounded-lg border border-hairline bg-surface px-6 py-3 text-sm font-semibold text-ink hover:bg-app-bg">
                    View Pricing
                </a>
            </div>
        </div>
    </section>

    <section id="features" class="border-y border-hairline bg-surface py-16">
        <div class="mx-auto max-w-6xl px-4 lg:px-6">
            <h2 class="text-center text-2xl font-semibold text-ink">Everything you need to run your stall</h2>
            <div class="mt-12 grid gap-8 sm:grid-cols-2 lg:grid-cols-4">
                @foreach ([
                    ['icon' => 'shopping-cart', 'title' => 'Easy POS', 'body' => 'Fast, touch-friendly checkout built for busy counters.'],
                    ['icon' => 'clipboard-list', 'title' => 'Inventory Control', 'body' => 'Track sellable stock and supplies without spreadsheets.'],
                    ['icon' => 'chef-hat', 'title' => 'Kitchen Management', 'body' => 'Made-to-order items flow straight to the kitchen screen.'],
                    ['icon' => 'bar-chart', 'title' => 'Reports & Insights', 'body' => 'See sales, expenses, and estimated net income at a glance.'],
                ] as $feature)
                    <div class="text-center">
                        <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-xl bg-primary-50 text-primary-600">
                            <x-dynamic-component :component="'lucide-'.$feature['icon']" class="h-6 w-6" />
                        </div>
                        <h3 class="mt-4 text-base font-semibold text-ink">{{ $feature['title'] }}</h3>
                        <p class="mt-1 text-sm text-muted">{{ $feature['body'] }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    <section class="py-16">
        <div class="mx-auto max-w-6xl px-4 lg:px-6">
            <h2 class="text-center text-2xl font-semibold text-ink">Simple, transparent pricing</h2>
            <div class="mt-12 grid gap-6 md:grid-cols-3">
                @foreach ($plans as $plan)
                    <div class="rounded-2xl border border-hairline bg-surface p-6">
                        <h3 class="text-lg font-semibold text-ink">{{ $plan->name }}</h3>
                        <p class="mt-2 text-3xl font-semibold text-ink">
                            &#8369;{{ number_format($plan->monthly_price) }}
                            <span class="text-sm font-normal text-muted">/month</span>
                        </p>
                        <a href="{{ route('register', ['plan' => $plan->slug]) }}"
                           class="mt-6 block rounded-lg bg-primary-600 px-4 py-2 text-center text-sm font-semibold text-white hover:bg-primary-700">
                            Start Free Trial
                        </a>
                    </div>
                @endforeach
            </div>
            <p class="mt-6 text-center text-sm text-muted">
                <a href="{{ route('pricing') }}" class="font-medium text-primary-600 hover:text-primary-700">Compare all plan features &rarr;</a>
            </p>
        </div>
    </section>
</div>

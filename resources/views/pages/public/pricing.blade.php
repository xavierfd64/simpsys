<?php

use App\Models\SubscriptionPlan;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts.public')] #[Title('Pricing')] class extends Component
{
    public $plans;

    public string $period = 'monthly';

    public function mount(): void
    {
        $this->plans = SubscriptionPlan::query()->where('is_active', true)->orderBy('sort_order')->get();
    }
}; ?>

<div class="mx-auto max-w-6xl px-4 py-16 lg:px-6">
    <div class="text-center">
        <h1 class="text-3xl font-semibold text-ink">Choose the perfect plan for your business</h1>
        <p class="mt-3 text-muted">Start with a 14-day free trial. No credit card required.</p>

        <div class="mx-auto mt-6 inline-flex rounded-lg border border-hairline bg-surface p-1">
            <button type="button" wire:click="$set('period', 'monthly')"
                    class="rounded-md px-4 py-1.5 text-sm font-medium {{ $period === 'monthly' ? 'bg-primary-600 text-white' : 'text-muted' }}">
                Monthly
            </button>
            <button type="button" wire:click="$set('period', 'yearly')"
                    class="rounded-md px-4 py-1.5 text-sm font-medium {{ $period === 'yearly' ? 'bg-primary-600 text-white' : 'text-muted' }}">
                Yearly <span class="text-xs opacity-80">(Save)</span>
            </button>
        </div>
    </div>

    <div class="mt-12 grid gap-6 md:grid-cols-3">
        @foreach ($plans as $index => $plan)
            @php $popular = $index === 1; @endphp
            <div class="relative rounded-2xl border {{ $popular ? 'border-primary-600 shadow-sm' : 'border-hairline' }} bg-surface p-6">
                @if ($popular)
                    <span class="absolute -top-3 left-1/2 -translate-x-1/2 rounded-full bg-primary-600 px-3 py-1 text-xs font-semibold text-white">
                        Most Popular
                    </span>
                @endif

                <h3 class="text-lg font-semibold text-ink">{{ $plan->name }}</h3>
                <p class="mt-2 text-3xl font-semibold text-ink">
                    &#8369;{{ number_format($period === 'yearly' ? $plan->yearly_price : $plan->monthly_price) }}
                    <span class="text-sm font-normal text-muted">/{{ $period === 'yearly' ? 'year' : 'month' }}</span>
                </p>

                <ul class="mt-6 space-y-3 text-sm text-muted">
                    @foreach ($plan->features ?? [] as $feature)
                        <li class="flex items-center gap-2">
                            <x-lucide-check class="h-4 w-4 shrink-0 text-accent-600" />
                            {{ $feature }}
                        </li>
                    @endforeach
                </ul>

                <a href="{{ route('register', ['plan' => $plan->slug]) }}"
                   class="mt-8 block rounded-lg px-4 py-2.5 text-center text-sm font-semibold
                          {{ $popular ? 'bg-primary-600 text-white hover:bg-primary-700' : 'border border-hairline text-ink hover:bg-app-bg' }}">
                    Start Free Trial
                </a>
            </div>
        @endforeach
    </div>
</div>

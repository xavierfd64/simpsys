<?php

use App\Enums\TenantStatus;
use App\Models\SubscriptionPlan;
use App\Models\Tenant;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts.admin')] #[Title('Admin Dashboard')] class extends Component
{
    public function getCountsProperty(): array
    {
        return [
            'total' => Tenant::query()->count(),
            'active' => Tenant::query()->where('status', TenantStatus::Active)->count(),
            'trial' => Tenant::query()->where('status', TenantStatus::Trial)->count(),
            'expired' => Tenant::query()->where('status', TenantStatus::Expired)->count(),
            'suspended' => Tenant::query()->where('status', TenantStatus::Suspended)->count(),
        ];
    }

    public function getRecentRegistrationsProperty()
    {
        return Tenant::query()->latest('id')->limit(5)->get();
    }

    public function getPlanDistributionProperty()
    {
        return SubscriptionPlan::query()
            ->withCount(['subscriptions' => fn ($q) => $q->whereIn('status', ['trial', 'active'])])
            ->orderBy('sort_order')
            ->get();
    }
}; ?>

<div class="space-y-6">
    <div>
        <h1 class="text-2xl font-semibold text-ink">Platform Overview</h1>
        <p class="mt-1 text-sm text-muted">Businesses using BizManager.</p>
    </div>

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
        <div class="rounded-xl border border-hairline bg-surface p-5">
            <p class="text-xs font-medium uppercase text-muted">Total Businesses</p>
            <p class="mt-1 text-2xl font-semibold text-ink">{{ $this->counts['total'] }}</p>
        </div>
        <div class="rounded-xl border border-hairline bg-surface p-5">
            <p class="text-xs font-medium uppercase text-muted">Active</p>
            <p class="mt-1 text-2xl font-semibold text-green-600">{{ $this->counts['active'] }}</p>
        </div>
        <div class="rounded-xl border border-hairline bg-surface p-5">
            <p class="text-xs font-medium uppercase text-muted">Trial</p>
            <p class="mt-1 text-2xl font-semibold text-primary-600">{{ $this->counts['trial'] }}</p>
        </div>
        <div class="rounded-xl border border-hairline bg-surface p-5">
            <p class="text-xs font-medium uppercase text-muted">Expired</p>
            <p class="mt-1 text-2xl font-semibold text-amber-600">{{ $this->counts['expired'] }}</p>
        </div>
        <div class="rounded-xl border border-hairline bg-surface p-5">
            <p class="text-xs font-medium uppercase text-muted">Suspended</p>
            <p class="mt-1 text-2xl font-semibold text-danger-500">{{ $this->counts['suspended'] }}</p>
        </div>
    </div>

    <div class="grid gap-6 lg:grid-cols-2">
        <div class="rounded-xl border border-hairline bg-surface p-6">
            <h3 class="text-sm font-medium text-muted">Recent Registrations</h3>
            <ul class="mt-3 divide-y divide-hairline">
                @forelse ($this->recentRegistrations as $tenant)
                    <li class="flex items-center justify-between py-2 text-sm">
                        <a href="{{ route('admin.businesses.show', $tenant) }}" class="text-ink hover:text-primary-600">{{ $tenant->name }}</a>
                        <span class="text-muted">{{ $tenant->created_at->format('M j, Y') }}</span>
                    </li>
                @empty
                    <li class="py-2 text-sm text-muted">No businesses yet.</li>
                @endforelse
            </ul>
        </div>

        <div class="rounded-xl border border-hairline bg-surface p-6">
            <h3 class="text-sm font-medium text-muted">Plan Distribution</h3>
            <div class="mt-3 space-y-3">
                @foreach ($this->planDistribution as $plan)
                    <div>
                        <div class="flex justify-between text-sm">
                            <span class="text-ink">{{ $plan->name }}</span>
                            <span class="text-muted">{{ $plan->subscriptions_count }}</span>
                        </div>
                        @php $max = max(1, $this->planDistribution->max('subscriptions_count')); @endphp
                        <div class="mt-1 h-2 w-full rounded-full bg-app-bg">
                            <div class="h-2 rounded-full bg-primary-600" style="width: {{ round(($plan->subscriptions_count / $max) * 100) }}%"></div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</div>

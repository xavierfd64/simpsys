<?php

use App\Enums\TenantStatus;
use App\Models\SubscriptionPlan;
use App\Models\Tenant;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts.admin')] #[Title('Admin Dashboard')] class extends Component
{
    public string $period = 'today';

    public string $selectedDate = '';

    public string $selectedMonth = '';

    public string $selectedYear = '';

    public string $dateFrom = '';

    public string $dateTo = '';

    public function mount(): void
    {
        $now = now();
        $this->selectedDate = $now->toDateString();
        $this->selectedMonth = $now->format('Y-m');
        $this->selectedYear = $now->format('Y');
        $this->dateFrom = $now->toDateString();
        $this->dateTo = $now->toDateString();
    }

    public function getPeriodRangeProperty(): array
    {
        $today = now()->toDateString();

        return match ($this->period) {
            'daily' => [$this->selectedDate ?: $today, $this->selectedDate ?: $today],
            'monthly' => [
                \Carbon\Carbon::parse($this->selectedMonth.'-01')->startOfMonth()->toDateString(),
                \Carbon\Carbon::parse($this->selectedMonth.'-01')->endOfMonth()->toDateString(),
            ],
            'yearly' => [
                \Carbon\Carbon::createFromDate((int) $this->selectedYear, 1, 1)->toDateString(),
                \Carbon\Carbon::createFromDate((int) $this->selectedYear, 12, 31)->toDateString(),
            ],
            'custom' => [$this->dateFrom ?: $today, $this->dateTo ?: $today],
            default => [$today, $today],
        };
    }

    public function getPeriodLabelProperty(): string
    {
        [$from, $to] = $this->periodRange;

        return match ($this->period) {
            'daily' => \Carbon\Carbon::parse($from)->format('M j, Y'),
            'monthly' => \Carbon\Carbon::parse($from)->format('F Y'),
            'yearly' => \Carbon\Carbon::parse($from)->format('Y'),
            'custom' => \Carbon\Carbon::parse($from)->format('M j, Y').' – '.\Carbon\Carbon::parse($to)->format('M j, Y'),
            default => 'Today',
        };
    }

    public function getNewRegistrationsProperty(): int
    {
        [$from, $to] = $this->periodRange;

        return Tenant::query()
            ->whereBetween('created_at', [
                \Carbon\Carbon::parse($from)->startOfDay(),
                \Carbon\Carbon::parse($to)->endOfDay(),
            ])
            ->count();
    }

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

    <div class="flex flex-wrap items-center justify-between gap-4 rounded-xl border border-hairline bg-surface p-4">
        <div>
            <p class="text-xs font-medium uppercase text-muted">New Businesses</p>
            <p class="mt-1 text-xl font-semibold text-ink">{{ $this->newRegistrations }} <span class="text-sm font-normal text-muted">({{ $this->periodLabel }})</span></p>
        </div>

        <div class="flex flex-wrap items-end gap-3">
            <div>
                <label class="mb-1 block text-xs font-medium text-muted">Period</label>
                <select wire:model.live="period" class="rounded-lg border border-hairline px-3 py-2 text-sm">
                    <option value="today">Today</option>
                    <option value="daily">Specific Day</option>
                    <option value="monthly">Monthly</option>
                    <option value="yearly">Yearly</option>
                    <option value="custom">Custom Range</option>
                </select>
            </div>
            @if ($period === 'daily')
                <div>
                    <label class="mb-1 block text-xs font-medium text-muted">Date</label>
                    <input wire:model.live="selectedDate" type="date" class="rounded-lg border border-hairline px-3 py-2 text-sm">
                </div>
            @elseif ($period === 'monthly')
                <div>
                    <label class="mb-1 block text-xs font-medium text-muted">Month</label>
                    <input wire:model.live="selectedMonth" type="month" class="rounded-lg border border-hairline px-3 py-2 text-sm">
                </div>
            @elseif ($period === 'yearly')
                <div>
                    <label class="mb-1 block text-xs font-medium text-muted">Year</label>
                    <input wire:model.live="selectedYear" type="number" min="2000" max="{{ now()->year + 1 }}" class="w-24 rounded-lg border border-hairline px-3 py-2 text-sm">
                </div>
            @elseif ($period === 'custom')
                <div>
                    <label class="mb-1 block text-xs font-medium text-muted">From</label>
                    <input wire:model.live="dateFrom" type="date" class="rounded-lg border border-hairline px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-muted">To</label>
                    <input wire:model.live="dateTo" type="date" class="rounded-lg border border-hairline px-3 py-2 text-sm">
                </div>
            @endif
        </div>
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

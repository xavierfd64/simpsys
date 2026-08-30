<?php

use App\Enums\SaleStatus;
use App\Models\Expense;
use App\Models\PlatformNotification;
use App\Models\Product;
use App\Models\Sale;
use App\Models\Supply;
use App\Models\Tenant;
use App\Services\TenantContext;
use App\Support\Money;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts.app')] #[Title('Dashboard')] class extends Component
{
    public string $period = 'today';

    public string $selectedDate = '';

    public string $selectedMonth = '';

    public string $selectedYear = '';

    public string $dateFrom = '';

    public string $dateTo = '';

    public string $branchFilter = 'all';

    public function mount(): void
    {
        $now = now(app(TenantContext::class)->tenant()->timezone);
        $this->selectedDate = $now->toDateString();
        $this->selectedMonth = $now->format('Y-m');
        $this->selectedYear = $now->format('Y');
        $this->dateFrom = $now->toDateString();
        $this->dateTo = $now->toDateString();
    }

    public function getTenantProperty()
    {
        return app(TenantContext::class)->tenant();
    }

    public function getBusinessProperty(): Tenant
    {
        return $this->tenant->businessRoot();
    }

    /**
     * The root tenant plus every branch that has (or had) real operational
     * data — pending/rejected branches never ran, so they're excluded from
     * comparison. A single-branch business always resolves to just [root],
     * which keeps every stat below identical to its pre-multi-branch value.
     */
    public function getComparableBranchesProperty()
    {
        return collect([$this->business])
            ->concat($this->business->branches()->whereIn('branch_status', ['active', 'suspended'])->get());
    }

    /**
     * @return array<int>
     */
    protected function filteredTenantIds(): array
    {
        if ($this->branchFilter !== 'all') {
            return [(int) $this->branchFilter];
        }

        return $this->comparableBranches->pluck('id')->all();
    }

    public function getPeriodRangeProperty(): array
    {
        $today = $this->today->toDateString();

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

    public function getPlatformNoticeProperty()
    {
        return PlatformNotification::query()
            ->forTenant($this->tenant)
            ->where('created_at', '>=', now()->subDays(14))
            ->latest('id')
            ->first();
    }

    public function getTodayProperty(): \Carbon\CarbonInterface
    {
        return now($this->tenant->timezone)->startOfDay();
    }

    public function getTodaysSalesProperty(): int
    {
        [$from, $to] = $this->periodRange;
        [$start, $end] = $this->tenant->localRangeBoundsUtc($from, $to);

        return Sale::forTenants($this->filteredTenantIds())
            ->where('status', SaleStatus::Completed)
            ->whereBetween('created_at', [$start, $end])
            ->sum('total');
    }

    public function getTodaysTransactionsProperty(): int
    {
        [$from, $to] = $this->periodRange;
        [$start, $end] = $this->tenant->localRangeBoundsUtc($from, $to);

        return Sale::forTenants($this->filteredTenantIds())
            ->where('status', SaleStatus::Completed)
            ->whereBetween('created_at', [$start, $end])
            ->count();
    }

    public function getTodaysExpensesProperty(): int
    {
        [$from, $to] = $this->periodRange;

        return Expense::forTenants($this->filteredTenantIds())
            ->whereDate('expense_date', '>=', $from)
            ->whereDate('expense_date', '<=', $to)
            ->sum('amount');
    }

    public function getNetIncomeProperty(): int
    {
        return $this->todaysSales - $this->todaysExpenses;
    }

    public function getLowStockProductsProperty()
    {
        return Product::query()
            ->with('inventory')
            ->where('is_active', true)
            ->where('type', 'ready_to_sell')
            ->whereHas('inventory', fn ($q) => $q->whereColumn('quantity', '<=', 'products.low_stock_threshold'))
            ->limit(5)
            ->get();
    }

    public function getLowStockSuppliesProperty()
    {
        return Supply::query()
            ->with('inventory')
            ->where('is_active', true)
            ->whereHas('inventory', fn ($q) => $q->whereColumn('quantity', '<=', 'supplies.low_stock_threshold'))
            ->limit(5)
            ->get();
    }

    public function getRecentSalesProperty()
    {
        [$from, $to] = $this->periodRange;
        [$start, $end] = $this->tenant->localRangeBoundsUtc($from, $to);

        return Sale::forTenants($this->filteredTenantIds())->with('cashier')->whereBetween('created_at', [$start, $end])->latest('id')->limit(5)->get();
    }

    public function getSalesTrendProperty(): array
    {
        $days = collect(range(6, 0))->map(fn ($i) => $this->today->copy()->subDays($i));
        $tenantIds = $this->filteredTenantIds();

        return $days->map(function ($day) use ($tenantIds) {
            [$start, $end] = $this->tenant->localDayBoundsUtc($day->toDateString());

            $total = Sale::forTenants($tenantIds)
                ->where('status', SaleStatus::Completed)
                ->whereBetween('created_at', [$start, $end])
                ->sum('total');

            return ['label' => $day->format('D'), 'total' => $total];
        })->all();
    }

    /**
     * Per-branch sales/expenses/net income/transactions for the current
     * period — the "Branch Performance" comparison table. Only meaningful
     * (and only rendered) when the business has more than one branch.
     *
     * @return array<int, array{tenant: Tenant, sales: int, expenses: int, net: int, transactions: int}>
     */
    public function getBranchPerformanceProperty(): array
    {
        [$from, $to] = $this->periodRange;

        return $this->comparableBranches->map(function (Tenant $branchTenant) use ($from, $to) {
            [$start, $end] = $branchTenant->localRangeBoundsUtc($from, $to);

            $sales = Sale::forTenant($branchTenant)->where('status', SaleStatus::Completed)->whereBetween('created_at', [$start, $end])->sum('total');
            $transactions = Sale::forTenant($branchTenant)->where('status', SaleStatus::Completed)->whereBetween('created_at', [$start, $end])->count();
            $expenses = Expense::forTenant($branchTenant)->whereDate('expense_date', '>=', $from)->whereDate('expense_date', '<=', $to)->sum('amount');

            return [
                'tenant' => $branchTenant,
                'sales' => $sales,
                'expenses' => $expenses,
                'net' => $sales - $expenses,
                'transactions' => $transactions,
            ];
        })->all();
    }

    public function getBestBranchIdProperty(): ?int
    {
        if (count($this->branchPerformance) < 2) {
            return null;
        }

        return collect($this->branchPerformance)->sortByDesc('net')->first()['tenant']->id;
    }

    public function getWorstBranchIdProperty(): ?int
    {
        if (count($this->branchPerformance) < 2) {
            return null;
        }

        return collect($this->branchPerformance)->sortBy('net')->first()['tenant']->id;
    }
}; ?>

<div class="space-y-6">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <h1 class="text-2xl font-semibold text-ink">Good {{ now($this->tenant->timezone)->hour < 12 ? 'morning' : 'day' }}, {{ Auth::user()->name }}</h1>
            <p class="mt-1 text-sm text-muted">
                @if ($period === 'today')
                    Here's what's happening in your business today, {{ $this->today->format('M j, Y') }}.
                @else
                    Showing your business performance for {{ $this->periodLabel }}.
                @endif
            </p>
        </div>

        <div class="flex flex-wrap items-end gap-3">
            @if ($this->comparableBranches->count() > 1)
                <div>
                    <label class="mb-1 block text-xs font-medium text-muted">Branch</label>
                    <select wire:model.live="branchFilter" class="rounded-lg border border-hairline px-3 py-2 text-sm">
                        <option value="all">All Branches</option>
                        @foreach ($this->comparableBranches as $branchTenant)
                            <option value="{{ $branchTenant->id }}">{{ $branchTenant->name }}</option>
                        @endforeach
                    </select>
                </div>
            @endif
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

    @if ($this->platformNotice)
        <div class="flex items-start gap-3 rounded-xl border border-primary-200 bg-primary-50 p-4">
            <x-lucide-bell class="mt-0.5 h-5 w-5 shrink-0 text-primary-600" />
            <div>
                <p class="text-sm font-semibold text-primary-700">{{ $this->platformNotice->title }}</p>
                <p class="mt-0.5 text-sm text-primary-700">{{ $this->platformNotice->message }}</p>
            </div>
        </div>
    @endif

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <div class="rounded-xl border border-hairline bg-surface p-5">
            <p class="text-xs font-medium uppercase text-muted">{{ $period === 'today' ? "Today's Sales" : 'Sales' }}</p>
            <p class="mt-1 text-2xl font-semibold text-ink">{{ Money::format($this->todaysSales) }}</p>
        </div>
        <div class="rounded-xl border border-hairline bg-surface p-5">
            <p class="text-xs font-medium uppercase text-muted">Transactions</p>
            <p class="mt-1 text-2xl font-semibold text-ink">{{ $this->todaysTransactions }}</p>
        </div>
        <div class="rounded-xl border border-hairline bg-surface p-5">
            <p class="text-xs font-medium uppercase text-muted">{{ $period === 'today' ? "Today's Expenses" : 'Expenses' }}</p>
            <p class="mt-1 text-2xl font-semibold text-ink">{{ Money::format($this->todaysExpenses) }}</p>
        </div>
        <div class="rounded-xl border border-hairline bg-surface p-5">
            <p class="text-xs font-medium uppercase text-muted">Est. Net Income</p>
            <p class="mt-1 text-2xl font-semibold {{ $this->netIncome < 0 ? 'text-danger-500' : 'text-ink' }}">{{ Money::format($this->netIncome) }}</p>
        </div>
    </div>

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="rounded-xl border border-hairline bg-surface p-6 lg:col-span-2">
            <h3 class="text-sm font-medium text-muted">Sales Trend (7 days)</h3>
            @php $max = max(1, collect($this->salesTrend)->max('total')); @endphp
            <div class="mt-4 flex items-end gap-3" style="height: 140px;">
                @foreach ($this->salesTrend as $day)
                    <div class="flex flex-1 flex-col items-center gap-2">
                        <div class="w-full rounded-t bg-primary-200" style="height: {{ max(4, round(($day['total'] / $max) * 100)) }}px;"
                             title="{{ Money::format($day['total']) }}"></div>
                        <span class="text-xs text-muted">{{ $day['label'] }}</span>
                    </div>
                @endforeach
            </div>
        </div>

        <div class="space-y-6">
            <div class="rounded-xl border border-hairline bg-surface p-6">
                <h3 class="text-sm font-medium text-muted">Low Product Inventory</h3>
                <ul class="mt-3 space-y-2 text-sm">
                    @forelse ($this->lowStockProducts as $product)
                        <li class="flex justify-between">
                            <span class="text-ink">{{ $product->name }}</span>
                            <span class="font-medium text-danger-500">{{ $product->currentStock() }} left</span>
                        </li>
                    @empty
                        <li class="text-muted">All good — no low stock products.</li>
                    @endforelse
                </ul>
            </div>

            <div class="rounded-xl border border-hairline bg-surface p-6">
                <h3 class="text-sm font-medium text-muted">Low Supplies</h3>
                <ul class="mt-3 space-y-2 text-sm">
                    @forelse ($this->lowStockSupplies as $supply)
                        <li class="flex justify-between">
                            <span class="text-ink">{{ $supply->name }}</span>
                            <span class="font-medium text-danger-500">{{ $supply->currentStock() }} {{ $supply->unit }}</span>
                        </li>
                    @empty
                        <li class="text-muted">All good — no low supplies.</li>
                    @endforelse
                </ul>
            </div>
        </div>
    </div>

    @if ($this->comparableBranches->count() > 1)
        <div class="rounded-xl border border-hairline bg-surface">
            <div class="border-b border-hairline p-4">
                <h3 class="text-sm font-medium text-ink">Branch Performance</h3>
                <p class="mt-0.5 text-xs text-muted">{{ $this->periodLabel }}</p>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full min-w-[640px] text-left text-sm">
                    <thead class="border-b border-hairline text-xs font-medium uppercase text-muted">
                        <tr>
                            <th class="px-4 py-3">Branch</th>
                            <th class="px-4 py-3 text-right">Sales</th>
                            <th class="px-4 py-3 text-right">Expenses</th>
                            <th class="px-4 py-3 text-right">Net Income</th>
                            <th class="px-4 py-3 text-right">Transactions</th>
                            <th class="px-4 py-3">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-hairline">
                        @foreach ($this->branchPerformance as $row)
                            <tr>
                                <td class="px-4 py-3 font-medium text-ink">
                                    {{ $row['tenant']->name }}
                                    @if ($row['tenant']->id === $this->bestBranchId)
                                        <span class="ml-1 rounded-full bg-green-50 px-2 py-0.5 text-xs font-medium text-green-700">Best</span>
                                    @elseif ($row['tenant']->id === $this->worstBranchId)
                                        <span class="ml-1 rounded-full bg-amber-50 px-2 py-0.5 text-xs font-medium text-amber-700">Needs Attention</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-right text-ink">{{ Money::format($row['sales']) }}</td>
                                <td class="px-4 py-3 text-right text-ink">{{ Money::format($row['expenses']) }}</td>
                                <td class="px-4 py-3 text-right {{ $row['net'] < 0 ? 'text-danger-500' : 'text-ink' }}">{{ Money::format($row['net']) }}</td>
                                <td class="px-4 py-3 text-right text-ink">{{ $row['transactions'] }}</td>
                                <td class="px-4 py-3">
                                    @if ($row['tenant']->isBranch())
                                        <span class="rounded-full px-2 py-1 text-xs font-medium {{ $row['tenant']->branch_status->badgeClasses() }}">{{ $row['tenant']->branch_status->label() }}</span>
                                    @else
                                        <span class="rounded-full px-2 py-1 text-xs font-medium {{ $row['tenant']->status->badgeClasses() }}">{{ $row['tenant']->status->label() }}</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    <div class="rounded-xl border border-hairline bg-surface">
        <div class="flex items-center justify-between border-b border-hairline p-4">
            <h3 class="text-sm font-medium text-ink">Recent Transactions</h3>
            <a href="{{ route('app.sales.index') }}" class="text-sm font-medium text-primary-600 hover:text-primary-700">View all</a>
        </div>
        <table class="w-full text-left text-sm">
            <tbody class="divide-y divide-hairline">
                @forelse ($this->recentSales as $sale)
                    <tr>
                        <td class="px-4 py-3 font-medium text-ink">{{ $sale->displayNumber() }}</td>
                        <td class="px-4 py-3 text-muted">{{ $sale->cashier?->name }}</td>
                        <td class="px-4 py-3 text-muted">{{ $sale->created_at->timezone($this->tenant->timezone)->format('g:i A') }}</td>
                        <td class="px-4 py-3 text-right text-ink">{{ Money::format($sale->total) }}</td>
                    </tr>
                @empty
                    <tr><td class="px-4 py-8 text-center text-muted">No transactions yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<?php

use App\Enums\SaleStatus;
use App\Models\Expense;
use App\Models\Product;
use App\Models\Sale;
use App\Models\Supply;
use App\Services\TenantContext;
use App\Support\Money;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts.app')] #[Title('Dashboard')] class extends Component
{
    public function getTenantProperty()
    {
        return app(TenantContext::class)->tenant();
    }

    public function getTodayProperty(): \Carbon\CarbonInterface
    {
        return now($this->tenant->timezone)->startOfDay();
    }

    public function getTodaysSalesProperty(): int
    {
        return Sale::query()
            ->where('status', SaleStatus::Completed)
            ->whereDate('created_at', $this->today->toDateString())
            ->sum('total');
    }

    public function getTodaysTransactionsProperty(): int
    {
        return Sale::query()
            ->where('status', SaleStatus::Completed)
            ->whereDate('created_at', $this->today->toDateString())
            ->count();
    }

    public function getTodaysExpensesProperty(): int
    {
        return Expense::query()->whereDate('expense_date', $this->today->toDateString())->sum('amount');
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
        return Sale::query()->with('cashier')->latest('id')->limit(5)->get();
    }

    public function getSalesTrendProperty(): array
    {
        $days = collect(range(6, 0))->map(fn ($i) => $this->today->copy()->subDays($i));

        return $days->map(function ($day) {
            $total = Sale::query()
                ->where('status', SaleStatus::Completed)
                ->whereDate('created_at', $day->toDateString())
                ->sum('total');

            return ['label' => $day->format('D'), 'total' => $total];
        })->all();
    }
}; ?>

<div class="space-y-6">
    <div>
        <h1 class="text-2xl font-semibold text-ink">Good {{ now($this->tenant->timezone)->hour < 12 ? 'morning' : 'day' }}, {{ Auth::user()->name }}</h1>
        <p class="mt-1 text-sm text-muted">Here's what's happening in your business today, {{ $this->today->format('M j, Y') }}.</p>
    </div>

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <div class="rounded-xl border border-hairline bg-surface p-5">
            <p class="text-xs font-medium uppercase text-muted">Today's Sales</p>
            <p class="mt-1 text-2xl font-semibold text-ink">{{ Money::format($this->todaysSales) }}</p>
        </div>
        <div class="rounded-xl border border-hairline bg-surface p-5">
            <p class="text-xs font-medium uppercase text-muted">Transactions</p>
            <p class="mt-1 text-2xl font-semibold text-ink">{{ $this->todaysTransactions }}</p>
        </div>
        <div class="rounded-xl border border-hairline bg-surface p-5">
            <p class="text-xs font-medium uppercase text-muted">Today's Expenses</p>
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

<?php

use App\Enums\ProductType;
use App\Enums\SaleStatus;
use App\Models\Expense;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Supply;
use App\Services\TenantContext;
use App\Support\Money;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts.app')] #[Title('Reports')] class extends Component
{
    public string $tab = 'sales';

    public string $dateFrom = '';

    public string $dateTo = '';

    public function mount(): void
    {
        $tenant = app(TenantContext::class)->tenant();
        $this->dateFrom = now($tenant->timezone)->startOfMonth()->toDateString();
        $this->dateTo = now($tenant->timezone)->toDateString();
    }

    protected function salesInRange()
    {
        return Sale::query()
            ->whereDate('created_at', '>=', $this->dateFrom)
            ->whereDate('created_at', '<=', $this->dateTo);
    }

    public function getSalesSummaryProperty(): array
    {
        $completed = (clone $this->salesInRange())->where('status', SaleStatus::Completed);

        $totalSales = (clone $completed)->sum('total');
        $transactions = (clone $completed)->count();
        $voided = (clone $this->salesInRange())->where('status', SaleStatus::Voided)->count();

        $byPaymentMethod = (clone $completed)
            ->selectRaw('payment_method_name, sum(total) as total')
            ->groupBy('payment_method_name')
            ->orderByDesc('total')
            ->get();

        return [
            'total' => $totalSales,
            'transactions' => $transactions,
            'average' => $transactions > 0 ? intdiv($totalSales, $transactions) : 0,
            'voided' => $voided,
            'byPaymentMethod' => $byPaymentMethod,
        ];
    }

    public function getProductReportProperty()
    {
        $saleIds = (clone $this->salesInRange())->where('status', SaleStatus::Completed)->pluck('id');

        return SaleItem::query()
            ->whereIn('sale_id', $saleIds)
            ->selectRaw('product_name, sum(quantity) as quantity_sold, sum(line_total) as revenue')
            ->groupBy('product_name')
            ->orderByDesc('revenue')
            ->get();
    }

    public function getInventoryReportProperty()
    {
        return Product::query()
            ->with('inventory')
            ->where('type', ProductType::ReadyToSell)
            ->orderBy('name')
            ->get();
    }

    public function getSuppliesReportProperty()
    {
        return Supply::query()->with('inventory')->orderBy('name')->get();
    }

    public function getExpenseReportProperty(): array
    {
        $expenses = Expense::query()
            ->with('category')
            ->whereDate('expense_date', '>=', $this->dateFrom)
            ->whereDate('expense_date', '<=', $this->dateTo);

        $total = (clone $expenses)->sum('amount');

        $byCategory = (clone $expenses)
            ->selectRaw('expense_category_id, sum(amount) as total')
            ->groupBy('expense_category_id')
            ->with('category')
            ->orderByDesc('total')
            ->get();

        return ['total' => $total, 'byCategory' => $byCategory];
    }

    public function getNetIncomeProperty(): int
    {
        return $this->salesSummary['total'] - $this->expenseReport['total'];
    }
}; ?>

<div class="space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-2xl font-semibold text-ink">Reports</h1>
            <p class="mt-1 text-sm text-muted">Business performance at a glance.</p>
        </div>
        @if (in_array($tab, ['sales', 'products', 'expenses']))
            <div class="flex items-end gap-3">
                <div>
                    <label class="mb-1 block text-xs font-medium text-muted">From</label>
                    <input wire:model.live="dateFrom" type="date" class="rounded-lg border border-hairline px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-muted">To</label>
                    <input wire:model.live="dateTo" type="date" class="rounded-lg border border-hairline px-3 py-2 text-sm">
                </div>
            </div>
        @endif
    </div>

    <div class="flex gap-1 overflow-x-auto border-b border-hairline">
        @foreach (['sales' => 'Sales', 'products' => 'Products', 'inventory' => 'Inventory', 'supplies' => 'Supplies', 'expenses' => 'Expenses'] as $key => $label)
            <button type="button" wire:click="$set('tab', '{{ $key }}')"
                    class="shrink-0 border-b-2 px-4 py-2 text-sm font-medium {{ $tab === $key ? 'border-primary-600 text-primary-600' : 'border-transparent text-muted hover:text-ink' }}">
                {{ $label }}
            </button>
        @endforeach
    </div>

    @if ($tab === 'sales')
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div class="rounded-xl border border-hairline bg-surface p-5">
                <p class="text-xs font-medium uppercase text-muted">Total Sales</p>
                <p class="mt-1 text-2xl font-semibold text-ink">{{ Money::format($this->salesSummary['total']) }}</p>
            </div>
            <div class="rounded-xl border border-hairline bg-surface p-5">
                <p class="text-xs font-medium uppercase text-muted">Transactions</p>
                <p class="mt-1 text-2xl font-semibold text-ink">{{ $this->salesSummary['transactions'] }}</p>
            </div>
            <div class="rounded-xl border border-hairline bg-surface p-5">
                <p class="text-xs font-medium uppercase text-muted">Average Sale</p>
                <p class="mt-1 text-2xl font-semibold text-ink">{{ Money::format($this->salesSummary['average']) }}</p>
            </div>
            <div class="rounded-xl border border-hairline bg-surface p-5">
                <p class="text-xs font-medium uppercase text-muted">Voided Sales</p>
                <p class="mt-1 text-2xl font-semibold text-ink">{{ $this->salesSummary['voided'] }}</p>
            </div>
        </div>

        <div class="rounded-xl border border-hairline bg-surface p-6">
            <h3 class="text-sm font-medium text-muted">Payment Breakdown</h3>
            <div class="mt-4 space-y-3">
                @forelse ($this->salesSummary['byPaymentMethod'] as $row)
                    @php $pct = $this->salesSummary['total'] > 0 ? round(($row->total / $this->salesSummary['total']) * 100) : 0; @endphp
                    <div>
                        <div class="flex justify-between text-sm">
                            <span class="text-ink">{{ $row->payment_method_name }}</span>
                            <span class="text-muted">{{ Money::format($row->total) }} ({{ $pct }}%)</span>
                        </div>
                        <div class="mt-1 h-2 w-full rounded-full bg-app-bg">
                            <div class="h-2 rounded-full bg-primary-600" style="width: {{ $pct }}%"></div>
                        </div>
                    </div>
                @empty
                    <p class="text-sm text-muted">No completed sales in this range.</p>
                @endforelse
            </div>
        </div>
    @endif

    @if ($tab === 'products')
        <div class="overflow-x-auto rounded-xl border border-hairline bg-surface">
            <table class="w-full min-w-[500px] text-left text-sm">
                <thead class="border-b border-hairline bg-app-bg text-xs font-medium uppercase tracking-wide text-muted">
                    <tr>
                        <th class="px-4 py-3">Product</th>
                        <th class="px-4 py-3">Qty Sold</th>
                        <th class="px-4 py-3 text-right">Revenue</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-hairline">
                    @forelse ($this->productReport as $row)
                        <tr>
                            <td class="px-4 py-3 text-ink">{{ $row->product_name }}</td>
                            <td class="px-4 py-3 text-muted">{{ $row->quantity_sold }}</td>
                            <td class="px-4 py-3 text-right text-ink">{{ Money::format($row->revenue) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="px-4 py-12 text-center text-muted">No sales in this range.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    @endif

    @if ($tab === 'inventory')
        <div class="overflow-x-auto rounded-xl border border-hairline bg-surface">
            <table class="w-full min-w-[500px] text-left text-sm">
                <thead class="border-b border-hairline bg-app-bg text-xs font-medium uppercase tracking-wide text-muted">
                    <tr>
                        <th class="px-4 py-3">Product</th>
                        <th class="px-4 py-3">Stock</th>
                        <th class="px-4 py-3">Alert At</th>
                        <th class="px-4 py-3">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-hairline">
                    @forelse ($this->inventoryReport as $product)
                        <tr>
                            <td class="px-4 py-3 text-ink">{{ $product->name }}</td>
                            <td class="px-4 py-3 text-muted">{{ $product->currentStock() }}</td>
                            <td class="px-4 py-3 text-muted">{{ $product->low_stock_threshold }}</td>
                            <td class="px-4 py-3">
                                @if ($product->isLowStock())
                                    <span class="rounded-full bg-red-50 px-2 py-0.5 text-xs font-medium text-danger-500">Low</span>
                                @else
                                    <span class="rounded-full bg-green-50 px-2 py-0.5 text-xs font-medium text-green-700">OK</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="px-4 py-12 text-center text-muted">No ready-to-sell products.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    @endif

    @if ($tab === 'supplies')
        <div class="overflow-x-auto rounded-xl border border-hairline bg-surface">
            <table class="w-full min-w-[500px] text-left text-sm">
                <thead class="border-b border-hairline bg-app-bg text-xs font-medium uppercase tracking-wide text-muted">
                    <tr>
                        <th class="px-4 py-3">Supply</th>
                        <th class="px-4 py-3">Stock</th>
                        <th class="px-4 py-3">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-hairline">
                    @forelse ($this->suppliesReport as $supply)
                        <tr>
                            <td class="px-4 py-3 text-ink">{{ $supply->name }}</td>
                            <td class="px-4 py-3 text-muted">{{ $supply->currentStock() }} {{ $supply->unit }}</td>
                            <td class="px-4 py-3">
                                @if ($supply->isLowStock())
                                    <span class="rounded-full bg-red-50 px-2 py-0.5 text-xs font-medium text-danger-500">Low</span>
                                @else
                                    <span class="rounded-full bg-green-50 px-2 py-0.5 text-xs font-medium text-green-700">OK</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="px-4 py-12 text-center text-muted">No supplies yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    @endif

    @if ($tab === 'expenses')
        <div class="grid gap-4 sm:grid-cols-2">
            <div class="rounded-xl border border-hairline bg-surface p-5">
                <p class="text-xs font-medium uppercase text-muted">Total Expenses</p>
                <p class="mt-1 text-2xl font-semibold text-ink">{{ Money::format($this->expenseReport['total']) }}</p>
            </div>
            <div class="rounded-xl border border-hairline bg-surface p-5">
                <p class="text-xs font-medium uppercase text-muted">Estimated Net Income</p>
                <p class="mt-1 text-2xl font-semibold {{ $this->netIncome < 0 ? 'text-danger-500' : 'text-ink' }}">{{ Money::format($this->netIncome) }}</p>
            </div>
        </div>

        <div class="rounded-xl border border-hairline bg-surface p-6">
            <h3 class="text-sm font-medium text-muted">By Category</h3>
            <div class="mt-4 space-y-2 text-sm">
                @forelse ($this->expenseReport['byCategory'] as $row)
                    <div class="flex justify-between">
                        <span class="text-ink">{{ $row->category?->name ?? 'Uncategorized' }}</span>
                        <span class="text-muted">{{ Money::format($row->total) }}</span>
                    </div>
                @empty
                    <p class="text-muted">No expenses in this range.</p>
                @endforelse
            </div>
        </div>
    @endif
</div>

<?php

use App\Models\Sale;
use App\Services\TenantContext;
use App\Support\Money;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Layout('layouts.app')] #[Title('Sales')] class extends Component
{
    use WithPagination;

    public string $status = '';

    public string $dateFrom = '';

    public string $dateTo = '';

    public function mount(): void
    {
        $tenant = app(TenantContext::class)->tenant();
        $this->dateFrom = now($tenant->timezone)->startOfMonth()->toDateString();
        $this->dateTo = now($tenant->timezone)->toDateString();
    }

    public function updating(): void
    {
        $this->resetPage();
    }

    public function getSalesProperty()
    {
        $tenant = app(TenantContext::class)->tenant();
        $isOwner = app(TenantContext::class)->hasRole(\App\Enums\TenantMembershipRole::Owner);

        return Sale::query()
            ->with('cashier')
            ->when(! $isOwner, fn ($q) => $q->where('cashier_id', auth()->id()))
            ->when($this->status, fn ($q) => $q->where('status', $this->status))
            ->when($this->dateFrom, fn ($q) => $q->whereDate('created_at', '>=', $this->dateFrom))
            ->when($this->dateTo, fn ($q) => $q->whereDate('created_at', '<=', $this->dateTo))
            ->latest('id')
            ->paginate(15);
    }
}; ?>

<div class="space-y-6">
    <div>
        <h1 class="text-2xl font-semibold text-ink">Sales</h1>
        <p class="mt-1 text-sm text-muted">Transaction history.</p>
    </div>

    <div class="flex flex-wrap items-end gap-3">
        <div>
            <label class="mb-1 block text-xs font-medium text-muted">From</label>
            <input wire:model.live="dateFrom" type="date" class="rounded-lg border border-hairline px-3 py-2 text-sm">
        </div>
        <div>
            <label class="mb-1 block text-xs font-medium text-muted">To</label>
            <input wire:model.live="dateTo" type="date" class="rounded-lg border border-hairline px-3 py-2 text-sm">
        </div>
        <div>
            <label class="mb-1 block text-xs font-medium text-muted">Status</label>
            <select wire:model.live="status" class="rounded-lg border border-hairline px-3 py-2 text-sm">
                <option value="">All</option>
                <option value="completed">Completed</option>
                <option value="voided">Voided</option>
            </select>
        </div>
    </div>

    <div class="overflow-x-auto rounded-xl border border-hairline bg-surface">
        <table class="w-full min-w-[760px] text-left text-sm">
            <thead class="border-b border-hairline bg-app-bg text-xs font-medium uppercase tracking-wide text-muted">
                <tr>
                    <th class="px-4 py-3">Order #</th>
                    <th class="px-4 py-3">Date</th>
                    <th class="px-4 py-3">Cashier</th>
                    <th class="px-4 py-3">Order Type</th>
                    <th class="px-4 py-3">Payment</th>
                    <th class="px-4 py-3">Total</th>
                    <th class="px-4 py-3">Status</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-hairline">
                @forelse ($this->sales as $sale)
                    <tr class="cursor-pointer hover:bg-app-bg" onclick="window.location='{{ route('app.sales.show', $sale) }}'">
                        <td class="px-4 py-3 font-medium text-ink">{{ $sale->displayNumber() }}</td>
                        <td class="px-4 py-3 text-muted">{{ $sale->created_at->timezone($sale->tenant->timezone)->format('M j, Y g:i A') }}</td>
                        <td class="px-4 py-3 text-muted">{{ $sale->cashier?->name }}</td>
                        <td class="px-4 py-3 text-muted">{{ $sale->order_type?->label() ?? '—' }}</td>
                        <td class="px-4 py-3 text-muted">{{ $sale->payment_method_name }}</td>
                        <td class="px-4 py-3 text-ink">{{ Money::format($sale->total) }}</td>
                        <td class="px-4 py-3">
                            @if ($sale->status->value === 'voided')
                                <span class="rounded-full bg-red-50 px-2.5 py-1 text-xs font-medium text-danger-500">Voided</span>
                            @else
                                <span class="rounded-full bg-green-50 px-2.5 py-1 text-xs font-medium text-green-700">Completed</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-4 py-12 text-center text-muted">No sales in this date range.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $this->sales->links() }}
</div>

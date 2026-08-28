<?php

use App\Enums\TenantMembershipRole;
use App\Models\Sale;
use App\Services\TenantContext;
use App\Services\VoidSaleService;
use App\Support\Money;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts.app')] #[Title('Sale Details')] class extends Component
{
    public Sale $sale;

    public bool $showVoidModal = false;

    public string $void_reason = '';

    public function mount(Sale $sale): void
    {
        $sale = Sale::with(['items', 'cashier', 'voidedBy', 'kitchenOrder.items'])->findOrFail($sale->id);

        $isOwner = app(TenantContext::class)->hasRole(TenantMembershipRole::Owner);

        if (! $isOwner && $sale->cashier_id !== Auth::id()) {
            abort(403);
        }

        $this->sale = $sale;
    }

    public function voidSale(VoidSaleService $voidService): void
    {
        if (! app(TenantContext::class)->hasRole(TenantMembershipRole::Owner)) {
            abort(403);
        }

        $this->validate(['void_reason' => ['required', 'string', 'max:255']]);

        $voidService->void($this->sale, Auth::user(), $this->void_reason);

        $this->sale = Sale::with(['items', 'cashier', 'voidedBy', 'kitchenOrder.items'])->findOrFail($this->sale->id);
        $this->showVoidModal = false;
        session()->flash('status', 'Sale voided.');
    }
}; ?>

<div class="max-w-3xl space-y-6">
    <div class="flex items-center gap-3">
        <a href="{{ route('app.sales.index') }}" class="text-muted hover:text-ink">
            <x-lucide-arrow-left class="h-5 w-5" />
        </a>
        <h1 class="text-2xl font-semibold text-ink">Sale {{ $sale->displayNumber() }}</h1>
        @if ($sale->status->value === 'voided')
            <span class="rounded-full bg-red-50 px-2.5 py-1 text-xs font-medium text-danger-500">Voided</span>
        @else
            <span class="rounded-full bg-green-50 px-2.5 py-1 text-xs font-medium text-green-700">Completed</span>
        @endif
    </div>

    @if (session('status'))
        <div class="rounded-lg bg-green-50 px-4 py-3 text-sm text-green-700">{{ session('status') }}</div>
    @endif

    <div class="grid gap-6 md:grid-cols-3">
        <div class="rounded-xl border border-hairline bg-surface p-6 md:col-span-2">
            <h3 class="text-sm font-medium text-muted">Items</h3>
            <table class="mt-3 w-full text-left text-sm">
                <thead class="text-xs font-medium uppercase tracking-wide text-muted">
                    <tr>
                        <th class="pb-2">Product</th>
                        <th class="pb-2">Qty</th>
                        <th class="pb-2">Price</th>
                        <th class="pb-2 text-right">Total</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-hairline">
                    @foreach ($sale->items as $item)
                        <tr>
                            <td class="py-2 text-ink">{{ $item->product_name }}</td>
                            <td class="py-2 text-muted">{{ $item->quantity }}</td>
                            <td class="py-2 text-muted">{{ Money::format($item->unit_price) }}</td>
                            <td class="py-2 text-right text-ink">{{ Money::format($item->line_total) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            @if ($sale->kitchenOrder)
                <div class="mt-4 rounded-lg bg-app-bg p-3 text-sm text-muted">
                    Kitchen Order {{ $sale->kitchenOrder->displayNumber() }} &mdash; {{ $sale->kitchenOrder->status->label() }}
                </div>
            @endif

            @if ($sale->status->value === 'voided')
                <div class="mt-4 rounded-lg bg-red-50 p-4 text-sm text-danger-500">
                    <p class="font-medium">Voided by {{ $sale->voidedBy?->name }} on {{ $sale->voided_at?->timezone($sale->tenant->timezone)->format('M j, Y g:i A') }}</p>
                    <p class="mt-1">Reason: {{ $sale->void_reason }}</p>
                </div>
            @endif
        </div>

        <div class="space-y-4">
            <div class="rounded-xl border border-hairline bg-surface p-6">
                <dl class="space-y-2 text-sm">
                    <div class="flex justify-between"><dt class="text-muted">Date</dt><dd class="text-ink">{{ $sale->created_at->timezone($sale->tenant->timezone)->format('M j, Y g:i A') }}</dd></div>
                    <div class="flex justify-between"><dt class="text-muted">Cashier</dt><dd class="text-ink">{{ $sale->cashier?->name }}</dd></div>
                    <div class="flex justify-between"><dt class="text-muted">Order Type</dt><dd class="text-ink">{{ $sale->order_type?->label() ?? '—' }}</dd></div>
                    <div class="flex justify-between"><dt class="text-muted">Payment Method</dt><dd class="text-ink">{{ $sale->payment_method_name }}</dd></div>
                    <div class="flex justify-between border-t border-hairline pt-2"><dt class="text-muted">Total</dt><dd class="font-semibold text-ink">{{ Money::format($sale->total) }}</dd></div>
                    <div class="flex justify-between"><dt class="text-muted">Amount Received</dt><dd class="text-ink">{{ Money::format($sale->amount_received) }}</dd></div>
                    <div class="flex justify-between"><dt class="text-muted">Change</dt><dd class="text-ink">{{ Money::format($sale->change_amount) }}</dd></div>
                </dl>
            </div>

            @if ($sale->status->value === 'completed' && app(\App\Services\TenantContext::class)->hasRole(\App\Enums\TenantMembershipRole::Owner))
                <button type="button" wire:click="$set('showVoidModal', true)"
                        class="w-full rounded-lg border border-danger-500 px-4 py-2 text-sm font-semibold text-danger-500 hover:bg-red-50">
                    Void Sale
                </button>
            @endif
        </div>
    </div>

    @if ($showVoidModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
            <div class="w-full max-w-md rounded-xl bg-surface p-6 shadow-xl">
                <h2 class="text-lg font-semibold text-ink">Void Sale {{ $sale->displayNumber() }}</h2>
                <p class="mt-1 text-sm text-muted">This will restore inventory and cannot be undone.</p>

                <form wire:submit="voidSale" class="mt-4 space-y-4">
                    <div>
                        <label class="mb-1 block text-sm font-medium text-ink">Reason</label>
                        <textarea wire:model="void_reason" rows="3" class="w-full rounded-lg border border-hairline px-3 py-2 text-sm"></textarea>
                        @error('void_reason') <p class="mt-1 text-sm text-danger-500">{{ $message }}</p> @enderror
                    </div>
                    <div class="flex justify-end gap-2">
                        <button type="button" wire:click="$set('showVoidModal', false)" class="rounded-lg border border-hairline px-4 py-2 text-sm font-medium text-ink hover:bg-app-bg">
                            Cancel
                        </button>
                        <button type="submit" class="rounded-lg bg-danger-500 px-4 py-2 text-sm font-semibold text-white hover:opacity-90">
                            Confirm Void
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>

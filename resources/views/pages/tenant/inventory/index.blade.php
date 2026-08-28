<?php

use App\Enums\ProductInventoryMovementType;
use App\Enums\ProductType;
use App\Exceptions\InsufficientInventoryException;
use App\Models\Product;
use App\Services\ProductInventoryService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Layout('layouts.app')] #[Title('Product Inventory')] class extends Component
{
    use WithPagination;

    public string $search = '';

    public bool $lowStockOnly = false;

    public ?int $adjustingId = null;

    public string $movement_type = 'stock_added';

    public string $quantity = '';

    public string $note = '';

    public function getProductsProperty()
    {
        return Product::query()
            ->with('inventory', 'category')
            ->where('type', ProductType::ReadyToSell)
            ->when($this->search, fn ($q) => $q->where('name', 'like', '%'.$this->search.'%'))
            ->when($this->lowStockOnly, fn ($q) => $q->whereHas(
                'inventory',
                fn ($iq) => $iq->whereColumn('quantity', '<=', 'products.low_stock_threshold')
            ))
            ->orderBy('name')
            ->paginate(15);
    }

    public function openAdjust(int $productId): void
    {
        $this->adjustingId = $productId;
        $this->reset(['quantity', 'note']);
        $this->movement_type = 'stock_added';
    }

    public function adjustStock(ProductInventoryService $inventory): void
    {
        $this->validate([
            'movement_type' => ['required', 'in:'.implode(',', array_map(fn ($c) => $c->value, ProductInventoryMovementType::manualTypes()))],
            'quantity' => ['required', 'integer', 'min:1'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        $product = Product::findOrFail($this->adjustingId);
        $type = ProductInventoryMovementType::from($this->movement_type);
        $delta = $type->isDeduction() ? -1 * (int) $this->quantity : (int) $this->quantity;

        try {
            $inventory->adjust($product, $delta, $type, $this->note ?: null, Auth::user());
            $this->adjustingId = null;
            session()->flash('status', 'Inventory updated.');
        } catch (InsufficientInventoryException $e) {
            $this->addError('quantity', $e->getMessage());
        }
    }
}; ?>

<div class="space-y-6">
    <div>
        <h1 class="text-2xl font-semibold text-ink">Product Inventory</h1>
        <p class="mt-1 text-sm text-muted">Sellable stock across your ready-to-sell products.</p>
    </div>

    @if (session('status'))
        <div class="rounded-lg bg-green-50 px-4 py-3 text-sm text-green-700">{{ session('status') }}</div>
    @endif

    <div class="flex flex-wrap items-center gap-3">
        <input wire:model.live.debounce.300ms="search" type="text" placeholder="Search products..."
               class="w-full max-w-xs rounded-lg border border-hairline px-3 py-2 text-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500">
        <label class="flex items-center gap-2 text-sm text-ink">
            <input wire:model.live="lowStockOnly" type="checkbox" class="rounded border-hairline text-primary-600">
            Low stock only
        </label>
    </div>

    <div class="overflow-x-auto rounded-xl border border-hairline bg-surface">
        <table class="w-full min-w-[600px] text-left text-sm">
            <thead class="border-b border-hairline bg-app-bg text-xs font-medium uppercase tracking-wide text-muted">
                <tr>
                    <th class="px-4 py-3">Product</th>
                    <th class="px-4 py-3">Category</th>
                    <th class="px-4 py-3">Stock</th>
                    <th class="px-4 py-3">Alert At</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-hairline">
                @forelse ($this->products as $product)
                    <tr>
                        <td class="px-4 py-3">
                            <a href="{{ route('app.products.show', $product) }}" class="font-medium text-ink hover:text-primary-600">{{ $product->name }}</a>
                        </td>
                        <td class="px-4 py-3 text-muted">{{ $product->category?->name ?? '—' }}</td>
                        <td class="px-4 py-3">
                            <span class="{{ $product->isLowStock() ? 'font-medium text-danger-500' : 'text-ink' }}">
                                {{ $product->currentStock() }}
                            </span>
                            @if ($product->isLowStock())
                                <span class="ml-1 rounded-full bg-red-50 px-2 py-0.5 text-xs font-medium text-danger-500">Low</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-muted">{{ $product->low_stock_threshold }}</td>
                        <td class="px-4 py-3 text-right">
                            <button type="button" wire:click="openAdjust({{ $product->id }})"
                                    class="rounded-lg border border-hairline px-3 py-1.5 text-xs font-medium text-ink hover:bg-app-bg">
                                Adjust
                            </button>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-4 py-12 text-center text-muted">No ready-to-sell products yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $this->products->links() }}

    @if ($adjustingId)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
            <div class="w-full max-w-md rounded-xl bg-surface p-6 shadow-xl">
                <h2 class="text-lg font-semibold text-ink">Adjust Stock</h2>

                <form wire:submit="adjustStock" class="mt-4 space-y-4">
                    <div>
                        <label class="mb-1 block text-sm font-medium text-ink">Movement Type</label>
                        <select wire:model="movement_type" class="w-full rounded-lg border border-hairline px-3 py-2 text-sm">
                            @foreach (ProductInventoryMovementType::manualTypes() as $case)
                                <option value="{{ $case->value }}">{{ $case->label() }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-ink">Quantity</label>
                        <input wire:model="quantity" type="number" min="1" class="w-full rounded-lg border border-hairline px-3 py-2 text-sm">
                        @error('quantity') <p class="mt-1 text-sm text-danger-500">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-ink">Note (optional)</label>
                        <input wire:model="note" type="text" class="w-full rounded-lg border border-hairline px-3 py-2 text-sm">
                    </div>
                    <div class="flex justify-end gap-2">
                        <button type="button" wire:click="$set('adjustingId', null)" class="rounded-lg border border-hairline px-4 py-2 text-sm font-medium text-ink hover:bg-app-bg">
                            Cancel
                        </button>
                        <button type="submit" class="rounded-lg bg-primary-600 px-4 py-2 text-sm font-semibold text-white hover:bg-primary-700">
                            Apply
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>

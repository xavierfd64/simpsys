<?php

use App\Enums\ProductInventoryMovementType;
use App\Models\Product;
use App\Services\ProductInventoryService;
use App\Support\Money;
use App\Support\TenantStorage;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Layout('layouts.app')] #[Title('Product Details')] class extends Component
{
    use WithPagination;

    public Product $product;

    public string $tab = 'overview';

    public string $movement_type = 'stock_added';

    public string $direction = 'add';

    public string $quantity = '';

    public string $note = '';

    public function mount(Product $product): void
    {
        // Implicit route-model binding runs in the SubstituteBindings
        // middleware, which fires before the `tenant` middleware populates
        // TenantContext — so the tenant global scope isn't active yet at
        // bind time. Re-fetch here (post-middleware, scope active) so a
        // product ID belonging to another tenant 404s instead of loading.
        $this->product = Product::with('category', 'inventory')->findOrFail($product->id);
    }

    public function getMovementsProperty()
    {
        return $this->product->movements()->with('user')->latest('id')->paginate(15);
    }

    public function adjustStock(ProductInventoryService $inventory): void
    {
        $this->validate([
            'movement_type' => ['required', 'in:'.implode(',', array_map(fn ($c) => $c->value, ProductInventoryMovementType::manualTypes()))],
            'quantity' => ['required', 'integer', 'min:1'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        $type = ProductInventoryMovementType::from($this->movement_type);
        $isDeduction = $type === ProductInventoryMovementType::Adjustment
            ? $this->direction === 'remove'
            : $type->isDeduction();
        $delta = $isDeduction ? -1 * (int) $this->quantity : (int) $this->quantity;

        try {
            $inventory->adjust($this->product, $delta, $type, $this->note ?: null, Auth::user());
            $this->product->refresh();
            $this->reset(['quantity', 'note']);
            session()->flash('status', 'Inventory updated.');
        } catch (\App\Exceptions\InsufficientInventoryException $e) {
            $this->addError('quantity', $e->getMessage());
        }
    }
}; ?>

<div class="space-y-6">
    <div class="flex items-center gap-3">
        <a href="{{ route('app.products.index') }}" class="text-muted hover:text-ink">
            <x-lucide-arrow-left class="h-5 w-5" />
        </a>
        <h1 class="text-2xl font-semibold text-ink">{{ $product->name }}</h1>
        <span class="rounded-full px-2.5 py-1 text-xs font-medium {{ $product->is_active ? 'bg-green-50 text-green-700' : 'bg-app-bg text-muted' }}">
            {{ $product->is_active ? 'Active' : 'Inactive' }}
        </span>
    </div>

    @if (session('status'))
        <div class="rounded-lg bg-green-50 px-4 py-3 text-sm text-green-700">{{ session('status') }}</div>
    @endif

    <div class="flex gap-1 border-b border-hairline">
        @foreach (['overview' => 'Overview', 'inventory' => 'Inventory', 'history' => 'Inventory History'] as $key => $label)
            <button type="button" wire:click="$set('tab', '{{ $key }}')"
                    class="border-b-2 px-4 py-2 text-sm font-medium {{ $tab === $key ? 'border-primary-600 text-primary-600' : 'border-transparent text-muted hover:text-ink' }}">
                {{ $label }}
            </button>
        @endforeach
    </div>

    @if ($tab === 'overview')
        <div class="grid gap-6 md:grid-cols-3">
            <div class="rounded-xl border border-hairline bg-surface p-6">
                <div class="flex h-40 items-center justify-center overflow-hidden rounded-lg bg-app-bg">
                    @if ($product->image_path)
                        <img src="{{ TenantStorage::url($product->image_path) }}" class="h-full w-full object-cover" alt="{{ $product->name }}">
                    @else
                        <x-lucide-package class="h-10 w-10 text-muted" />
                    @endif
                </div>
                <dl class="mt-4 space-y-2 text-sm">
                    <div class="flex justify-between"><dt class="text-muted">Category</dt><dd class="text-ink">{{ $product->category?->name ?? '—' }}</dd></div>
                    <div class="flex justify-between"><dt class="text-muted">Price</dt><dd class="text-ink">{{ Money::format($product->selling_price) }}</dd></div>
                    <div class="flex justify-between"><dt class="text-muted">Type</dt><dd class="text-ink">{{ $product->type->label() }}</dd></div>
                    <div class="flex justify-between"><dt class="text-muted">Low Stock Alert</dt><dd class="text-ink">{{ $product->low_stock_threshold }}</dd></div>
                </dl>
            </div>

            @if ($product->type->value === 'ready_to_sell')
                <div class="rounded-xl border border-hairline bg-surface p-6 md:col-span-2">
                    <h3 class="text-sm font-medium text-muted">Stock Summary</h3>
                    <p class="mt-2 text-3xl font-semibold {{ $product->isLowStock() ? 'text-danger-500' : 'text-ink' }}">
                        {{ $product->currentStock() }}
                        <span class="text-base font-normal text-muted">ready to sell</span>
                    </p>
                    @if ($product->isLowStock())
                        <p class="mt-2 flex items-center gap-1 text-sm text-danger-500">
                            <x-lucide-alert-triangle class="h-4 w-4" /> Low stock — at or below alert threshold.
                        </p>
                    @endif
                    <button type="button" wire:click="$set('tab', 'inventory')" class="mt-4 rounded-lg bg-primary-600 px-4 py-2 text-sm font-semibold text-white hover:bg-primary-700">
                        Adjust Stock
                    </button>
                </div>
            @else
                <div class="rounded-xl border border-hairline bg-surface p-6 md:col-span-2">
                    <p class="text-sm text-muted">
                        This is a made-to-order product. It has no sellable inventory &mdash; a kitchen order is
                        created each time it's sold.
                    </p>
                </div>
            @endif
        </div>
    @endif

    @if ($tab === 'inventory')
        @if ($product->type->value === 'ready_to_sell')
            <div class="max-w-lg rounded-xl border border-hairline bg-surface p-6">
                <h3 class="text-base font-semibold text-ink">Adjust Stock</h3>
                <p class="mt-1 text-sm text-muted">Current stock: <strong>{{ $product->currentStock() }}</strong></p>

                <form wire:submit="adjustStock" class="mt-4 space-y-4">
                    <div>
                        <label class="mb-1 block text-sm font-medium text-ink">Movement Type</label>
                        <select wire:model="movement_type" class="w-full rounded-lg border border-hairline px-3 py-2 text-sm">
                            @foreach (ProductInventoryMovementType::manualTypes() as $case)
                                <option value="{{ $case->value }}">{{ $case->label() }}</option>
                            @endforeach
                        </select>
                    </div>
                    @if ($movement_type === 'adjustment')
                        <div>
                            <label class="mb-1 block text-sm font-medium text-ink">Direction</label>
                            <select wire:model="direction" class="w-full rounded-lg border border-hairline px-3 py-2 text-sm">
                                <option value="add">Add to stock</option>
                                <option value="remove">Remove from stock</option>
                            </select>
                        </div>
                    @endif
                    <div>
                        <label class="mb-1 block text-sm font-medium text-ink">Quantity</label>
                        <input wire:model="quantity" type="number" min="1" class="w-full rounded-lg border border-hairline px-3 py-2 text-sm">
                        @error('quantity') <p class="mt-1 text-sm text-danger-500">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-ink">Note (optional)</label>
                        <input wire:model="note" type="text" class="w-full rounded-lg border border-hairline px-3 py-2 text-sm">
                    </div>
                    <button type="submit" class="rounded-lg bg-primary-600 px-4 py-2 text-sm font-semibold text-white hover:bg-primary-700">
                        Apply
                    </button>
                </form>
            </div>
        @else
            <p class="text-sm text-muted">Made-to-order products don't carry sellable inventory.</p>
        @endif
    @endif

    @if ($tab === 'history')
        <div class="overflow-x-auto rounded-xl border border-hairline bg-surface">
            <table class="w-full min-w-[600px] text-left text-sm">
                <thead class="border-b border-hairline bg-app-bg text-xs font-medium uppercase tracking-wide text-muted">
                    <tr>
                        <th class="px-4 py-3">Date</th>
                        <th class="px-4 py-3">Type</th>
                        <th class="px-4 py-3">Change</th>
                        <th class="px-4 py-3">Balance</th>
                        <th class="px-4 py-3">By</th>
                        <th class="px-4 py-3">Note</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-hairline">
                    @forelse ($this->movements as $movement)
                        <tr>
                            <td class="px-4 py-3 text-muted">{{ $movement->created_at->timezone($product->tenant->timezone)->format('M j, Y g:i A') }}</td>
                            <td class="px-4 py-3 text-ink">{{ $movement->type->label() }}</td>
                            <td class="px-4 py-3 {{ $movement->quantity_change < 0 ? 'text-danger-500' : 'text-green-600' }}">
                                {{ $movement->quantity_change > 0 ? '+' : '' }}{{ $movement->quantity_change }}
                            </td>
                            <td class="px-4 py-3 text-ink">{{ $movement->quantity_after }}</td>
                            <td class="px-4 py-3 text-muted">{{ $movement->user?->name ?? 'System' }}</td>
                            <td class="px-4 py-3 text-muted">{{ $movement->note }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-4 py-12 text-center text-muted">No inventory movements yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        {{ $this->movements->links() }}
    @endif
</div>

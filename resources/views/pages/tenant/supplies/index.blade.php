<?php

use App\Enums\SupplyInventoryMovementType;
use App\Exceptions\InsufficientSupplyException;
use App\Models\Supply;
use App\Models\SupplyCategory;
use App\Services\SupplyInventoryService;
use App\Services\TenantContext;
use App\Support\TenantStorage;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

new #[Layout('layouts.app')] #[Title('Supplies')] class extends Component
{
    use WithFileUploads, WithPagination;

    public string $search = '';

    public bool $lowStockOnly = false;

    public bool $showFormModal = false;

    public bool $showCategoryModal = false;

    public bool $showAdjustModal = false;

    public bool $showHistoryModal = false;

    public ?int $editingId = null;

    public ?int $adjustingId = null;

    public ?int $historyId = null;

    public string $name = '';

    public string $supply_category_id = '';

    public string $unit = '';

    public string $low_stock_threshold = '0';

    public bool $is_active = true;

    public $image = null;

    public string $new_category_name = '';

    public string $movement_type = 'stock_added';

    public string $direction = 'add';

    public string $quantity = '';

    public string $note = '';

    public function getSuppliesProperty()
    {
        return Supply::query()
            ->with(['category', 'inventory'])
            ->when($this->search, fn ($q) => $q->where('name', 'like', '%'.$this->search.'%'))
            ->when($this->lowStockOnly, fn ($q) => $q->whereHas(
                'inventory',
                fn ($iq) => $iq->whereColumn('quantity', '<=', 'supplies.low_stock_threshold')
            ))
            ->orderBy('name')
            ->paginate(12);
    }

    public function getCategoriesProperty()
    {
        return SupplyCategory::query()->orderBy('name')->get();
    }

    public function getHistoryProperty()
    {
        if (! $this->historyId) {
            return collect();
        }

        return Supply::findOrFail($this->historyId)->movements()->with('user')->latest('id')->limit(30)->get();
    }

    public function openCreate(): void
    {
        $this->reset(['editingId', 'name', 'supply_category_id', 'unit', 'image']);
        $this->low_stock_threshold = '0';
        $this->is_active = true;
        $this->showFormModal = true;
    }

    public function openEdit(int $supplyId): void
    {
        $supply = Supply::findOrFail($supplyId);

        $this->editingId = $supply->id;
        $this->name = $supply->name;
        $this->supply_category_id = (string) $supply->supply_category_id;
        $this->unit = $supply->unit;
        $this->low_stock_threshold = (string) $supply->low_stock_threshold;
        $this->is_active = $supply->is_active;
        $this->image = null;
        $this->showFormModal = true;
    }

    public function save(): void
    {
        $data = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'supply_category_id' => ['nullable', 'exists:supply_categories,id'],
            'unit' => ['required', 'string', 'max:50'],
            'low_stock_threshold' => ['required', 'numeric', 'min:0'],
            'image' => ['nullable', 'image', 'max:5120'],
        ]);

        $tenant = app(TenantContext::class)->tenant();

        $attributes = [
            'name' => $data['name'],
            'supply_category_id' => $data['supply_category_id'] ?: null,
            'unit' => $data['unit'],
            'low_stock_threshold' => (float) $data['low_stock_threshold'],
            'is_active' => $this->is_active,
        ];

        if ($this->image) {
            $attributes['image_path'] = TenantStorage::storeImage($this->image, 'supplies', $tenant);
        }

        if ($this->editingId) {
            $supply = Supply::findOrFail($this->editingId);

            if ($this->image) {
                TenantStorage::delete($supply->image_path);
            }

            $supply->update($attributes);
        } else {
            Supply::create($attributes);
        }

        $this->showFormModal = false;
        session()->flash('status', 'Supply saved.');
    }

    public function toggleActive(int $supplyId): void
    {
        $supply = Supply::findOrFail($supplyId);
        $supply->update(['is_active' => ! $supply->is_active]);
    }

    public function saveCategory(): void
    {
        $this->validate(['new_category_name' => ['required', 'string', 'max:255']]);

        SupplyCategory::create(['name' => $this->new_category_name]);
        $this->new_category_name = '';
    }

    public function deleteCategory(int $categoryId): void
    {
        SupplyCategory::findOrFail($categoryId)->delete();
    }

    public function openAdjust(int $supplyId): void
    {
        $this->adjustingId = $supplyId;
        $this->reset(['quantity', 'note']);
        $this->movement_type = 'stock_added';
        $this->showAdjustModal = true;
    }

    public function adjustStock(SupplyInventoryService $inventory): void
    {
        $this->validate([
            'movement_type' => ['required', 'in:stock_added,manual_usage,spoilage,adjustment'],
            'quantity' => ['required', 'numeric', 'min:0.01'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        $supply = Supply::findOrFail($this->adjustingId);
        $type = SupplyInventoryMovementType::from($this->movement_type);
        $isDeduction = $type === SupplyInventoryMovementType::Adjustment
            ? $this->direction === 'remove'
            : $type->isDeduction();
        $delta = $isDeduction ? -1 * (float) $this->quantity : (float) $this->quantity;

        try {
            $inventory->adjust($supply, $delta, $type, $this->note ?: null, Auth::user());
            $this->showAdjustModal = false;
            session()->flash('status', 'Supply stock updated.');
        } catch (InsufficientSupplyException $e) {
            $this->addError('quantity', $e->getMessage());
        }
    }

    public function openHistory(int $supplyId): void
    {
        $this->historyId = $supplyId;
        $this->showHistoryModal = true;
    }
}; ?>

<div class="space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-2xl font-semibold text-ink">Supplies</h1>
            <p class="mt-1 text-sm text-muted">Cooking oil, packaging, and other operating materials.</p>
        </div>
        <div class="flex gap-2">
            <button type="button" wire:click="$set('showCategoryModal', true)"
                    class="rounded-lg border border-hairline bg-surface px-4 py-2 text-sm font-medium text-ink hover:bg-app-bg">
                Categories
            </button>
            <button type="button" wire:click="openCreate"
                    class="flex items-center gap-2 rounded-lg bg-primary-600 px-4 py-2 text-sm font-semibold text-white hover:bg-primary-700">
                <x-lucide-plus class="h-4 w-4" /> Add Supply
            </button>
        </div>
    </div>

    @if (session('status'))
        <div class="rounded-lg bg-green-50 px-4 py-3 text-sm text-green-700">{{ session('status') }}</div>
    @endif

    <div class="flex flex-wrap items-center gap-3">
        <input wire:model.live.debounce.300ms="search" type="text" placeholder="Search supplies..."
               class="w-full max-w-xs rounded-lg border border-hairline px-3 py-2 text-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500">
        <label class="flex items-center gap-2 text-sm text-ink">
            <input wire:model.live="lowStockOnly" type="checkbox" class="rounded border-hairline text-primary-600">
            Low stock only
        </label>
    </div>

    <div class="overflow-x-auto rounded-xl border border-hairline bg-surface">
        <table class="w-full min-w-[720px] text-left text-sm">
            <thead class="border-b border-hairline bg-app-bg text-xs font-medium uppercase tracking-wide text-muted">
                <tr>
                    <th class="px-4 py-3">Supply</th>
                    <th class="px-4 py-3">Category</th>
                    <th class="px-4 py-3">Stock</th>
                    <th class="px-4 py-3">Status</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-hairline">
                @forelse ($this->supplies as $supply)
                    <tr>
                        <td class="flex items-center gap-3 px-4 py-3">
                            <div class="flex h-10 w-10 shrink-0 items-center justify-center overflow-hidden rounded-lg bg-app-bg">
                                @if ($supply->image_path)
                                    <img src="{{ TenantStorage::url($supply->image_path) }}" class="h-full w-full object-cover" alt="{{ $supply->name }}">
                                @else
                                    <x-lucide-tag class="h-5 w-5 text-muted" />
                                @endif
                            </div>
                            <span class="font-medium text-ink">{{ $supply->name }}</span>
                        </td>
                        <td class="px-4 py-3 text-muted">{{ $supply->category?->name ?? '—' }}</td>
                        <td class="px-4 py-3">
                            <span class="{{ $supply->isLowStock() ? 'font-medium text-danger-500' : 'text-ink' }}">
                                {{ rtrim(rtrim(number_format($supply->currentStock(), 2), '0'), '.') }} {{ $supply->unit }}
                            </span>
                            @if ($supply->isLowStock())
                                <span class="ml-1 rounded-full bg-red-50 px-2 py-0.5 text-xs font-medium text-danger-500">Low</span>
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            <span class="rounded-full px-2.5 py-1 text-xs font-medium {{ $supply->is_active ? 'bg-green-50 text-green-700' : 'bg-app-bg text-muted' }}">
                                {{ $supply->is_active ? 'Active' : 'Inactive' }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-right">
                            <div class="flex justify-end gap-1">
                                <button type="button" wire:click="openAdjust({{ $supply->id }})" class="rounded-lg border border-hairline px-3 py-1.5 text-xs font-medium text-ink hover:bg-app-bg">
                                    Adjust
                                </button>
                                <button type="button" wire:click="openHistory({{ $supply->id }})" class="rounded-lg p-2 text-muted hover:bg-app-bg hover:text-ink">
                                    <x-lucide-history class="h-4 w-4" />
                                </button>
                                <button type="button" wire:click="openEdit({{ $supply->id }})" class="rounded-lg p-2 text-muted hover:bg-app-bg hover:text-ink">
                                    <x-lucide-pencil class="h-4 w-4" />
                                </button>
                                <button type="button" wire:click="toggleActive({{ $supply->id }})" class="rounded-lg p-2 text-muted hover:bg-app-bg hover:text-ink">
                                    <x-lucide-power class="h-4 w-4" />
                                </button>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-4 py-12 text-center text-muted">No supplies yet. Add your first supply to get started.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $this->supplies->links() }}

    @if ($showFormModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
            <div class="w-full max-w-lg rounded-xl bg-surface p-6 shadow-xl">
                <h2 class="text-lg font-semibold text-ink">{{ $editingId ? 'Edit Supply' : 'Add Supply' }}</h2>

                <form wire:submit="save" class="mt-4 space-y-4">
                    <div>
                        <label class="mb-1 block text-sm font-medium text-ink">Supply Name</label>
                        <input wire:model="name" type="text" class="w-full rounded-lg border border-hairline px-3 py-2 text-sm">
                        @error('name') <p class="mt-1 text-sm text-danger-500">{{ $message }}</p> @enderror
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="mb-1 block text-sm font-medium text-ink">Category</label>
                            <select wire:model="supply_category_id" class="w-full rounded-lg border border-hairline px-3 py-2 text-sm">
                                <option value="">None</option>
                                @foreach ($this->categories as $category)
                                    <option value="{{ $category->id }}">{{ $category->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium text-ink">Unit</label>
                            <input wire:model="unit" type="text" placeholder="kg, liters, pieces..." class="w-full rounded-lg border border-hairline px-3 py-2 text-sm">
                            @error('unit') <p class="mt-1 text-sm text-danger-500">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    <div>
                        <label class="mb-1 block text-sm font-medium text-ink">Low Stock Alert</label>
                        <input wire:model="low_stock_threshold" type="number" step="0.01" min="0" class="w-full max-w-xs rounded-lg border border-hairline px-3 py-2 text-sm">
                    </div>

                    <div>
                        <label class="mb-1 block text-sm font-medium text-ink">Image</label>
                        <input wire:model="image" type="file" accept="image/*" class="w-full text-sm">
                        @error('image') <p class="mt-1 text-sm text-danger-500">{{ $message }}</p> @enderror
                    </div>

                    <label class="flex items-center gap-2 text-sm text-ink">
                        <input wire:model="is_active" type="checkbox" class="rounded border-hairline text-primary-600">
                        Active
                    </label>

                    <div class="flex justify-end gap-2 pt-2">
                        <button type="button" wire:click="$set('showFormModal', false)" class="rounded-lg border border-hairline px-4 py-2 text-sm font-medium text-ink hover:bg-app-bg">
                            Cancel
                        </button>
                        <button type="submit" class="rounded-lg bg-primary-600 px-4 py-2 text-sm font-semibold text-white hover:bg-primary-700">
                            Save Supply
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    @if ($showAdjustModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
            <div class="w-full max-w-md rounded-xl bg-surface p-6 shadow-xl">
                <h2 class="text-lg font-semibold text-ink">Adjust Stock</h2>

                <form wire:submit="adjustStock" class="mt-4 space-y-4">
                    <div>
                        <label class="mb-1 block text-sm font-medium text-ink">Movement Type</label>
                        <select wire:model="movement_type" class="w-full rounded-lg border border-hairline px-3 py-2 text-sm">
                            @foreach (SupplyInventoryMovementType::cases() as $case)
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
                        <input wire:model="quantity" type="number" step="0.01" min="0.01" class="w-full rounded-lg border border-hairline px-3 py-2 text-sm">
                        @error('quantity') <p class="mt-1 text-sm text-danger-500">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-ink">Note (optional)</label>
                        <input wire:model="note" type="text" class="w-full rounded-lg border border-hairline px-3 py-2 text-sm">
                    </div>
                    <div class="flex justify-end gap-2">
                        <button type="button" wire:click="$set('showAdjustModal', false)" class="rounded-lg border border-hairline px-4 py-2 text-sm font-medium text-ink hover:bg-app-bg">
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

    @if ($showHistoryModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
            <div class="w-full max-w-lg rounded-xl bg-surface p-6 shadow-xl">
                <h2 class="text-lg font-semibold text-ink">Inventory History</h2>

                <div class="mt-4 max-h-96 overflow-y-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="text-xs font-medium uppercase tracking-wide text-muted">
                            <tr>
                                <th class="py-2">Date</th>
                                <th class="py-2">Type</th>
                                <th class="py-2">Change</th>
                                <th class="py-2">Balance</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-hairline">
                            @forelse ($this->history as $movement)
                                <tr>
                                    <td class="py-2 text-muted">{{ $movement->created_at->format('M j, g:i A') }}</td>
                                    <td class="py-2 text-ink">{{ $movement->type->label() }}</td>
                                    <td class="py-2 {{ $movement->quantity_change < 0 ? 'text-danger-500' : 'text-green-600' }}">
                                        {{ $movement->quantity_change > 0 ? '+' : '' }}{{ $movement->quantity_change }}
                                    </td>
                                    <td class="py-2 text-ink">{{ $movement->quantity_after }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="py-8 text-center text-muted">No movements yet.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="mt-4 flex justify-end">
                    <button type="button" wire:click="$set('showHistoryModal', false)" class="rounded-lg border border-hairline px-4 py-2 text-sm font-medium text-ink hover:bg-app-bg">
                        Close
                    </button>
                </div>
            </div>
        </div>
    @endif

    @if ($showCategoryModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
            <div class="w-full max-w-md rounded-xl bg-surface p-6 shadow-xl">
                <h2 class="text-lg font-semibold text-ink">Supply Categories</h2>

                <form wire:submit="saveCategory" class="mt-4 flex gap-2">
                    <input wire:model="new_category_name" type="text" placeholder="New category name"
                           class="flex-1 rounded-lg border border-hairline px-3 py-2 text-sm">
                    <button type="submit" class="rounded-lg bg-primary-600 px-4 py-2 text-sm font-semibold text-white hover:bg-primary-700">
                        Add
                    </button>
                </form>
                @error('new_category_name') <p class="mt-1 text-sm text-danger-500">{{ $message }}</p> @enderror

                <ul class="mt-4 max-h-64 space-y-1 overflow-y-auto">
                    @forelse ($this->categories as $category)
                        <li class="flex items-center justify-between rounded-lg px-3 py-2 text-sm hover:bg-app-bg">
                            {{ $category->name }}
                            <button type="button" wire:click="deleteCategory({{ $category->id }})" wire:confirm="Delete this category?" class="text-muted hover:text-danger-500">
                                <x-lucide-trash-2 class="h-4 w-4" />
                            </button>
                        </li>
                    @empty
                        <li class="px-3 py-2 text-sm text-muted">No categories yet.</li>
                    @endforelse
                </ul>

                <div class="mt-4 flex justify-end">
                    <button type="button" wire:click="$set('showCategoryModal', false)" class="rounded-lg border border-hairline px-4 py-2 text-sm font-medium text-ink hover:bg-app-bg">
                        Close
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>

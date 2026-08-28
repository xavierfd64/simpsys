<?php

use App\Enums\ProductType;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Services\TenantContext;
use App\Support\Money;
use App\Support\TenantStorage;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

new #[Layout('layouts.app')] #[Title('Products')] class extends Component
{
    use WithFileUploads, WithPagination;

    public string $search = '';

    public string $categoryFilter = '';

    public string $statusFilter = '';

    public bool $showFormModal = false;

    public bool $showCategoryModal = false;

    public ?int $editingId = null;

    public string $name = '';

    public string $product_category_id = '';

    public string $type = 'ready_to_sell';

    public string $selling_price = '';

    public string $low_stock_threshold = '10';

    public bool $is_active = true;

    public $image = null;

    public string $new_category_name = '';

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function getProductsProperty()
    {
        return Product::query()
            ->with(['category', 'inventory'])
            ->when($this->search, fn ($q) => $q->where('name', 'like', '%'.$this->search.'%'))
            ->when($this->categoryFilter, fn ($q) => $q->where('product_category_id', $this->categoryFilter))
            ->when($this->statusFilter === 'active', fn ($q) => $q->where('is_active', true))
            ->when($this->statusFilter === 'inactive', fn ($q) => $q->where('is_active', false))
            ->orderBy('name')
            ->paginate(12);
    }

    public function getCategoriesProperty()
    {
        return ProductCategory::query()->orderBy('name')->get();
    }

    public function openCreate(): void
    {
        $this->reset(['editingId', 'name', 'product_category_id', 'selling_price', 'image']);
        $this->type = 'ready_to_sell';
        $this->low_stock_threshold = '10';
        $this->is_active = true;
        $this->showFormModal = true;
    }

    public function openEdit(int $productId): void
    {
        $product = Product::findOrFail($productId);

        $this->editingId = $product->id;
        $this->name = $product->name;
        $this->product_category_id = (string) $product->product_category_id;
        $this->type = $product->type->value;
        $this->selling_price = number_format($product->selling_price / 100, 2, '.', '');
        $this->low_stock_threshold = (string) $product->low_stock_threshold;
        $this->is_active = $product->is_active;
        $this->image = null;
        $this->showFormModal = true;
    }

    public function save(): void
    {
        $data = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'product_category_id' => ['nullable', 'exists:product_categories,id'],
            'type' => ['required', 'in:ready_to_sell,made_to_order'],
            'selling_price' => ['required', 'numeric', 'min:0'],
            'low_stock_threshold' => ['required', 'integer', 'min:0'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ]);

        $tenant = app(TenantContext::class)->tenant();

        $attributes = [
            'name' => $data['name'],
            'product_category_id' => $data['product_category_id'] ?: null,
            'type' => $data['type'],
            'selling_price' => Money::toCents($data['selling_price']),
            'low_stock_threshold' => (int) $data['low_stock_threshold'],
            'is_active' => $this->is_active,
        ];

        if ($this->image) {
            $attributes['image_path'] = TenantStorage::storeImage($this->image, 'products', $tenant);
        }

        if ($this->editingId) {
            $product = Product::findOrFail($this->editingId);

            if ($this->image) {
                TenantStorage::delete($product->image_path);
            }

            $product->update($attributes);
        } else {
            Product::create($attributes);
        }

        $this->showFormModal = false;
        session()->flash('status', 'Product saved.');
    }

    public function toggleActive(int $productId): void
    {
        $product = Product::findOrFail($productId);
        $product->update(['is_active' => ! $product->is_active]);
    }

    public function saveCategory(): void
    {
        $this->validate(['new_category_name' => ['required', 'string', 'max:255']]);

        ProductCategory::create(['name' => $this->new_category_name]);
        $this->new_category_name = '';
    }

    public function deleteCategory(int $categoryId): void
    {
        ProductCategory::findOrFail($categoryId)->delete();
    }
}; ?>

<div class="space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-2xl font-semibold text-ink">Products</h1>
            <p class="mt-1 text-sm text-muted">Manage what you sell.</p>
        </div>
        <div class="flex gap-2">
            <button type="button" wire:click="$set('showCategoryModal', true)"
                    class="rounded-lg border border-hairline bg-surface px-4 py-2 text-sm font-medium text-ink hover:bg-app-bg">
                Categories
            </button>
            <button type="button" wire:click="openCreate"
                    class="flex items-center gap-2 rounded-lg bg-primary-600 px-4 py-2 text-sm font-semibold text-white hover:bg-primary-700">
                <x-lucide-plus class="h-4 w-4" /> Add Product
            </button>
        </div>
    </div>

    @if (session('status'))
        <div class="rounded-lg bg-green-50 px-4 py-3 text-sm text-green-700">{{ session('status') }}</div>
    @endif

    <div class="flex flex-wrap gap-3">
        <input wire:model.live.debounce.300ms="search" type="text" placeholder="Search products..."
               class="w-full max-w-xs rounded-lg border border-hairline px-3 py-2 text-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500">

        <select wire:model.live="categoryFilter" class="rounded-lg border border-hairline px-3 py-2 text-sm">
            <option value="">All Categories</option>
            @foreach ($this->categories as $category)
                <option value="{{ $category->id }}">{{ $category->name }}</option>
            @endforeach
        </select>

        <select wire:model.live="statusFilter" class="rounded-lg border border-hairline px-3 py-2 text-sm">
            <option value="">All Status</option>
            <option value="active">Active</option>
            <option value="inactive">Inactive</option>
        </select>
    </div>

    <div class="overflow-x-auto rounded-xl border border-hairline bg-surface">
        <table class="w-full min-w-[720px] text-left text-sm">
            <thead class="border-b border-hairline bg-app-bg text-xs font-medium uppercase tracking-wide text-muted">
                <tr>
                    <th class="px-4 py-3">Product</th>
                    <th class="px-4 py-3">Category</th>
                    <th class="px-4 py-3">Type</th>
                    <th class="px-4 py-3">Price</th>
                    <th class="px-4 py-3">Stock</th>
                    <th class="px-4 py-3">Status</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-hairline">
                @forelse ($this->products as $product)
                    <tr>
                        <td class="flex items-center gap-3 px-4 py-3">
                            <div class="flex h-10 w-10 shrink-0 items-center justify-center overflow-hidden rounded-lg bg-app-bg">
                                @if ($product->image_path)
                                    <img src="{{ \App\Support\TenantStorage::url($product->image_path) }}" class="h-full w-full object-cover" alt="{{ $product->name }}">
                                @else
                                    <x-lucide-package class="h-5 w-5 text-muted" />
                                @endif
                            </div>
                            <a href="{{ route('app.products.show', $product) }}" class="font-medium text-ink hover:text-primary-600">
                                {{ $product->name }}
                            </a>
                        </td>
                        <td class="px-4 py-3 text-muted">{{ $product->category?->name ?? '—' }}</td>
                        <td class="px-4 py-3 text-muted">{{ $product->type->label() }}</td>
                        <td class="px-4 py-3 text-ink">{{ Money::format($product->selling_price) }}</td>
                        <td class="px-4 py-3">
                            @if ($product->type->value === 'ready_to_sell')
                                <span class="{{ $product->isLowStock() ? 'text-danger-500 font-medium' : 'text-ink' }}">
                                    {{ $product->currentStock() }}
                                </span>
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            <span class="rounded-full px-2.5 py-1 text-xs font-medium {{ $product->is_active ? 'bg-green-50 text-green-700' : 'bg-app-bg text-muted' }}">
                                {{ $product->is_active ? 'Active' : 'Inactive' }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-right">
                            <div class="flex justify-end gap-1">
                                <button type="button" wire:click="openEdit({{ $product->id }})" class="rounded-lg p-2 text-muted hover:bg-app-bg hover:text-ink">
                                    <x-lucide-pencil class="h-4 w-4" />
                                </button>
                                <button type="button" wire:click="toggleActive({{ $product->id }})" class="rounded-lg p-2 text-muted hover:bg-app-bg hover:text-ink">
                                    <x-lucide-power class="h-4 w-4" />
                                </button>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-4 py-12 text-center text-muted">No products yet. Add your first product to get started.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $this->products->links() }}

    @if ($showFormModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
            <div class="w-full max-w-lg rounded-xl bg-surface p-6 shadow-xl">
                <h2 class="text-lg font-semibold text-ink">{{ $editingId ? 'Edit Product' : 'Add Product' }}</h2>

                <form wire:submit="save" class="mt-4 space-y-4">
                    <div>
                        <label class="mb-1 block text-sm font-medium text-ink">Product Name</label>
                        <input wire:model="name" type="text" class="w-full rounded-lg border border-hairline px-3 py-2 text-sm">
                        @error('name') <p class="mt-1 text-sm text-danger-500">{{ $message }}</p> @enderror
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="mb-1 block text-sm font-medium text-ink">Category</label>
                            <select wire:model="product_category_id" class="w-full rounded-lg border border-hairline px-3 py-2 text-sm">
                                <option value="">None</option>
                                @foreach ($this->categories as $category)
                                    <option value="{{ $category->id }}">{{ $category->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium text-ink">Type</label>
                            <select wire:model="type" class="w-full rounded-lg border border-hairline px-3 py-2 text-sm">
                                @foreach (ProductType::cases() as $case)
                                    <option value="{{ $case->value }}">{{ $case->label() }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="mb-1 block text-sm font-medium text-ink">Selling Price (₱)</label>
                            <input wire:model="selling_price" type="text" inputmode="decimal" class="w-full rounded-lg border border-hairline px-3 py-2 text-sm">
                            @error('selling_price') <p class="mt-1 text-sm text-danger-500">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium text-ink">Low Stock Alert</label>
                            <input wire:model="low_stock_threshold" type="number" min="0" class="w-full rounded-lg border border-hairline px-3 py-2 text-sm">
                        </div>
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
                            Save Product
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    @if ($showCategoryModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
            <div class="w-full max-w-md rounded-xl bg-surface p-6 shadow-xl">
                <h2 class="text-lg font-semibold text-ink">Product Categories</h2>

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

<?php

use App\Enums\OrderType;
use App\Exceptions\InsufficientInventoryException;
use App\Exceptions\InsufficientPaymentException;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Services\SaleService;
use App\Services\TenantContext;
use App\Support\Money;
use App\Support\TenantStorage;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts.app')] #[Title('POS')] class extends Component
{
    public string $search = '';

    public ?int $categoryId = null;

    /** @var array<int, int> product_id => quantity */
    public array $cart = [];

    public ?string $orderType = null;

    public bool $showMobileCart = false;

    public bool $showCheckout = false;

    public ?int $payment_method_id = null;

    public string $amount_received = '';

    public ?string $lastSaleMessage = null;

    public function mount(): void
    {
        $settings = app(TenantContext::class)->tenant()->settings;

        if ($settings?->orderTypesEnabled()) {
            $this->orderType = $settings->default_order_type?->value ?? OrderType::ToGo->value;
        }
    }

    public function getSettingsProperty()
    {
        return app(TenantContext::class)->tenant()->settings;
    }

    public function getCategoriesProperty()
    {
        return ProductCategory::query()->orderBy('name')->get();
    }

    public function getProductsProperty()
    {
        return Product::query()
            ->with('inventory')
            ->where('is_active', true)
            ->when($this->search, fn ($q) => $q->where('name', 'like', '%'.$this->search.'%'))
            ->when($this->categoryId, fn ($q) => $q->where('product_category_id', $this->categoryId))
            ->orderBy('name')
            ->get();
    }

    public function getPaymentMethodsProperty()
    {
        return PaymentMethod::query()->where('is_enabled', true)->orderBy('sort_order')->get();
    }

    public function getCartLinesProperty()
    {
        if (empty($this->cart)) {
            return collect();
        }

        $products = Product::query()->whereIn('id', array_keys($this->cart))->get()->keyBy('id');

        return collect($this->cart)->map(function ($quantity, $productId) use ($products) {
            $product = $products->get($productId);

            if (! $product) {
                return null;
            }

            return (object) [
                'product' => $product,
                'quantity' => $quantity,
                'lineTotal' => $product->selling_price * $quantity,
            ];
        })->filter()->values();
    }

    public function getTotalProperty(): int
    {
        return $this->cartLines->sum('lineTotal');
    }

    public function getChangeProperty(): int
    {
        $received = Money::toCents($this->amount_received ?: '0');

        return max(0, $received - $this->total);
    }

    public function addToCart(int $productId): void
    {
        $product = Product::findOrFail($productId);

        $currentQty = $this->cart[$productId] ?? 0;

        if ($product->type->value === 'ready_to_sell' && $currentQty + 1 > $product->currentStock()) {
            $this->addError('cart', "Not enough stock for \"{$product->name}\".");

            return;
        }

        $this->cart[$productId] = $currentQty + 1;
    }

    public function incrementQty(int $productId): void
    {
        $this->addToCart($productId);
    }

    public function decrementQty(int $productId): void
    {
        if (! isset($this->cart[$productId])) {
            return;
        }

        $this->cart[$productId]--;

        if ($this->cart[$productId] <= 0) {
            unset($this->cart[$productId]);
        }
    }

    public function removeItem(int $productId): void
    {
        unset($this->cart[$productId]);
    }

    public function clearCart(): void
    {
        $this->cart = [];
    }

    public function openCheckout(): void
    {
        if (empty($this->cart)) {
            $this->addError('cart', 'Your cart is empty.');

            return;
        }

        $this->payment_method_id = $this->paymentMethods->first()?->id;
        $this->amount_received = number_format($this->total / 100, 2, '.', '');
        $this->showCheckout = true;
    }

    public function confirmSale(SaleService $sales): void
    {
        $this->validate([
            'payment_method_id' => ['required', 'exists:payment_methods,id'],
            'amount_received' => ['required', 'numeric', 'min:0'],
        ]);

        $tenant = app(TenantContext::class)->tenant();
        $paymentMethod = PaymentMethod::findOrFail($this->payment_method_id);
        $amountReceivedCents = Money::toCents($this->amount_received);

        $cartItems = $this->cartLines->map(fn ($line) => [
            'product_id' => $line->product->id,
            'quantity' => $line->quantity,
        ])->all();

        try {
            $sale = $sales->recordSale(
                $tenant,
                Auth::user(),
                $cartItems,
                $this->orderType ? OrderType::from($this->orderType) : null,
                $paymentMethod,
                $amountReceivedCents,
            );

            $this->lastSaleMessage = "Sale {$sale->displayNumber()} recorded. Change: ".Money::format($sale->change_amount);
            $this->cart = [];
            $this->showCheckout = false;
            $this->showMobileCart = false;
        } catch (InsufficientInventoryException|InsufficientPaymentException $e) {
            $this->addError('checkout', $e->getMessage());
        }
    }
}; ?>

<div class="flex flex-col gap-4 lg:h-[calc(100dvh-7rem)] lg:flex-row">
    <div class="flex min-w-0 flex-1 flex-col gap-4">
        @if ($lastSaleMessage)
            <div class="rounded-lg bg-green-50 px-4 py-3 text-sm text-green-700">{{ $lastSaleMessage }}</div>
        @endif
        @error('cart') <div class="rounded-lg bg-red-50 px-4 py-3 text-sm text-danger-500">{{ $message }}</div> @enderror

        <div class="flex flex-wrap items-center gap-3">
            <div class="relative flex-1 min-w-[200px]">
                <x-lucide-search class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted" />
                <input wire:model.live.debounce.300ms="search" type="text" placeholder="Search product..."
                       class="w-full rounded-lg border border-hairline py-2 pl-9 pr-3 text-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500">
            </div>
        </div>

        <div class="flex gap-2 overflow-x-auto pb-1">
            <button type="button" wire:click="$set('categoryId', null)"
                    class="shrink-0 rounded-full px-4 py-1.5 text-sm font-medium {{ ! $categoryId ? 'bg-primary-600 text-white' : 'bg-surface text-muted border border-hairline' }}">
                All
            </button>
            @foreach ($this->categories as $category)
                <button type="button" wire:click="$set('categoryId', {{ $category->id }})"
                        class="shrink-0 rounded-full px-4 py-1.5 text-sm font-medium {{ $categoryId === $category->id ? 'bg-primary-600 text-white' : 'bg-surface text-muted border border-hairline' }}">
                    {{ $category->name }}
                </button>
            @endforeach
        </div>

        <div class="grid flex-1 grid-cols-2 gap-3 overflow-y-auto pb-24 sm:grid-cols-3 lg:grid-cols-4 lg:pb-4">
            @forelse ($this->products as $product)
                <button type="button" wire:click="addToCart({{ $product->id }})"
                        @if ($product->type->value === 'ready_to_sell' && $product->currentStock() <= 0) disabled @endif
                        class="flex aspect-square flex-col overflow-hidden rounded-xl border border-hairline bg-surface text-left transition hover:border-primary-300 hover:shadow-sm disabled:cursor-not-allowed disabled:opacity-50">
                    <div class="flex min-h-0 flex-1 items-center justify-center bg-app-bg">
                        @if ($product->image_path)
                            <img src="{{ TenantStorage::url($product->image_path) }}" class="h-full w-full object-cover" alt="{{ $product->name }}">
                        @else
                            <x-lucide-package class="h-8 w-8 text-muted" />
                        @endif
                    </div>
                    <div class="shrink-0 border-t border-hairline p-2">
                        <p class="truncate text-xs font-medium text-ink sm:text-sm">{{ $product->name }}</p>
                        <p class="text-xs text-primary-600 sm:text-sm">{{ Money::format($product->selling_price) }}</p>
                        @if ($product->type->value === 'ready_to_sell')
                            <p class="text-[10px] text-muted sm:text-xs">{{ $product->currentStock() }} left</p>
                        @endif
                    </div>
                </button>
            @empty
                <p class="col-span-full py-12 text-center text-sm text-muted">No products found.</p>
            @endforelse
        </div>
    </div>

    <div class="hidden w-80 min-h-0 shrink-0 flex-col rounded-xl border border-hairline bg-surface lg:flex">
        @include('pages.tenant.pos.partials.cart')
    </div>

    @if (! empty($cart))
        <button type="button" wire:click="$set('showMobileCart', true)"
                class="fixed inset-x-4 bottom-20 z-30 flex items-center justify-between rounded-xl bg-primary-600 px-4 py-3 text-sm font-semibold text-white shadow-lg lg:hidden">
            <span>{{ collect($cart)->sum() }} item(s) in cart</span>
            <span>{{ Money::format($this->total) }}</span>
        </button>
    @endif

    @if ($showMobileCart)
        <div class="fixed inset-0 z-40 flex items-end lg:hidden">
            <div class="absolute inset-0 bg-black/40" wire:click="$set('showMobileCart', false)"></div>
            <div class="relative flex max-h-[85vh] w-full flex-col rounded-t-2xl bg-surface">
                @include('pages.tenant.pos.partials.cart', ['isMobile' => true])
            </div>
        </div>
    @endif

    @if ($showCheckout)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
            <div class="w-full max-w-md rounded-xl bg-surface p-6 shadow-xl">
                <div class="flex items-center justify-between">
                    <h2 class="text-lg font-semibold text-ink">Checkout</h2>
                    <p class="text-lg font-semibold text-ink">{{ Money::format($this->total) }}</p>
                </div>

                @error('checkout') <p class="mt-2 text-sm text-danger-500">{{ $message }}</p> @enderror

                <div class="mt-4 space-y-4">
                    <div>
                        <label class="mb-1 block text-sm font-medium text-ink">Payment Method</label>
                        <select wire:model="payment_method_id" class="w-full rounded-lg border border-hairline px-3 py-2 text-sm">
                            @foreach ($this->paymentMethods as $method)
                                <option value="{{ $method->id }}">{{ $method->name }}</option>
                            @endforeach
                        </select>
                        @error('payment_method_id') <p class="mt-1 text-sm text-danger-500">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="mb-1 block text-sm font-medium text-ink">Amount Received (₱)</label>
                        <input wire:model.live="amount_received" type="text" inputmode="decimal"
                               class="w-full rounded-lg border border-hairline px-3 py-2 text-sm">
                        @error('amount_received') <p class="mt-1 text-sm text-danger-500">{{ $message }}</p> @enderror
                    </div>

                    <div class="flex items-center justify-between rounded-lg bg-app-bg px-4 py-3 text-sm">
                        <span class="text-muted">Change</span>
                        <span class="font-semibold text-ink">{{ Money::format($this->change) }}</span>
                    </div>
                </div>

                <div class="mt-6 flex justify-end gap-2">
                    <button type="button" wire:click="$set('showCheckout', false)" class="rounded-lg border border-hairline px-4 py-2 text-sm font-medium text-ink hover:bg-app-bg">
                        Cancel
                    </button>
                    <button type="button" wire:click="confirmSale" class="rounded-lg bg-primary-600 px-4 py-2 text-sm font-semibold text-white hover:bg-primary-700">
                        Confirm Sale
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>

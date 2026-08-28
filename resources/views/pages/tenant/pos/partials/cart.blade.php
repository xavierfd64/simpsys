@php $isMobile = $isMobile ?? false; @endphp

<div class="flex h-16 items-center justify-between border-b border-hairline px-4">
    <h2 class="text-base font-semibold text-ink">Cart</h2>
    @if ($isMobile)
        <button type="button" wire:click="$set('showMobileCart', false)" class="text-muted">
            <x-lucide-x class="h-5 w-5" />
        </button>
    @else
        @if (! empty($cart))
            <button type="button" wire:click="clearCart" class="text-xs font-medium text-muted hover:text-danger-500">Clear</button>
        @endif
    @endif
</div>

@if ($this->settings?->orderTypesEnabled())
    <div class="flex gap-2 border-b border-hairline p-3">
        @if ($this->settings->dine_in_enabled)
            <button type="button" wire:click="$set('orderType', 'dine_in')"
                    class="flex-1 rounded-lg py-2 text-sm font-medium {{ $orderType === 'dine_in' ? 'bg-primary-600 text-white' : 'bg-app-bg text-muted' }}">
                DINE-IN
            </button>
        @endif
        @if ($this->settings->to_go_enabled)
            <button type="button" wire:click="$set('orderType', 'to_go')"
                    class="flex-1 rounded-lg py-2 text-sm font-medium {{ $orderType === 'to_go' ? 'bg-primary-600 text-white' : 'bg-app-bg text-muted' }}">
                TO-GO
            </button>
        @endif
    </div>
@endif

<div class="flex-1 space-y-3 overflow-y-auto p-3">
    @forelse ($this->cartLines as $line)
        <div class="flex items-center gap-3">
            <div class="min-w-0 flex-1">
                <p class="truncate text-sm font-medium text-ink">{{ $line->product->name }}</p>
                <p class="text-xs text-muted">{{ \App\Support\Money::format($line->product->selling_price) }}</p>
            </div>
            <div class="flex items-center gap-2">
                <button type="button" wire:click="decrementQty({{ $line->product->id }})" class="flex h-7 w-7 items-center justify-center rounded-full border border-hairline text-muted hover:bg-app-bg">
                    <x-lucide-minus class="h-3.5 w-3.5" />
                </button>
                <span class="w-5 text-center text-sm">{{ $line->quantity }}</span>
                <button type="button" wire:click="incrementQty({{ $line->product->id }})" class="flex h-7 w-7 items-center justify-center rounded-full border border-hairline text-muted hover:bg-app-bg">
                    <x-lucide-plus class="h-3.5 w-3.5" />
                </button>
            </div>
            <p class="w-16 shrink-0 text-right text-sm font-medium text-ink">{{ \App\Support\Money::format($line->lineTotal) }}</p>
            <button type="button" wire:click="removeItem({{ $line->product->id }})" class="text-muted hover:text-danger-500">
                <x-lucide-x class="h-4 w-4" />
            </button>
        </div>
    @empty
        <p class="py-8 text-center text-sm text-muted">Cart is empty. Tap a product to add it.</p>
    @endforelse
</div>

<div class="border-t border-hairline p-4">
    <div class="flex items-center justify-between text-sm">
        <span class="text-muted">Total</span>
        <span class="text-lg font-semibold text-ink">{{ \App\Support\Money::format($this->total) }}</span>
    </div>
    <button type="button" wire:click="openCheckout"
            class="mt-3 w-full rounded-lg bg-primary-600 py-3 text-sm font-semibold text-white hover:bg-primary-700 disabled:opacity-50"
            @if (empty($cart)) disabled @endif>
        RECORD SALE
    </button>
</div>

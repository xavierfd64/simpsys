<?php

use App\Enums\KitchenOrderStatus;
use App\Models\KitchenOrder;
use App\Services\KitchenService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts.app')] #[Title('Kitchen')] class extends Component
{
    public string $tab = 'pending';

    public bool $autoRefresh = true;

    public function getCountsProperty(): array
    {
        return [
            'pending' => KitchenOrder::query()->where('status', KitchenOrderStatus::Pending)->count(),
            'preparing' => KitchenOrder::query()->where('status', KitchenOrderStatus::Preparing)->count(),
            'ready' => KitchenOrder::query()->where('status', KitchenOrderStatus::Ready)->count(),
        ];
    }

    public function getOrdersProperty()
    {
        return KitchenOrder::query()
            ->with('items')
            ->where('status', $this->tab)
            ->oldest('created_at')
            ->get();
    }

    public function advance(int $orderId, KitchenService $kitchen): void
    {
        $order = KitchenOrder::findOrFail($orderId);
        $kitchen->advance($order);
    }
}; ?>

<div class="space-y-6" @if ($autoRefresh) wire:poll.10s @endif>
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-2xl font-semibold text-ink">Kitchen Orders</h1>
            <p class="mt-1 text-sm text-muted">Made-to-order items awaiting preparation.</p>
        </div>
        <label class="flex items-center gap-2 text-sm text-muted">
            <input wire:model="autoRefresh" type="checkbox" class="rounded border-hairline text-primary-600">
            Auto Refresh
        </label>
    </div>

    <div class="flex gap-2 border-b border-hairline">
        @foreach (['pending' => 'Pending', 'preparing' => 'Preparing', 'ready' => 'Ready'] as $key => $label)
            <button type="button" wire:click="$set('tab', '{{ $key }}')"
                    class="border-b-2 px-4 py-2 text-sm font-medium {{ $tab === $key ? 'border-primary-600 text-primary-600' : 'border-transparent text-muted hover:text-ink' }}">
                {{ $label }} ({{ $this->counts[$key] }})
            </button>
        @endforeach
    </div>

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
        @forelse ($this->orders as $order)
            <div wire:key="kitchen-order-{{ $order->id }}"
                 x-data="{ startedAt: {{ $order->created_at->timestamp * 1000 }}, elapsed: '00:00' }"
                 x-init="setInterval(() => {
                     const diff = Math.max(0, Math.floor((Date.now() - startedAt) / 1000));
                     const m = String(Math.floor(diff / 60)).padStart(2, '0');
                     const s = String(diff % 60).padStart(2, '0');
                     elapsed = m + ':' + s;
                 }, 1000)"
                 class="flex flex-col rounded-xl border border-hairline bg-surface p-4">
                <div class="flex items-center justify-between">
                    <span class="text-lg font-semibold text-ink">{{ $order->displayNumber() }}</span>
                    <span class="font-mono text-sm text-muted" x-text="elapsed"></span>
                </div>

                @if ($order->order_type)
                    <span class="mt-1 inline-block w-fit rounded-full bg-app-bg px-2 py-0.5 text-xs font-medium text-muted">
                        {{ $order->order_type->label() }}
                    </span>
                @endif

                <ul class="mt-3 flex-1 space-y-1 text-sm">
                    @foreach ($order->items as $item)
                        <li class="text-ink">{{ $item->quantity }} &times; {{ $item->product_name }}</li>
                    @endforeach
                </ul>

                @if ($order->status === KitchenOrderStatus::Pending)
                    <button type="button" wire:click="advance({{ $order->id }})"
                            class="mt-4 rounded-lg bg-primary-600 py-2.5 text-sm font-semibold text-white hover:bg-primary-700">
                        START PREPARING
                    </button>
                @elseif ($order->status === KitchenOrderStatus::Preparing)
                    <button type="button" wire:click="advance({{ $order->id }})"
                            class="mt-4 rounded-lg bg-amber-500 py-2.5 text-sm font-semibold text-white hover:opacity-90">
                        MARK READY
                    </button>
                @elseif ($order->status === KitchenOrderStatus::Ready)
                    <button type="button" wire:click="advance({{ $order->id }})"
                            class="mt-4 rounded-lg bg-success-500 py-2.5 text-sm font-semibold text-white hover:opacity-90">
                        MARK COMPLETED
                    </button>
                @endif
            </div>
        @empty
            <p class="col-span-full py-12 text-center text-sm text-muted">No orders in this stage.</p>
        @endforelse
    </div>
</div>

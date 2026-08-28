<?php

use App\Models\SubscriptionPlan;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts.admin')] #[Title('Plans')] class extends Component
{
    public bool $showFormModal = false;

    public ?int $editingId = null;

    public string $name = '';

    public string $monthly_price = '';

    public string $yearly_price = '';

    public string $user_limit = '';

    public string $features_text = '';

    public bool $is_active = true;

    public string $sort_order = '0';

    public function getPlansProperty()
    {
        return SubscriptionPlan::query()->orderBy('sort_order')->get();
    }

    public function openCreate(): void
    {
        $this->reset(['editingId', 'name', 'monthly_price', 'yearly_price', 'user_limit', 'features_text']);
        $this->is_active = true;
        $this->sort_order = (string) (SubscriptionPlan::query()->count());
        $this->showFormModal = true;
    }

    public function openEdit(int $id): void
    {
        $plan = SubscriptionPlan::findOrFail($id);
        $this->editingId = $plan->id;
        $this->name = $plan->name;
        $this->monthly_price = (string) $plan->monthly_price;
        $this->yearly_price = (string) $plan->yearly_price;
        $this->user_limit = (string) $plan->user_limit;
        $this->features_text = implode("\n", $plan->features ?? []);
        $this->is_active = $plan->is_active;
        $this->sort_order = (string) $plan->sort_order;
        $this->showFormModal = true;
    }

    public function save(): void
    {
        $data = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'monthly_price' => ['required', 'integer', 'min:0'],
            'yearly_price' => ['required', 'integer', 'min:0'],
            'user_limit' => ['required', 'integer', 'min:1'],
            'sort_order' => ['required', 'integer', 'min:0'],
        ]);

        $features = collect(explode("\n", $this->features_text))
            ->map(fn ($line) => trim($line))
            ->filter()
            ->values()
            ->all();

        $attributes = [
            'name' => $data['name'],
            'slug' => \Illuminate\Support\Str::slug($data['name']),
            'monthly_price' => $data['monthly_price'],
            'yearly_price' => $data['yearly_price'],
            'user_limit' => $data['user_limit'],
            'sort_order' => $data['sort_order'],
            'features' => $features,
            'is_active' => $this->is_active,
        ];

        if ($this->editingId) {
            $plan = SubscriptionPlan::findOrFail($this->editingId);
            unset($attributes['slug']);
            $plan->update($attributes);
        } else {
            SubscriptionPlan::create($attributes);
        }

        $this->showFormModal = false;
        session()->flash('status', 'Plan saved.');
    }

    public function toggleActive(int $id): void
    {
        $plan = SubscriptionPlan::findOrFail($id);
        $plan->update(['is_active' => ! $plan->is_active]);
    }
}; ?>

<div class="space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-semibold text-ink">Subscription Plans</h1>
            <p class="mt-1 text-sm text-muted">Plans available on the pricing page and at sign-up.</p>
        </div>
        <button type="button" wire:click="openCreate" class="rounded-lg bg-primary-600 px-4 py-2 text-sm font-semibold text-white hover:bg-primary-700">
            Add Plan
        </button>
    </div>

    @if (session('status'))
        <div class="rounded-lg bg-green-50 px-4 py-3 text-sm text-green-700">{{ session('status') }}</div>
    @endif

    <div class="overflow-x-auto rounded-xl border border-hairline bg-surface">
        <table class="w-full min-w-[600px] text-left text-sm">
            <thead class="border-b border-hairline bg-app-bg text-xs font-medium uppercase tracking-wide text-muted">
                <tr>
                    <th class="px-4 py-3">Plan</th>
                    <th class="px-4 py-3">Monthly</th>
                    <th class="px-4 py-3">Yearly</th>
                    <th class="px-4 py-3">User Limit</th>
                    <th class="px-4 py-3">Status</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-hairline">
                @foreach ($this->plans as $plan)
                    <tr>
                        <td class="px-4 py-3 text-ink">{{ $plan->name }}</td>
                        <td class="px-4 py-3 text-muted">₱{{ number_format($plan->monthly_price) }}</td>
                        <td class="px-4 py-3 text-muted">₱{{ number_format($plan->yearly_price) }}</td>
                        <td class="px-4 py-3 text-muted">{{ $plan->user_limit }}</td>
                        <td class="px-4 py-3">
                            <span class="rounded-full px-2.5 py-1 text-xs font-medium {{ $plan->is_active ? 'bg-green-50 text-green-700' : 'bg-app-bg text-muted' }}">
                                {{ $plan->is_active ? 'Active' : 'Inactive' }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-right">
                            <div class="flex justify-end gap-1">
                                <button type="button" wire:click="openEdit({{ $plan->id }})" class="rounded-lg p-2 text-muted hover:bg-app-bg hover:text-ink">
                                    <x-lucide-pencil class="h-4 w-4" />
                                </button>
                                <button type="button" wire:click="toggleActive({{ $plan->id }})" class="rounded-lg p-2 text-muted hover:bg-app-bg hover:text-ink">
                                    <x-lucide-power class="h-4 w-4" />
                                </button>
                            </div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    @if ($showFormModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
            <div class="w-full max-w-lg rounded-xl bg-surface p-6 shadow-xl">
                <h2 class="text-lg font-semibold text-ink">{{ $editingId ? 'Edit Plan' : 'Add Plan' }}</h2>

                <form wire:submit="save" class="mt-4 space-y-4">
                    <div>
                        <label class="mb-1 block text-sm font-medium text-ink">Plan Name</label>
                        <input wire:model="name" type="text" class="w-full rounded-lg border border-hairline px-3 py-2 text-sm">
                        @error('name') <p class="mt-1 text-sm text-danger-500">{{ $message }}</p> @enderror
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="mb-1 block text-sm font-medium text-ink">Monthly Price (₱)</label>
                            <input wire:model="monthly_price" type="number" min="0" class="w-full rounded-lg border border-hairline px-3 py-2 text-sm">
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium text-ink">Yearly Price (₱)</label>
                            <input wire:model="yearly_price" type="number" min="0" class="w-full rounded-lg border border-hairline px-3 py-2 text-sm">
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="mb-1 block text-sm font-medium text-ink">User Limit</label>
                            <input wire:model="user_limit" type="number" min="1" class="w-full rounded-lg border border-hairline px-3 py-2 text-sm">
                            @error('user_limit') <p class="mt-1 text-sm text-danger-500">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium text-ink">Sort Order</label>
                            <input wire:model="sort_order" type="number" min="0" class="w-full rounded-lg border border-hairline px-3 py-2 text-sm">
                        </div>
                    </div>

                    <div>
                        <label class="mb-1 block text-sm font-medium text-ink">Features (one per line)</label>
                        <textarea wire:model="features_text" rows="4" class="w-full rounded-lg border border-hairline px-3 py-2 text-sm"></textarea>
                    </div>

                    <label class="flex items-center gap-2 text-sm text-ink">
                        <input wire:model="is_active" type="checkbox" class="rounded border-hairline text-primary-600">
                        Active (visible on pricing page)
                    </label>

                    <div class="flex justify-end gap-2 pt-2">
                        <button type="button" wire:click="$set('showFormModal', false)" class="rounded-lg border border-hairline px-4 py-2 text-sm font-medium text-ink hover:bg-app-bg">
                            Cancel
                        </button>
                        <button type="submit" class="rounded-lg bg-primary-600 px-4 py-2 text-sm font-semibold text-white hover:bg-primary-700">
                            Save Plan
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>

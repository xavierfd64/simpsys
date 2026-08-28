<?php

use App\Models\Promotion;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts.admin')] #[Title('Promotions')] class extends Component
{
    public bool $showFormModal = false;

    public ?int $editingId = null;

    public string $code = '';

    public string $discount_type = 'percentage';

    public string $discount_value = '';

    public string $starts_at = '';

    public string $ends_at = '';

    public string $usage_limit = '';

    public bool $is_active = true;

    public function getPromotionsProperty()
    {
        return Promotion::query()->latest('id')->get();
    }

    public function openCreate(): void
    {
        $this->reset(['editingId', 'code', 'discount_value', 'starts_at', 'ends_at', 'usage_limit']);
        $this->discount_type = 'percentage';
        $this->is_active = true;
        $this->showFormModal = true;
    }

    public function openEdit(int $id): void
    {
        $promo = Promotion::findOrFail($id);
        $this->editingId = $promo->id;
        $this->code = $promo->code;
        $this->discount_type = $promo->discount_type;
        $this->discount_value = (string) $promo->discount_value;
        $this->starts_at = $promo->starts_at?->toDateString() ?? '';
        $this->ends_at = $promo->ends_at?->toDateString() ?? '';
        $this->usage_limit = (string) $promo->usage_limit;
        $this->is_active = $promo->is_active;
        $this->showFormModal = true;
    }

    public function save(): void
    {
        $data = $this->validate([
            'code' => ['required', 'string', 'max:50', \Illuminate\Validation\Rule::unique('promotions', 'code')->ignore($this->editingId)],
            'discount_type' => ['required', 'in:percentage,fixed'],
            'discount_value' => ['required', 'integer', 'min:1'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date'],
            'usage_limit' => ['nullable', 'integer', 'min:1'],
        ]);

        $attributes = [
            'code' => strtoupper($data['code']),
            'discount_type' => $data['discount_type'],
            'discount_value' => $data['discount_value'],
            'starts_at' => $data['starts_at'] ?: null,
            'ends_at' => $data['ends_at'] ?: null,
            'usage_limit' => $data['usage_limit'] ?: null,
            'is_active' => $this->is_active,
        ];

        if ($this->editingId) {
            Promotion::findOrFail($this->editingId)->update($attributes);
        } else {
            Promotion::create($attributes);
        }

        $this->showFormModal = false;
        session()->flash('status', 'Promotion saved.');
    }

    public function toggleActive(int $id): void
    {
        $promo = Promotion::findOrFail($id);
        $promo->update(['is_active' => ! $promo->is_active]);
    }
}; ?>

<div class="space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-semibold text-ink">Promotions</h1>
            <p class="mt-1 text-sm text-muted">Promo codes for marketing campaigns.</p>
        </div>
        <button type="button" wire:click="openCreate" class="rounded-lg bg-primary-600 px-4 py-2 text-sm font-semibold text-white hover:bg-primary-700">
            Add Promotion
        </button>
    </div>

    @if (session('status'))
        <div class="rounded-lg bg-green-50 px-4 py-3 text-sm text-green-700">{{ session('status') }}</div>
    @endif

    <div class="overflow-x-auto rounded-xl border border-hairline bg-surface">
        <table class="w-full min-w-[700px] text-left text-sm">
            <thead class="border-b border-hairline bg-app-bg text-xs font-medium uppercase tracking-wide text-muted">
                <tr>
                    <th class="px-4 py-3">Code</th>
                    <th class="px-4 py-3">Discount</th>
                    <th class="px-4 py-3">Valid</th>
                    <th class="px-4 py-3">Usage</th>
                    <th class="px-4 py-3">Status</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-hairline">
                @foreach ($this->promotions as $promo)
                    <tr>
                        <td class="px-4 py-3 font-mono text-ink">{{ $promo->code }}</td>
                        <td class="px-4 py-3 text-muted">
                            {{ $promo->discount_type === 'percentage' ? $promo->discount_value.'%' : '₱'.number_format($promo->discount_value) }}
                        </td>
                        <td class="px-4 py-3 text-muted">
                            {{ $promo->starts_at?->format('M j, Y') ?? 'Anytime' }} &ndash; {{ $promo->ends_at?->format('M j, Y') ?? 'No end' }}
                        </td>
                        <td class="px-4 py-3 text-muted">{{ $promo->times_used }}{{ $promo->usage_limit ? ' / '.$promo->usage_limit : '' }}</td>
                        <td class="px-4 py-3">
                            <span class="rounded-full px-2.5 py-1 text-xs font-medium {{ $promo->is_active ? 'bg-green-50 text-green-700' : 'bg-app-bg text-muted' }}">
                                {{ $promo->is_active ? 'Active' : 'Inactive' }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-right">
                            <div class="flex justify-end gap-1">
                                <button type="button" wire:click="openEdit({{ $promo->id }})" class="rounded-lg p-2 text-muted hover:bg-app-bg hover:text-ink">
                                    <x-lucide-pencil class="h-4 w-4" />
                                </button>
                                <button type="button" wire:click="toggleActive({{ $promo->id }})" class="rounded-lg p-2 text-muted hover:bg-app-bg hover:text-ink">
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
            <div class="w-full max-w-md rounded-xl bg-surface p-6 shadow-xl">
                <h2 class="text-lg font-semibold text-ink">{{ $editingId ? 'Edit Promotion' : 'Add Promotion' }}</h2>

                <form wire:submit="save" class="mt-4 space-y-4">
                    <div>
                        <label class="mb-1 block text-sm font-medium text-ink">Promo Code</label>
                        <input wire:model="code" type="text" class="w-full rounded-lg border border-hairline px-3 py-2 text-sm uppercase">
                        @error('code') <p class="mt-1 text-sm text-danger-500">{{ $message }}</p> @enderror
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="mb-1 block text-sm font-medium text-ink">Type</label>
                            <select wire:model="discount_type" class="w-full rounded-lg border border-hairline px-3 py-2 text-sm">
                                <option value="percentage">Percentage</option>
                                <option value="fixed">Fixed Amount</option>
                            </select>
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium text-ink">Value</label>
                            <input wire:model="discount_value" type="number" min="1" class="w-full rounded-lg border border-hairline px-3 py-2 text-sm">
                            @error('discount_value') <p class="mt-1 text-sm text-danger-500">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="mb-1 block text-sm font-medium text-ink">Starts</label>
                            <input wire:model="starts_at" type="date" class="w-full rounded-lg border border-hairline px-3 py-2 text-sm">
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium text-ink">Ends</label>
                            <input wire:model="ends_at" type="date" class="w-full rounded-lg border border-hairline px-3 py-2 text-sm">
                        </div>
                    </div>

                    <div>
                        <label class="mb-1 block text-sm font-medium text-ink">Usage Limit (optional)</label>
                        <input wire:model="usage_limit" type="number" min="1" class="w-full max-w-xs rounded-lg border border-hairline px-3 py-2 text-sm">
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
                            Save Promotion
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>

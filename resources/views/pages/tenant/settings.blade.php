<?php

use App\Enums\OrderType;
use App\Models\PaymentMethod;
use App\Services\TenantContext;
use App\Support\TenantStorage;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

new #[Layout('layouts.app')] #[Title('Business Settings')] class extends Component
{
    use WithFileUploads;

    public string $tab = 'business';

    // Business info
    public string $name = '';

    public string $timezone = '';

    public $logo = null;

    public ?string $current_logo_path = null;

    // Payment methods
    public bool $showPaymentMethodModal = false;

    public ?int $editingPaymentMethodId = null;

    public string $payment_method_name = '';

    // Order types
    public bool $order_types_enabled = false;

    public bool $dine_in_enabled = true;

    public bool $to_go_enabled = true;

    public string $default_order_type = 'to_go';

    public bool $kitchen_enabled = true;

    public function mount(TenantContext $tenantContext): void
    {
        $tenant = $tenantContext->tenant();
        $this->name = $tenant->name;
        $this->timezone = $tenant->timezone;
        $this->current_logo_path = $tenant->logo_path;

        $settings = $tenant->settings;
        $this->order_types_enabled = (bool) $settings?->order_types_enabled;
        $this->dine_in_enabled = (bool) $settings?->dine_in_enabled;
        $this->to_go_enabled = (bool) $settings?->to_go_enabled;
        $this->default_order_type = $settings?->default_order_type?->value ?? 'to_go';
        $this->kitchen_enabled = (bool) ($settings?->kitchen_enabled ?? true);
    }

    public function getPaymentMethodsProperty()
    {
        return PaymentMethod::query()->orderBy('sort_order')->get();
    }

    public function saveBusinessInfo(TenantContext $tenantContext): void
    {
        $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'timezone' => ['required', Rule::in(DateTimeZone::listIdentifiers())],
            'logo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ]);

        $tenant = $tenantContext->tenant();
        $attributes = [
            'name' => $this->name,
            'timezone' => $this->timezone,
        ];

        if ($this->logo) {
            TenantStorage::delete($tenant->logo_path);
            $attributes['logo_path'] = TenantStorage::storeImage($this->logo, 'branding', $tenant);
        }

        $tenant->update($attributes);

        $this->logo = null;
        $this->current_logo_path = $tenant->logo_path;

        session()->flash('status', 'Business information updated.');
    }

    public function removeLogo(TenantContext $tenantContext): void
    {
        $tenant = $tenantContext->tenant();
        TenantStorage::delete($tenant->logo_path);
        $tenant->update(['logo_path' => null]);

        $this->current_logo_path = null;
        session()->flash('status', 'Logo removed.');
    }

    public function saveOrderTypeSettings(TenantContext $tenantContext): void
    {
        $this->validate([
            'default_order_type' => ['required', 'in:dine_in,to_go'],
        ]);

        $tenantContext->tenant()->settings()->update([
            'order_types_enabled' => $this->order_types_enabled,
            'dine_in_enabled' => $this->dine_in_enabled,
            'to_go_enabled' => $this->to_go_enabled,
            'default_order_type' => $this->default_order_type,
            'kitchen_enabled' => $this->kitchen_enabled,
        ]);

        session()->flash('status', 'Order type settings updated.');
    }

    public function openCreatePaymentMethod(): void
    {
        $this->reset(['editingPaymentMethodId', 'payment_method_name']);
        $this->showPaymentMethodModal = true;
    }

    public function openEditPaymentMethod(int $id): void
    {
        $method = PaymentMethod::findOrFail($id);
        $this->editingPaymentMethodId = $method->id;
        $this->payment_method_name = $method->name;
        $this->showPaymentMethodModal = true;
    }

    public function savePaymentMethod(): void
    {
        $this->validate(['payment_method_name' => ['required', 'string', 'max:255']]);

        if ($this->editingPaymentMethodId) {
            PaymentMethod::findOrFail($this->editingPaymentMethodId)->update(['name' => $this->payment_method_name]);
        } else {
            $count = PaymentMethod::query()->count();
            PaymentMethod::create(['name' => $this->payment_method_name, 'is_enabled' => true, 'sort_order' => $count]);
        }

        $this->showPaymentMethodModal = false;
        session()->flash('status', 'Payment method saved.');
    }

    public function togglePaymentMethod(int $id): void
    {
        $method = PaymentMethod::findOrFail($id);
        $method->update(['is_enabled' => ! $method->is_enabled]);
    }
}; ?>

<div class="max-w-3xl space-y-6">
    <div>
        <h1 class="text-2xl font-semibold text-ink">Business Settings</h1>
        <p class="mt-1 text-sm text-muted">Manage your business information and preferences.</p>
    </div>

    @if (session('status'))
        <div class="rounded-lg bg-green-50 px-4 py-3 text-sm text-green-700">{{ session('status') }}</div>
    @endif

    <div class="flex gap-1 overflow-x-auto border-b border-hairline">
        @foreach (['business' => 'Business Info', 'payment_methods' => 'Payment Methods', 'order_types' => 'Order Types', 'kitchen' => 'Kitchen'] as $key => $label)
            <button type="button" wire:click="$set('tab', '{{ $key }}')"
                    class="shrink-0 border-b-2 px-4 py-2 text-sm font-medium {{ $tab === $key ? 'border-primary-600 text-primary-600' : 'border-transparent text-muted hover:text-ink' }}">
                {{ $label }}
            </button>
        @endforeach
    </div>

    @if ($tab === 'business')
        <div class="rounded-xl border border-hairline bg-surface p-6">
            <h2 class="text-base font-semibold text-ink">Business Information</h2>

            <form wire:submit="saveBusinessInfo" class="mt-4 space-y-4">
                <div>
                    <label for="name" class="mb-1 block text-sm font-medium text-ink">Business Name</label>
                    <input wire:model="name" id="name" type="text"
                           class="w-full max-w-md rounded-lg border border-hairline px-3 py-2.5 text-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500">
                    @error('name') <p class="mt-1 text-sm text-danger-500">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="timezone" class="mb-1 block text-sm font-medium text-ink">Timezone</label>
                    <select wire:model="timezone" id="timezone"
                            class="w-full max-w-md rounded-lg border border-hairline px-3 py-2.5 text-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500">
                        @foreach (\DateTimeZone::listIdentifiers() as $tz)
                            <option value="{{ $tz }}">{{ $tz }}</option>
                        @endforeach
                    </select>
                    @error('timezone') <p class="mt-1 text-sm text-danger-500">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium text-ink">Business Logo</label>
                    <div class="flex items-center gap-4">
                        <div class="flex h-16 w-16 shrink-0 items-center justify-center overflow-hidden rounded-lg border border-hairline bg-app-bg">
                            @if ($logo)
                                <img src="{{ $logo->temporaryUrl() }}" class="h-full w-full object-cover" alt="Logo preview">
                            @elseif ($current_logo_path)
                                <img src="{{ TenantStorage::url($current_logo_path) }}" class="h-full w-full object-cover" alt="Business logo">
                            @else
                                <x-lucide-store class="h-7 w-7 text-muted" />
                            @endif
                        </div>
                        <div class="flex-1">
                            <input wire:model="logo" type="file" accept="image/*" class="w-full text-sm">
                            @error('logo') <p class="mt-1 text-sm text-danger-500">{{ $message }}</p> @enderror
                            @if ($current_logo_path && ! $logo)
                                <button type="button" wire:click="removeLogo" wire:confirm="Remove the business logo?"
                                        class="mt-1 text-xs font-medium text-danger-500 hover:underline">
                                    Remove logo
                                </button>
                            @endif
                        </div>
                    </div>
                </div>

                <button type="submit"
                        class="rounded-lg bg-primary-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-primary-700"
                        wire:loading.attr="disabled" wire:target="saveBusinessInfo">
                    Save Changes
                </button>
            </form>
        </div>
    @endif

    @if ($tab === 'payment_methods')
        <div class="rounded-xl border border-hairline bg-surface p-6">
            <div class="flex items-center justify-between">
                <h2 class="text-base font-semibold text-ink">Payment Methods</h2>
                <button type="button" wire:click="openCreatePaymentMethod" class="rounded-lg bg-primary-600 px-4 py-2 text-sm font-semibold text-white hover:bg-primary-700">
                    Add Payment Method
                </button>
            </div>

            <ul class="mt-4 divide-y divide-hairline">
                @foreach ($this->paymentMethods as $method)
                    <li class="flex items-center justify-between py-3">
                        <span class="text-sm text-ink">{{ $method->name }}</span>
                        <div class="flex items-center gap-3">
                            <label class="relative inline-flex cursor-pointer items-center">
                                <input type="checkbox" wire:click="togglePaymentMethod({{ $method->id }})" @checked($method->is_enabled) class="peer sr-only">
                                <div class="h-5 w-9 rounded-full bg-app-bg peer-checked:bg-primary-600 after:absolute after:left-0.5 after:top-0.5 after:h-4 after:w-4 after:rounded-full after:bg-white after:transition-all peer-checked:after:translate-x-4"></div>
                            </label>
                            <button type="button" wire:click="openEditPaymentMethod({{ $method->id }})" class="text-muted hover:text-ink">
                                <x-lucide-pencil class="h-4 w-4" />
                            </button>
                        </div>
                    </li>
                @endforeach
            </ul>
            <p class="mt-2 text-xs text-muted">Disabled methods can't be used for new sales but stay attached to past transactions.</p>
        </div>
    @endif

    @if ($tab === 'order_types')
        <div class="rounded-xl border border-hairline bg-surface p-6">
            <h2 class="text-base font-semibold text-ink">Order Type Settings</h2>

            <form wire:submit="saveOrderTypeSettings" class="mt-4 space-y-4">
                <label class="flex items-center gap-2 text-sm text-ink">
                    <input wire:model.live="order_types_enabled" type="checkbox" class="rounded border-hairline text-primary-600">
                    Enable order types in POS
                </label>

                @if ($order_types_enabled)
                    <div class="ml-6 space-y-2 border-l border-hairline pl-4">
                        <label class="flex items-center gap-2 text-sm text-ink">
                            <input wire:model="dine_in_enabled" type="checkbox" class="rounded border-hairline text-primary-600">
                            Enable DINE-IN
                        </label>
                        <label class="flex items-center gap-2 text-sm text-ink">
                            <input wire:model="to_go_enabled" type="checkbox" class="rounded border-hairline text-primary-600">
                            Enable TO-GO
                        </label>
                        <div>
                            <label class="mb-1 block text-sm font-medium text-ink">Default Order Type</label>
                            <select wire:model="default_order_type" class="w-full max-w-xs rounded-lg border border-hairline px-3 py-2 text-sm">
                                <option value="dine_in">Dine-In</option>
                                <option value="to_go">To-Go</option>
                            </select>
                        </div>
                    </div>
                @endif

                <button type="submit" class="rounded-lg bg-primary-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-primary-700">
                    Save Changes
                </button>
            </form>
        </div>
    @endif

    @if ($tab === 'kitchen')
        <div class="rounded-xl border border-hairline bg-surface p-6">
            <h2 class="text-base font-semibold text-ink">Kitchen Settings</h2>
            <p class="mt-1 text-sm text-muted">Made-to-order items always create a kitchen ticket. This controls whether the Kitchen screen appears in navigation.</p>

            <form wire:submit="saveOrderTypeSettings" class="mt-4 space-y-4">
                <label class="flex items-center gap-2 text-sm text-ink">
                    <input wire:model="kitchen_enabled" type="checkbox" class="rounded border-hairline text-primary-600">
                    Enable Kitchen screen
                </label>

                <button type="submit" class="rounded-lg bg-primary-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-primary-700">
                    Save Changes
                </button>
            </form>
        </div>
    @endif

    @if ($showPaymentMethodModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
            <div class="w-full max-w-sm rounded-xl bg-surface p-6 shadow-xl">
                <h2 class="text-lg font-semibold text-ink">{{ $editingPaymentMethodId ? 'Edit Payment Method' : 'Add Payment Method' }}</h2>

                <form wire:submit="savePaymentMethod" class="mt-4 space-y-4">
                    <div>
                        <label class="mb-1 block text-sm font-medium text-ink">Name</label>
                        <input wire:model="payment_method_name" type="text" placeholder="GCash, Maya, Bank Transfer..."
                               class="w-full rounded-lg border border-hairline px-3 py-2 text-sm">
                        @error('payment_method_name') <p class="mt-1 text-sm text-danger-500">{{ $message }}</p> @enderror
                    </div>
                    <div class="flex justify-end gap-2">
                        <button type="button" wire:click="$set('showPaymentMethodModal', false)" class="rounded-lg border border-hairline px-4 py-2 text-sm font-medium text-ink hover:bg-app-bg">
                            Cancel
                        </button>
                        <button type="submit" class="rounded-lg bg-primary-600 px-4 py-2 text-sm font-semibold text-white hover:bg-primary-700">
                            Save
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>

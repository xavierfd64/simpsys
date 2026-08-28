<?php

use App\Services\TenantContext;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts.app')] #[Title('Business Settings')] class extends Component
{
    public string $name = '';

    public string $timezone = '';

    public function mount(TenantContext $tenantContext): void
    {
        $tenant = $tenantContext->tenant();
        $this->name = $tenant->name;
        $this->timezone = $tenant->timezone;
    }

    public function save(TenantContext $tenantContext): void
    {
        $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'timezone' => ['required', Rule::in(\DateTimeZone::listIdentifiers())],
        ]);

        $tenantContext->tenant()->update([
            'name' => $this->name,
            'timezone' => $this->timezone,
        ]);

        session()->flash('status', 'Business information updated.');
    }
}; ?>

<div class="max-w-2xl space-y-6">
    <div>
        <h1 class="text-2xl font-semibold text-ink">Business Settings</h1>
        <p class="mt-1 text-sm text-muted">Manage your business information.</p>
    </div>

    @if (session('status'))
        <div class="rounded-lg bg-green-50 px-4 py-3 text-sm text-green-700">{{ session('status') }}</div>
    @endif

    <div class="rounded-xl border border-hairline bg-surface p-6">
        <h2 class="text-base font-semibold text-ink">Business Information</h2>

        <form wire:submit="save" class="mt-4 space-y-4">
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

            <button type="submit"
                    class="rounded-lg bg-primary-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-primary-700"
                    wire:loading.attr="disabled" wire:target="save">
                Save Changes
            </button>
        </form>
    </div>
</div>

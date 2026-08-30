<?php

use App\Models\PlatformSetting;
use App\Support\TenantStorage;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

new #[Layout('layouts.admin')] #[Title('Platform Settings')] class extends Component
{
    use WithFileUploads;

    public string $platform_name = '';

    public string $support_email = '';

    public string $support_phone = '';

    public $logo = null;

    public $favicon = null;

    public ?string $current_logo_path = null;

    public ?string $current_favicon_path = null;

    public function mount(): void
    {
        $settings = PlatformSetting::current();

        $this->platform_name = (string) $settings->platform_name;
        $this->support_email = (string) $settings->support_email;
        $this->support_phone = (string) $settings->support_phone;
        $this->current_logo_path = $settings->logo_path;
        $this->current_favicon_path = $settings->favicon_path;
    }

    public function save(): void
    {
        $data = $this->validate([
            'platform_name' => ['nullable', 'string', 'max:255'],
            'support_email' => ['nullable', 'email', 'max:255'],
            'support_phone' => ['nullable', 'string', 'max:50'],
            'logo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'favicon' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp,ico', 'max:512'],
        ]);

        $settings = PlatformSetting::current();

        $attributes = [
            'platform_name' => $data['platform_name'] ?: null,
            'support_email' => $data['support_email'] ?: null,
            'support_phone' => $data['support_phone'] ?: null,
        ];

        if ($this->logo) {
            TenantStorage::delete($settings->logo_path);
            $attributes['logo_path'] = TenantStorage::storePlatformImage($this->logo, 'branding');
        }

        if ($this->favicon) {
            TenantStorage::delete($settings->favicon_path);
            $attributes['favicon_path'] = TenantStorage::storePlatformImage($this->favicon, 'branding');
        }

        $settings->update($attributes);

        $this->logo = null;
        $this->favicon = null;
        $this->current_logo_path = $settings->logo_path;
        $this->current_favicon_path = $settings->favicon_path;

        session()->flash('status', 'Platform settings updated.');
    }

    public function removeLogo(): void
    {
        $settings = PlatformSetting::current();
        TenantStorage::delete($settings->logo_path);
        $settings->update(['logo_path' => null]);
        $this->current_logo_path = null;
    }

    public function removeFavicon(): void
    {
        $settings = PlatformSetting::current();
        TenantStorage::delete($settings->favicon_path);
        $settings->update(['favicon_path' => null]);
        $this->current_favicon_path = null;
    }
}; ?>

<div class="max-w-2xl space-y-6">
    <div>
        <h1 class="text-2xl font-semibold text-ink">Platform Settings</h1>
        <p class="mt-1 text-sm text-muted">Branding and contact details shown across the public site and every business's dashboard.</p>
    </div>

    @if (session('status'))
        <div class="rounded-lg bg-green-50 px-4 py-3 text-sm text-green-700">{{ session('status') }}</div>
    @endif

    <div class="rounded-xl border border-hairline bg-surface p-6">
        <h2 class="text-base font-semibold text-ink">Branding</h2>

        <form wire:submit="save" class="mt-4 space-y-4">
            <div>
                <label class="mb-1 block text-sm font-medium text-ink">Platform Name</label>
                <input wire:model="platform_name" type="text" placeholder="{{ config('app.name') }}"
                       class="w-full max-w-md rounded-lg border border-hairline px-3 py-2.5 text-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500">
                @error('platform_name') <p class="mt-1 text-sm text-danger-500">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="mb-1 block text-sm font-medium text-ink">Platform Logo</label>
                <div class="flex items-center gap-4">
                    <div class="flex h-16 w-16 shrink-0 items-center justify-center overflow-hidden rounded-lg border border-hairline bg-app-bg">
                        @if ($logo)
                            <img src="{{ $logo->temporaryUrl() }}" class="h-full w-full object-cover" alt="Logo preview">
                        @elseif ($current_logo_path)
                            <img src="{{ TenantStorage::url($current_logo_path) }}" class="h-full w-full object-cover" alt="Platform logo">
                        @else
                            <x-lucide-shield class="h-7 w-7 text-muted" />
                        @endif
                    </div>
                    <div class="flex-1">
                        <input wire:model="logo" type="file" accept="image/*" class="w-full text-sm">
                        @error('logo') <p class="mt-1 text-sm text-danger-500">{{ $message }}</p> @enderror
                        @if ($current_logo_path && ! $logo)
                            <button type="button" wire:click="removeLogo" wire:confirm="Remove the platform logo?"
                                    class="mt-1 text-xs font-medium text-danger-500 hover:underline">
                                Remove logo
                            </button>
                        @endif
                    </div>
                </div>
            </div>

            <div>
                <label class="mb-1 block text-sm font-medium text-ink">Favicon</label>
                <div class="flex items-center gap-4">
                    <div class="flex h-10 w-10 shrink-0 items-center justify-center overflow-hidden rounded-lg border border-hairline bg-app-bg">
                        @if ($favicon)
                            <img src="{{ $favicon->temporaryUrl() }}" class="h-full w-full object-cover" alt="Favicon preview">
                        @elseif ($current_favicon_path)
                            <img src="{{ TenantStorage::url($current_favicon_path) }}" class="h-full w-full object-cover" alt="Favicon">
                        @else
                            <x-lucide-image class="h-5 w-5 text-muted" />
                        @endif
                    </div>
                    <div class="flex-1">
                        <input wire:model="favicon" type="file" accept="image/png,image/x-icon,image/webp" class="w-full text-sm">
                        @error('favicon') <p class="mt-1 text-sm text-danger-500">{{ $message }}</p> @enderror
                        @if ($current_favicon_path && ! $favicon)
                            <button type="button" wire:click="removeFavicon" wire:confirm="Remove the favicon?"
                                    class="mt-1 text-xs font-medium text-danger-500 hover:underline">
                                Remove favicon
                            </button>
                        @endif
                    </div>
                </div>
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="mb-1 block text-sm font-medium text-ink">Support Email</label>
                    <input wire:model="support_email" type="email" placeholder="support@example.com"
                           class="w-full rounded-lg border border-hairline px-3 py-2.5 text-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500">
                    @error('support_email') <p class="mt-1 text-sm text-danger-500">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-ink">Support Contact Number</label>
                    <input wire:model="support_phone" type="text" placeholder="+63 900 000 0000"
                           class="w-full rounded-lg border border-hairline px-3 py-2.5 text-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500">
                    @error('support_phone') <p class="mt-1 text-sm text-danger-500">{{ $message }}</p> @enderror
                </div>
            </div>

            <button type="submit"
                    class="rounded-lg bg-primary-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-primary-700"
                    wire:loading.attr="disabled" wire:target="save">
                Save Changes
            </button>
        </form>
    </div>
</div>

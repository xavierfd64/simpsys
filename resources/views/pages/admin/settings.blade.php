<?php

use App\Models\PlatformSetting;
use App\Support\MailConfigurator;
use App\Support\TenantStorage;
use Illuminate\Support\Facades\Mail;
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

    public string $mail_mailer = 'smtp';

    public string $mail_host = '';

    public string $mail_port = '';

    public string $mail_encryption = 'tls';

    public string $mail_username = '';

    public string $mail_password = '';

    public string $mail_from_address = '';

    public string $mail_from_name = '';

    public bool $has_mail_password = false;

    public string $test_email_address = '';

    public ?string $test_email_status = null;

    public string $test_email_message = '';

    public function mount(): void
    {
        $settings = PlatformSetting::current();

        $this->platform_name = (string) $settings->platform_name;
        $this->support_email = (string) $settings->support_email;
        $this->support_phone = (string) $settings->support_phone;
        $this->current_logo_path = $settings->logo_path;
        $this->current_favicon_path = $settings->favicon_path;

        $this->mail_mailer = $settings->mail_mailer ?: 'smtp';
        $this->mail_host = (string) $settings->mail_host;
        $this->mail_port = (string) $settings->mail_port;
        $this->mail_encryption = $settings->mail_encryption ?: 'tls';
        $this->mail_username = (string) $settings->mail_username;
        $this->mail_from_address = (string) $settings->mail_from_address;
        $this->mail_from_name = (string) $settings->mail_from_name;
        $this->has_mail_password = filled($settings->mail_password);
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

    public function saveMail(): void
    {
        $data = $this->validate([
            'mail_mailer' => ['required', 'in:smtp,log'],
            'mail_host' => ['required_if:mail_mailer,smtp', 'nullable', 'string', 'max:255'],
            'mail_port' => ['required_if:mail_mailer,smtp', 'nullable', 'numeric'],
            'mail_encryption' => ['required', 'in:tls,ssl,none'],
            'mail_username' => ['nullable', 'string', 'max:255'],
            'mail_password' => ['nullable', 'string', 'max:255'],
            'mail_from_address' => ['required', 'email', 'max:255'],
            'mail_from_name' => ['required', 'string', 'max:255'],
        ], attributes: ['mail_host' => 'SMTP host', 'mail_port' => 'SMTP port']);

        $settings = PlatformSetting::current();

        $attributes = [
            'mail_mailer' => $data['mail_mailer'],
            'mail_host' => $data['mail_host'] ?: null,
            'mail_port' => $data['mail_port'] ?: null,
            'mail_encryption' => $data['mail_encryption'] === 'none' ? null : $data['mail_encryption'],
            'mail_username' => $data['mail_username'] ?: null,
            'mail_from_address' => $data['mail_from_address'],
            'mail_from_name' => $data['mail_from_name'],
        ];

        if (filled($this->mail_password)) {
            $attributes['mail_password'] = $this->mail_password;
        }

        $settings->update($attributes);

        $this->mail_password = '';
        $this->has_mail_password = filled($settings->fresh()->mail_password);

        session()->flash('status', 'Email settings updated.');
    }

    public function sendTestEmail(): void
    {
        $this->validate([
            'test_email_address' => ['required', 'email'],
        ]);

        $settings = PlatformSetting::current();

        MailConfigurator::apply([
            'mailer' => $this->mail_mailer,
            'host' => $this->mail_host,
            'port' => $this->mail_port,
            'encryption' => $this->mail_encryption === 'none' ? null : $this->mail_encryption,
            'username' => $this->mail_username,
            'password' => filled($this->mail_password) ? $this->mail_password : $settings->mail_password,
            'from_address' => $this->mail_from_address ?: $settings->mail_from_address,
            'from_name' => $this->mail_from_name ?: $settings->mail_from_name,
        ]);

        try {
            Mail::raw('This is a test email from '.($this->mail_from_name ?: config('app.name')).'. Your SMTP settings are working correctly.', function ($message) {
                $message->to($this->test_email_address)->subject('Test Email — SMTP Configuration');
            });

            $this->test_email_status = 'success';
            $this->test_email_message = "Test email sent to {$this->test_email_address}.";
        } catch (\Throwable $e) {
            report($e);
            $this->test_email_status = 'failure';
            $this->test_email_message = 'Failed to send: '.$e->getMessage();
        }
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

    <div class="rounded-xl border border-hairline bg-surface p-6">
        <h2 class="text-base font-semibold text-ink">Email (SMTP)</h2>
        <p class="mt-1 text-sm text-muted">Used to send account, branch, and billing notifications. Leave the mailer as "Log" to disable outgoing email — the system keeps working normally either way.</p>

        <form wire:submit="saveMail" class="mt-4 space-y-4">
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="mb-1 block text-sm font-medium text-ink">Mailer</label>
                    <select wire:model="mail_mailer" class="w-full rounded-lg border border-hairline px-3 py-2.5 text-sm">
                        <option value="smtp">SMTP</option>
                        <option value="log">Log (disabled — writes to log file only)</option>
                    </select>
                    @error('mail_mailer') <p class="mt-1 text-sm text-danger-500">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-ink">Encryption</label>
                    <select wire:model="mail_encryption" class="w-full rounded-lg border border-hairline px-3 py-2.5 text-sm">
                        <option value="tls">TLS (STARTTLS — typically port 587)</option>
                        <option value="ssl">SSL (implicit TLS — typically port 465)</option>
                        <option value="none">None</option>
                    </select>
                    @error('mail_encryption') <p class="mt-1 text-sm text-danger-500">{{ $message }}</p> @enderror
                </div>
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="mb-1 block text-sm font-medium text-ink">SMTP Host</label>
                    <input wire:model="mail_host" type="text" placeholder="smtp.yourprovider.com"
                           class="w-full rounded-lg border border-hairline px-3 py-2.5 text-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500">
                    @error('mail_host') <p class="mt-1 text-sm text-danger-500">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-ink">SMTP Port</label>
                    <input wire:model="mail_port" type="text" placeholder="587"
                           class="w-full rounded-lg border border-hairline px-3 py-2.5 text-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500">
                    @error('mail_port') <p class="mt-1 text-sm text-danger-500">{{ $message }}</p> @enderror
                </div>
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="mb-1 block text-sm font-medium text-ink">SMTP Username</label>
                    <input wire:model="mail_username" type="text" autocomplete="off"
                           class="w-full rounded-lg border border-hairline px-3 py-2.5 text-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500">
                    @error('mail_username') <p class="mt-1 text-sm text-danger-500">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-ink">SMTP Password</label>
                    <input wire:model="mail_password" type="password" autocomplete="new-password"
                           placeholder="{{ $has_mail_password ? '•••••••• (unchanged — leave blank to keep)' : '' }}"
                           class="w-full rounded-lg border border-hairline px-3 py-2.5 text-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500">
                    @error('mail_password') <p class="mt-1 text-sm text-danger-500">{{ $message }}</p> @enderror
                </div>
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="mb-1 block text-sm font-medium text-ink">From Name</label>
                    <input wire:model="mail_from_name" type="text" placeholder="{{ config('app.name') }}"
                           class="w-full rounded-lg border border-hairline px-3 py-2.5 text-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500">
                    @error('mail_from_name') <p class="mt-1 text-sm text-danger-500">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-ink">From Email</label>
                    <input wire:model="mail_from_address" type="email" placeholder="no-reply@yourdomain.com"
                           class="w-full rounded-lg border border-hairline px-3 py-2.5 text-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500">
                    @error('mail_from_address') <p class="mt-1 text-sm text-danger-500">{{ $message }}</p> @enderror
                </div>
            </div>

            <button type="submit"
                    class="rounded-lg bg-primary-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-primary-700"
                    wire:loading.attr="disabled" wire:target="saveMail">
                Save Email Settings
            </button>
        </form>

        <div class="mt-6 border-t border-hairline pt-6">
            <h3 class="text-sm font-semibold text-ink">Send Test Email</h3>
            <p class="mt-1 text-sm text-muted">Tests the settings above, even if you haven't saved them yet.</p>

            @if ($test_email_status === 'success')
                <div class="mt-3 rounded-lg bg-green-50 px-4 py-3 text-sm text-green-700">{{ $test_email_message }}</div>
            @elseif ($test_email_status === 'failure')
                <div class="mt-3 rounded-lg bg-red-50 px-4 py-3 text-sm text-danger-500">{{ $test_email_message }}</div>
            @endif

            <form wire:submit="sendTestEmail" class="mt-3 flex flex-wrap items-end gap-3">
                <div class="flex-1 min-w-[220px]">
                    <label class="mb-1 block text-sm font-medium text-ink">Recipient Email</label>
                    <input wire:model="test_email_address" type="email" placeholder="you@example.com"
                           class="w-full rounded-lg border border-hairline px-3 py-2.5 text-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500">
                    @error('test_email_address') <p class="mt-1 text-sm text-danger-500">{{ $message }}</p> @enderror
                </div>
                <button type="submit"
                        class="rounded-lg border border-hairline px-4 py-2.5 text-sm font-semibold text-ink hover:bg-app-bg"
                        wire:loading.attr="disabled" wire:target="sendTestEmail">
                    Send Test Email
                </button>
            </form>
        </div>
    </div>
</div>

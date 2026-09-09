<?php

use App\Models\AuditLog;
use App\Models\PlatformSetting;
use App\Services\PayPalClient;
use App\Support\ColorTheme;
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

    public string $theme_primary_color = '';

    public string $theme_font = 'inter';

    public bool $manual_payment_enabled = true;

    public bool $paypal_enabled = false;

    public string $paypal_environment = 'sandbox';

    public string $paypal_client_id = '';

    public string $paypal_client_secret = '';

    public bool $has_paypal_client_secret = false;

    public string $paypal_webhook_id = '';

    public string $paypal_currency = 'PHP';

    public ?string $paypal_test_status = null;

    public string $paypal_test_message = '';

    public bool $login_protection_enabled = true;

    public int $max_login_attempts = 5;

    public int $lockout_minutes = 15;

    public bool $captcha_enabled = true;

    public int $captcha_threshold = 3;

    public bool $rate_limiting_enabled = true;

    public function mount(): void
    {
        $settings = PlatformSetting::current();

        $this->login_protection_enabled = $settings->login_protection_enabled;
        $this->max_login_attempts = $settings->max_login_attempts;
        $this->lockout_minutes = $settings->lockout_minutes;
        $this->captcha_enabled = $settings->captcha_enabled;
        $this->captcha_threshold = $settings->captcha_threshold;
        $this->rate_limiting_enabled = $settings->rate_limiting_enabled;

        $this->platform_name = (string) $settings->platform_name;
        $this->support_email = (string) $settings->support_email;
        $this->support_phone = (string) $settings->support_phone;
        $this->current_logo_path = $settings->logo_path;
        $this->current_favicon_path = $settings->favicon_path;

        $this->theme_primary_color = $settings->theme_primary_color ?: ColorTheme::DEFAULT_PRIMARY;
        $this->theme_font = $settings->theme_font ?: 'inter';

        $this->mail_mailer = $settings->mail_mailer ?: 'smtp';
        $this->mail_host = (string) $settings->mail_host;
        $this->mail_port = (string) $settings->mail_port;
        $this->mail_encryption = $settings->mail_encryption ?: 'tls';
        $this->mail_username = (string) $settings->mail_username;
        $this->mail_from_address = (string) $settings->mail_from_address;
        $this->mail_from_name = (string) $settings->mail_from_name;
        $this->has_mail_password = filled($settings->mail_password);

        $this->manual_payment_enabled = $settings->manual_payment_enabled;
        $this->paypal_enabled = $settings->paypal_enabled;
        $this->paypal_environment = $settings->paypal_environment ?: 'sandbox';
        $this->paypal_client_id = (string) $settings->paypal_client_id;
        $this->has_paypal_client_secret = filled($settings->paypal_client_secret);
        $this->paypal_webhook_id = (string) $settings->paypal_webhook_id;
        $this->paypal_currency = $settings->paypal_currency ?: 'PHP';
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

    public function saveAppearance(): void
    {
        $data = $this->validate([
            'theme_primary_color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'theme_font' => ['required', 'in:'.implode(',', array_keys(PlatformSetting::FONTS))],
        ], attributes: ['theme_primary_color' => 'primary color']);

        if (! ColorTheme::isAccessible($data['theme_primary_color'])) {
            $this->addError('theme_primary_color', 'This color is too light to keep white button/badge text readable. Please choose a darker shade.');

            return;
        }

        PlatformSetting::current()->update([
            'theme_primary_color' => $data['theme_primary_color'],
            'theme_font' => $data['theme_font'],
        ]);

        session()->flash('status', 'Appearance updated.');
    }

    public function resetAppearance(): void
    {
        PlatformSetting::current()->update([
            'theme_primary_color' => null,
            'theme_font' => null,
        ]);

        $this->theme_primary_color = ColorTheme::DEFAULT_PRIMARY;
        $this->theme_font = 'inter';

        session()->flash('status', 'Appearance reset to default.');
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

    public function savePaymentMethods(): void
    {
        PlatformSetting::current()->update([
            'manual_payment_enabled' => $this->manual_payment_enabled,
        ]);

        session()->flash('status', 'Payment methods updated.');
    }

    /**
     * A transient (never-persisted) settings instance built from whatever
     * is currently in the form — mirrors the existing "Send Test Email"
     * pattern of testing not-yet-saved values, and never requires the
     * admin to save first just to find out credentials are wrong.
     */
    protected function transientPayPalSettings(): PlatformSetting
    {
        // Built from decrypted plaintext accessor values only — never from
        // getAttributes() (the raw, still-encrypted ciphertext), which
        // would otherwise get encrypted a second time the moment it's
        // assigned onto a new model instance and come back unreadable.
        $saved = PlatformSetting::current();
        $settings = new PlatformSetting;

        $settings->platform_name = $saved->platform_name;
        $settings->paypal_environment = $this->paypal_environment;
        $settings->paypal_client_id = $this->paypal_client_id;
        $settings->paypal_client_secret = filled($this->paypal_client_secret) ? $this->paypal_client_secret : $saved->paypal_client_secret;
        $settings->paypal_webhook_id = $this->paypal_webhook_id;

        return $settings;
    }

    public function testPayPalConnection(): void
    {
        $this->validate([
            'paypal_client_id' => ['required', 'string'],
            'paypal_environment' => ['required', 'in:sandbox,live'],
        ], attributes: ['paypal_client_id' => 'Client ID']);

        if (blank($this->paypal_client_secret) && ! $this->has_paypal_client_secret) {
            $this->paypal_test_status = 'failure';
            $this->paypal_test_message = 'Please enter a Client Secret first.';

            return;
        }

        $result = (new PayPalClient($this->transientPayPalSettings()))->testConnection();

        $this->paypal_test_status = $result['success'] ? 'success' : 'failure';
        $this->paypal_test_message = $result['message'];
    }

    public function savePayPalSettings(): void
    {
        $data = $this->validate([
            'paypal_environment' => ['required', 'in:sandbox,live'],
            'paypal_client_id' => ['nullable', 'string', 'max:255'],
            'paypal_webhook_id' => ['nullable', 'string', 'max:255'],
            'paypal_currency' => ['required', 'alpha', 'size:3'],
            'paypal_enabled' => ['boolean'],
        ], attributes: ['paypal_client_id' => 'Client ID', 'paypal_webhook_id' => 'Webhook ID']);

        $settings = PlatformSetting::current();
        $hasSecret = filled($this->paypal_client_secret) || filled($settings->paypal_client_secret);

        if ($this->paypal_enabled && (blank($data['paypal_client_id']) || ! $hasSecret)) {
            $this->addError('paypal_client_id', 'A Client ID and Client Secret are both required to enable PayPal.');

            return;
        }

        $attributes = [
            'paypal_enabled' => $this->paypal_enabled,
            'paypal_environment' => $data['paypal_environment'],
            'paypal_client_id' => $data['paypal_client_id'] ?: null,
            'paypal_webhook_id' => $data['paypal_webhook_id'] ?: null,
            'paypal_currency' => strtoupper($data['paypal_currency']),
        ];

        if (filled($this->paypal_client_secret)) {
            $attributes['paypal_client_secret'] = $this->paypal_client_secret;
        }

        $settings->update($attributes);

        $this->paypal_client_secret = '';
        $this->has_paypal_client_secret = filled($settings->fresh()->paypal_client_secret);

        session()->flash('status', 'PayPal settings updated.');
    }

    /**
     * Keeps these thresholds sane rather than trusting whatever number an
     * admin types — an accidental 0-minute lockout or a 1-attempt threshold
     * would either defeat the protection entirely or lock out real users
     * on a single typo. Bounds match the spec's own defaults (5 attempts /
     * 15 minutes / CAPTCHA after 3) as the intended, sensible middle.
     */
    public function saveSecuritySettings(): void
    {
        $data = $this->validate([
            'login_protection_enabled' => ['boolean'],
            'max_login_attempts' => ['required', 'integer', 'min:3', 'max:20'],
            'lockout_minutes' => ['required', 'integer', 'min:1', 'max:1440'],
            'captcha_enabled' => ['boolean'],
            'captcha_threshold' => ['required', 'integer', 'min:1', 'max:19'],
            'rate_limiting_enabled' => ['boolean'],
        ], attributes: [
            'max_login_attempts' => 'failed attempts',
            'lockout_minutes' => 'lockout duration',
            'captcha_threshold' => 'CAPTCHA trigger',
        ]);

        if ($data['captcha_threshold'] >= $data['max_login_attempts']) {
            $this->addError('captcha_threshold', 'The CAPTCHA trigger must be reached before the lockout threshold.');

            return;
        }

        PlatformSetting::current()->update($data);

        AuditLog::record('SECURITY_SETTING_CHANGED', [
            'user_id' => \Illuminate\Support\Facades\Auth::id(),
            'description' => 'Platform Admin updated login protection / CAPTCHA / rate limiting settings.',
            'metadata' => $data,
        ]);

        session()->flash('status', 'Security settings updated.');
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
        <h2 class="text-base font-semibold text-ink">Appearance</h2>
        <p class="mt-1 text-sm text-muted">System-wide primary color and font, applied to buttons, links, active nav, badges, and accents across the whole app. This is separate from a business's own logo/name branding.</p>

        <form wire:submit="saveAppearance" class="mt-4 space-y-4">
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="mb-1 block text-sm font-medium text-ink">Primary Color</label>
                    <div class="flex items-center gap-3">
                        <input wire:model.live="theme_primary_color" type="color" class="h-10 w-14 shrink-0 cursor-pointer rounded-lg border border-hairline p-1">
                        <input wire:model.live="theme_primary_color" type="text" maxlength="7" placeholder="#2563eb"
                               class="w-28 rounded-lg border border-hairline px-3 py-2 text-sm font-mono focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500">
                    </div>
                    @error('theme_primary_color') <p class="mt-1 text-sm text-danger-500">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-ink">Font</label>
                    <select wire:model.live="theme_font" class="w-full rounded-lg border border-hairline px-3 py-2.5 text-sm">
                        @foreach (\App\Models\PlatformSetting::FONTS as $key => $font)
                            <option value="{{ $key }}" style="font-family: {{ $font['stack'] }}">{{ $font['label'] }}</option>
                        @endforeach
                    </select>
                    @error('theme_font') <p class="mt-1 text-sm text-danger-500">{{ $message }}</p> @enderror
                </div>
            </div>

            <div>
                <p class="mb-2 text-xs font-medium uppercase tracking-wide text-muted">Preview</p>
                <div class="flex flex-wrap items-center gap-3 rounded-lg border border-hairline bg-app-bg p-4" style="font-family: {{ \App\Models\PlatformSetting::FONTS[$theme_font]['stack'] ?? 'inherit' }}">
                    <button type="button" class="rounded-lg px-4 py-2 text-sm font-semibold text-white" style="background-color: {{ $theme_primary_color }}">Primary Button</button>
                    <span class="rounded-full px-2.5 py-1 text-xs font-medium text-white" style="background-color: {{ $theme_primary_color }}">Badge</span>
                    <a href="#" onclick="return false" class="text-sm font-medium" style="color: {{ $theme_primary_color }}">A sample link</a>
                </div>
            </div>

            <div class="flex items-center gap-3">
                <button type="submit"
                        class="rounded-lg bg-primary-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-primary-700"
                        wire:loading.attr="disabled" wire:target="saveAppearance">
                    Save Appearance
                </button>
                <button type="button" wire:click="resetAppearance" wire:confirm="Reset the primary color and font to the default?"
                        class="rounded-lg border border-hairline px-4 py-2.5 text-sm font-semibold text-ink hover:bg-app-bg">
                    Reset to Default
                </button>
            </div>
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

    <div class="rounded-xl border border-hairline bg-surface p-6">
        <h2 class="text-base font-semibold text-ink">Payment Settings</h2>
        <p class="mt-1 text-sm text-muted">Choose which payment methods businesses can use to pay for their subscription.</p>

        <form wire:submit="savePaymentMethods" class="mt-4 space-y-3">
            <label class="flex items-center justify-between rounded-lg border border-hairline px-4 py-3">
                <span>
                    <span class="block text-sm font-medium text-ink">Manual / Fund Transfer</span>
                    <span class="block text-xs text-muted">A Platform Admin verifies the payment and records it manually — the existing workflow.</span>
                </span>
                <input wire:model="manual_payment_enabled" type="checkbox" class="h-5 w-5 rounded border-hairline text-primary-600 focus:ring-primary-500">
            </label>
            <label class="flex items-center justify-between rounded-lg border border-hairline px-4 py-3">
                <span>
                    <span class="block text-sm font-medium text-ink">PayPal</span>
                    <span class="block text-xs text-muted">Automatic — the subscription activates as soon as PayPal confirms payment. Configure below.</span>
                </span>
                <span class="text-xs font-medium {{ $paypal_enabled ? 'text-success-500' : 'text-muted' }}">{{ $paypal_enabled ? '✓ Enabled' : 'Disabled' }}</span>
            </label>
            <button type="submit"
                    class="rounded-lg bg-primary-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-primary-700"
                    wire:loading.attr="disabled" wire:target="savePaymentMethods">
                Save Payment Methods
            </button>
        </form>

        <div class="mt-6 border-t border-hairline pt-6">
            <div class="flex items-center justify-between">
                <h3 class="text-sm font-semibold text-ink">PayPal Configuration</h3>
                @if (PlatformSetting::current()->isPayPalConfigured())
                    <span class="inline-flex items-center gap-1 rounded-full bg-green-50 px-2.5 py-1 text-xs font-medium text-green-700">
                        <x-lucide-circle-check class="h-3.5 w-3.5" /> Connected
                    </span>
                @endif
            </div>
            <p class="mt-1 text-sm text-muted">
                Get your Client ID and Client Secret from your
                <a href="https://developer.paypal.com/dashboard/applications" target="_blank" rel="noopener" class="text-primary-600 hover:underline">PayPal Developer App</a>.
                Use Sandbox to test before switching to Live.
            </p>

            @if ($paypal_test_status === 'success')
                <div class="mt-3 rounded-lg bg-green-50 px-4 py-3 text-sm text-green-700">✓ {{ $paypal_test_message }}</div>
            @elseif ($paypal_test_status === 'failure')
                <div class="mt-3 rounded-lg bg-red-50 px-4 py-3 text-sm text-danger-500">✕ {{ $paypal_test_message }}</div>
            @endif

            <form wire:submit="savePayPalSettings" class="mt-4 space-y-4">
                <label class="flex items-center gap-2">
                    <input wire:model="paypal_enabled" type="checkbox" class="h-5 w-5 rounded border-hairline text-primary-600 focus:ring-primary-500">
                    <span class="text-sm font-medium text-ink">Enable PayPal at checkout</span>
                </label>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label class="mb-1 block text-sm font-medium text-ink">Environment</label>
                        <select wire:model="paypal_environment" class="w-full rounded-lg border border-hairline px-3 py-2.5 text-sm">
                            <option value="sandbox">Sandbox (testing)</option>
                            <option value="live">Live (real payments)</option>
                        </select>
                        @error('paypal_environment') <p class="mt-1 text-sm text-danger-500">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-ink">Currency</label>
                        <input wire:model="paypal_currency" type="text" maxlength="3" placeholder="PHP"
                               class="w-full rounded-lg border border-hairline px-3 py-2.5 text-sm uppercase focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500">
                        @error('paypal_currency') <p class="mt-1 text-sm text-danger-500">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium text-ink">Client ID</label>
                    <input wire:model="paypal_client_id" type="text" autocomplete="off"
                           class="w-full rounded-lg border border-hairline px-3 py-2.5 text-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500">
                    <p class="mt-1 text-xs text-muted">From your PayPal Developer App.</p>
                    @error('paypal_client_id') <p class="mt-1 text-sm text-danger-500">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium text-ink">Client Secret</label>
                    <input wire:model="paypal_client_secret" type="password" autocomplete="new-password"
                           placeholder="{{ $has_paypal_client_secret ? '•••••••••••••••• (unchanged — leave blank to keep)' : '' }}"
                           class="w-full rounded-lg border border-hairline px-3 py-2.5 text-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500">
                    <p class="mt-1 text-xs text-muted">From your PayPal Developer App. Never shown again once saved.</p>
                    @error('paypal_client_secret') <p class="mt-1 text-sm text-danger-500">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium text-ink">Webhook ID</label>
                    <input wire:model="paypal_webhook_id" type="text" autocomplete="off"
                           class="w-full rounded-lg border border-hairline px-3 py-2.5 text-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500">
                    <p class="mt-1 text-xs text-muted">
                        Create a webhook in your PayPal app pointing to
                        <code class="rounded bg-app-bg px-1 py-0.5">{{ url('/webhooks/paypal') }}</code>
                        (events: Checkout order approved, Payment capture completed/denied/pending, Payment capture reversed), then paste its Webhook ID here.
                    </p>
                    @error('paypal_webhook_id') <p class="mt-1 text-sm text-danger-500">{{ $message }}</p> @enderror
                </div>

                <div class="flex flex-wrap items-center gap-3">
                    <button type="button" wire:click="testPayPalConnection"
                            class="rounded-lg border border-hairline px-4 py-2.5 text-sm font-semibold text-ink hover:bg-app-bg"
                            wire:loading.attr="disabled" wire:target="testPayPalConnection">
                        Test PayPal Connection
                    </button>
                    <button type="submit"
                            class="rounded-lg bg-primary-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-primary-700"
                            wire:loading.attr="disabled" wire:target="savePayPalSettings">
                        Save Settings
                    </button>
                </div>
            </form>

            <div class="mt-6 rounded-lg bg-app-bg p-4">
                <p class="text-xs font-semibold uppercase tracking-wide text-muted">Where do I get these?</p>
                <ol class="mt-2 list-decimal space-y-1 pl-4 text-xs text-muted">
                    <li>Log in to your <a href="https://developer.paypal.com" target="_blank" rel="noopener" class="text-primary-600 hover:underline">PayPal Developer account</a>.</li>
                    <li>Create (or open) a PayPal App under Apps &amp; Credentials.</li>
                    <li>Copy the Client ID and Client Secret into the fields above.</li>
                    <li>Add a webhook pointing to the URL shown above, then paste its Webhook ID.</li>
                    <li>Click <strong>Test PayPal Connection</strong> to confirm the credentials work.</li>
                    <li>Enable PayPal and save — test a real Sandbox payment before switching to Live.</li>
                </ol>
            </div>
        </div>
    </div>

    <div class="rounded-xl border border-hairline bg-surface p-6">
        <h2 class="text-base font-semibold text-ink">Security</h2>
        <p class="mt-1 text-sm text-muted">Login brute-force protection, CAPTCHA, and rate limiting. Safe defaults are already in effect — only change these if you know what you're doing.</p>

        <form wire:submit="saveSecuritySettings" class="mt-4 space-y-4">
            <label class="flex items-center justify-between rounded-lg border border-hairline px-4 py-3">
                <span>
                    <span class="block text-sm font-medium text-ink">Login Protection</span>
                    <span class="block text-xs text-muted">Enforced server-side regardless of what the browser sends — locks an account out after too many failed attempts.</span>
                </span>
                <input wire:model="login_protection_enabled" type="checkbox" class="h-5 w-5 rounded border-hairline text-primary-600 focus:ring-primary-500">
            </label>

            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="mb-1 block text-sm font-medium text-ink">Failed Attempts Before Lockout</label>
                    <input wire:model="max_login_attempts" type="number" min="3" max="20"
                           class="w-full rounded-lg border border-hairline px-3 py-2.5 text-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500">
                    @error('max_login_attempts') <p class="mt-1 text-sm text-danger-500">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-ink">Lockout Duration (minutes)</label>
                    <input wire:model="lockout_minutes" type="number" min="1" max="1440"
                           class="w-full rounded-lg border border-hairline px-3 py-2.5 text-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500">
                    @error('lockout_minutes') <p class="mt-1 text-sm text-danger-500">{{ $message }}</p> @enderror
                </div>
            </div>

            <label class="flex items-center justify-between rounded-lg border border-hairline px-4 py-3">
                <span>
                    <span class="block text-sm font-medium text-ink">CAPTCHA</span>
                    <span class="block text-xs text-muted">A simple server-side math question — no external service, generated fresh each time.</span>
                </span>
                <input wire:model="captcha_enabled" type="checkbox" class="h-5 w-5 rounded border-hairline text-primary-600 focus:ring-primary-500">
            </label>

            <div>
                <label class="mb-1 block text-sm font-medium text-ink">CAPTCHA Trigger (failed attempts)</label>
                <input wire:model="captcha_threshold" type="number" min="1" max="19"
                       class="w-full max-w-[160px] rounded-lg border border-hairline px-3 py-2.5 text-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500">
                <p class="mt-1 text-xs text-muted">Require CAPTCHA after this many failed attempts on an account (must be fewer than the lockout threshold above).</p>
                @error('captcha_threshold') <p class="mt-1 text-sm text-danger-500">{{ $message }}</p> @enderror
            </div>

            <label class="flex items-center justify-between rounded-lg border border-hairline px-4 py-3">
                <span>
                    <span class="block text-sm font-medium text-ink">Rate Limiting</span>
                    <span class="block text-xs text-muted">Caps login attempts from a single source, independent of which account is being targeted — blunts scripted attacks and intentional account-lockout abuse.</span>
                </span>
                <input wire:model="rate_limiting_enabled" type="checkbox" class="h-5 w-5 rounded border-hairline text-primary-600 focus:ring-primary-500">
            </label>

            <p class="text-xs text-muted">
                Note: these settings only cover login brute-force attempts. A large-scale network DDoS is outside what any application-level setting can stop — use your hosting provider's or a CDN/WAF's DDoS protection for that, if available.
            </p>

            <button type="submit"
                    class="rounded-lg bg-primary-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-primary-700"
                    wire:loading.attr="disabled" wire:target="saveSecuritySettings">
                Save Security Settings
            </button>
        </form>
    </div>
</div>

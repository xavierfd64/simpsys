<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * A single-row config table, mirroring TenantSetting's pattern but for
 * platform-wide branding/contact info instead of one tenant's preferences.
 */
#[Fillable([
    'platform_name', 'logo_path', 'favicon_path', 'support_email', 'support_phone',
    'mail_mailer', 'mail_host', 'mail_port', 'mail_encryption', 'mail_username',
    'mail_password', 'mail_from_address', 'mail_from_name',
    'theme_primary_color', 'theme_font',
    'manual_payment_enabled', 'paypal_enabled', 'paypal_environment', 'paypal_client_id',
    'paypal_client_secret', 'paypal_webhook_id', 'paypal_currency',
])]
class PlatformSetting extends Model
{
    /**
     * A controlled list, not arbitrary font URLs/CSS — every option is
     * already available with zero extra network requests or build step:
     * "inter" is the app's existing self-hosted @fontsource default, the
     * rest are OS-installed system fonts.
     */
    public const FONTS = [
        'inter' => [
            'label' => 'Inter (Default)',
            'stack' => "'Inter', ui-sans-serif, system-ui, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol', 'Noto Color Emoji'",
        ],
        'system' => [
            'label' => 'System UI',
            'stack' => "ui-sans-serif, system-ui, -apple-system, 'Segoe UI', sans-serif",
        ],
        'arial' => [
            'label' => 'Arial',
            'stack' => 'Arial, Helvetica, sans-serif',
        ],
        'verdana' => [
            'label' => 'Verdana',
            'stack' => 'Verdana, Geneva, sans-serif',
        ],
        'georgia' => [
            'label' => 'Georgia (Serif)',
            'stack' => "Georgia, 'Times New Roman', serif",
        ],
    ];

    protected function casts(): array
    {
        return [
            'mail_password' => 'encrypted',
            'paypal_client_secret' => 'encrypted',
            'manual_payment_enabled' => 'boolean',
            'paypal_enabled' => 'boolean',
        ];
    }

    public static function current(): self
    {
        // The boolean-cast columns' schema-level defaults only apply to
        // the row MySQL/SQLite actually stores — a freshly created()
        // instance doesn't get re-fetched from the database afterward, so
        // without these explicit values a brand-new row's in-memory
        // manual_payment_enabled/paypal_enabled would read back as null
        // (missing from the model's attributes entirely) rather than the
        // intended true/false, which a strictly bool-typed property
        // assignment elsewhere then rejects outright.
        return static::query()->firstOrCreate([], [
            'manual_payment_enabled' => true,
            'paypal_enabled' => false,
        ]);
    }

    public function displayName(): string
    {
        return $this->platform_name ?: config('app.name');
    }

    public function hasCustomTheme(): bool
    {
        return filled($this->theme_primary_color) || filled($this->theme_font);
    }

    public function fontStack(): ?string
    {
        return self::FONTS[$this->theme_font]['stack'] ?? null;
    }

    public function hasMailConfigured(): bool
    {
        return filled($this->mail_mailer) && filled($this->mail_host) && filled($this->mail_from_address);
    }

    /**
     * PayPal is only genuinely offered once it's both turned on and has
     * the credentials it needs to actually call the API — enabling the
     * toggle alone (e.g. mid-setup, before Test Connection has ever
     * succeeded) must not surface a checkout option that can't work.
     */
    public function isPayPalConfigured(): bool
    {
        return $this->paypal_enabled
            && filled($this->paypal_client_id)
            && filled($this->paypal_client_secret);
    }

    public function paypalApiBaseUrl(): string
    {
        return $this->paypal_environment === 'live'
            ? 'https://api-m.paypal.com'
            : 'https://api-m.sandbox.paypal.com';
    }
}

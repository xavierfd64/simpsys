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
        ];
    }

    public static function current(): self
    {
        return static::query()->firstOrCreate([]);
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
}

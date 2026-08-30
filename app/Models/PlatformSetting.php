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
])]
class PlatformSetting extends Model
{
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

    public function hasMailConfigured(): bool
    {
        return filled($this->mail_mailer) && filled($this->mail_host) && filled($this->mail_from_address);
    }
}

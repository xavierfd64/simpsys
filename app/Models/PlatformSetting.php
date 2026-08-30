<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * A single-row config table, mirroring TenantSetting's pattern but for
 * platform-wide branding/contact info instead of one tenant's preferences.
 */
#[Fillable(['platform_name', 'logo_path', 'favicon_path', 'support_email', 'support_phone'])]
class PlatformSetting extends Model
{
    public static function current(): self
    {
        return static::query()->firstOrCreate([]);
    }

    public function displayName(): string
    {
        return $this->platform_name ?: config('app.name');
    }
}

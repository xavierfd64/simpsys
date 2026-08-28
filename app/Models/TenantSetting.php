<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['tenant_id', 'dine_in_enabled', 'to_go_enabled', 'default_order_type', 'kitchen_enabled'])]
class TenantSetting extends Model
{
    protected function casts(): array
    {
        return [
            'dine_in_enabled' => 'boolean',
            'to_go_enabled' => 'boolean',
            'kitchen_enabled' => 'boolean',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function orderTypesEnabled(): bool
    {
        return $this->dine_in_enabled && $this->to_go_enabled;
    }
}

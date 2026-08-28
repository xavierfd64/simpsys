<?php

namespace App\Models;

use App\Enums\OrderType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['tenant_id', 'order_types_enabled', 'dine_in_enabled', 'to_go_enabled', 'default_order_type', 'kitchen_enabled'])]
class TenantSetting extends Model
{
    protected function casts(): array
    {
        return [
            'order_types_enabled' => 'boolean',
            'dine_in_enabled' => 'boolean',
            'to_go_enabled' => 'boolean',
            'kitchen_enabled' => 'boolean',
            'default_order_type' => OrderType::class,
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function orderTypesEnabled(): bool
    {
        return $this->order_types_enabled;
    }
}

<?php

namespace App\Models;

use App\Concerns\BelongsToTenant;
use App\Concerns\HasUuid;
use App\Enums\KitchenOrderStatus;
use App\Enums\OrderType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'tenant_id', 'sale_id', 'number', 'order_type', 'status',
    'started_at', 'ready_at', 'completed_at', 'cancelled_at',
])]
class KitchenOrder extends Model
{
    use BelongsToTenant, HasUuid;

    protected function casts(): array
    {
        return [
            'order_type' => OrderType::class,
            'status' => KitchenOrderStatus::class,
            'started_at' => 'datetime',
            'ready_at' => 'datetime',
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(KitchenOrderItem::class);
    }

    public function displayNumber(): string
    {
        return '#'.str_pad((string) $this->number, 5, '0', STR_PAD_LEFT);
    }
}

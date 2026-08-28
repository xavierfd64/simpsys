<?php

namespace App\Models;

use App\Concerns\BelongsToTenant;
use App\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'tenant_id', 'supply_category_id', 'name', 'image_path', 'unit',
    'low_stock_threshold', 'is_active',
])]
class Supply extends Model
{
    use BelongsToTenant, HasFactory, HasUuid, SoftDeletes;

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'low_stock_threshold' => 'float',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(SupplyCategory::class, 'supply_category_id');
    }

    public function inventory(): HasOne
    {
        return $this->hasOne(SupplyInventory::class);
    }

    public function movements(): HasMany
    {
        return $this->hasMany(SupplyInventoryMovement::class);
    }

    public function currentStock(): float
    {
        return (float) ($this->inventory?->quantity ?? 0);
    }

    public function isLowStock(): bool
    {
        return $this->currentStock() <= $this->low_stock_threshold;
    }

    protected static function booted(): void
    {
        static::created(function (Supply $supply) {
            $supply->inventory()->create(['quantity' => 0]);
        });
    }
}

<?php

namespace App\Models;

use App\Concerns\BelongsToTenant;
use App\Concerns\HasUuid;
use App\Enums\ProductType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'tenant_id', 'product_category_id', 'name', 'image_path', 'type',
    'selling_price', 'low_stock_threshold', 'is_active',
])]
class Product extends Model
{
    use BelongsToTenant, HasFactory, HasUuid, SoftDeletes;

    protected function casts(): array
    {
        return [
            'type' => ProductType::class,
            'is_active' => 'boolean',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class, 'product_category_id');
    }

    public function inventory(): HasOne
    {
        return $this->hasOne(ProductInventory::class);
    }

    public function movements(): HasMany
    {
        return $this->hasMany(ProductInventoryMovement::class);
    }

    public function currentStock(): int
    {
        return $this->inventory?->quantity ?? 0;
    }

    public function isLowStock(): bool
    {
        return $this->type === ProductType::ReadyToSell && $this->currentStock() <= $this->low_stock_threshold;
    }

    protected static function booted(): void
    {
        static::created(function (Product $product) {
            $product->inventory()->create(['quantity' => 0]);
        });
    }
}

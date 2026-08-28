<?php

namespace App\Models;

use App\Concerns\HasUuid;
use App\Enums\ProductInventoryMovementType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['tenant_id', 'product_id', 'user_id', 'type', 'quantity_change', 'quantity_after', 'note'])]
class ProductInventoryMovement extends Model
{
    use HasUuid;

    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'type' => ProductInventoryMovementType::class,
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

<?php

namespace App\Models;

use App\Concerns\HasUuid;
use App\Enums\SupplyInventoryMovementType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['tenant_id', 'supply_id', 'user_id', 'type', 'quantity_change', 'quantity_after', 'note'])]
class SupplyInventoryMovement extends Model
{
    use HasUuid;

    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'type' => SupplyInventoryMovementType::class,
            'quantity_change' => 'float',
            'quantity_after' => 'float',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function supply(): BelongsTo
    {
        return $this->belongsTo(Supply::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

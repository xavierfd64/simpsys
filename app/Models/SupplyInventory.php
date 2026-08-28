<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['supply_id', 'quantity'])]
class SupplyInventory extends Model
{
    protected $table = 'supply_inventory';

    protected function casts(): array
    {
        return ['quantity' => 'float'];
    }

    public function supply(): BelongsTo
    {
        return $this->belongsTo(Supply::class);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['product_id', 'quantity'])]
class ProductInventory extends Model
{
    protected $table = 'product_inventory';

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}

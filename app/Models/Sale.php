<?php

namespace App\Models;

use App\Concerns\BelongsToTenant;
use App\Concerns\HasUuid;
use App\Enums\OrderType;
use App\Enums\SaleStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'tenant_id', 'number', 'cashier_id', 'order_type', 'payment_method_id', 'payment_method_name',
    'total', 'amount_received', 'change_amount', 'status', 'voided_by', 'void_reason', 'voided_at',
])]
class Sale extends Model
{
    use BelongsToTenant, HasFactory, HasUuid;

    protected function casts(): array
    {
        return [
            'order_type' => OrderType::class,
            'status' => SaleStatus::class,
            'voided_at' => 'datetime',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function cashier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cashier_id');
    }

    public function voidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'voided_by');
    }

    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(SaleItem::class);
    }

    public function kitchenOrder(): HasOne
    {
        return $this->hasOne(KitchenOrder::class);
    }

    public function displayNumber(): string
    {
        return '#'.str_pad((string) $this->number, 5, '0', STR_PAD_LEFT);
    }
}

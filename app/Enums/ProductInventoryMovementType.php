<?php

namespace App\Enums;

enum ProductInventoryMovementType: string
{
    case StockAdded = 'stock_added';
    case BatchProduced = 'batch_produced';
    case Sale = 'sale';
    case Spoilage = 'spoilage';
    case Damage = 'damage';
    case PersonalConsumption = 'personal_consumption';
    case Missing = 'missing';
    case Adjustment = 'adjustment';
    case VoidReversal = 'void_reversal';

    public function label(): string
    {
        return match ($this) {
            self::StockAdded => 'Stock Added',
            self::BatchProduced => 'Batch Produced',
            self::Sale => 'Sale',
            self::Spoilage => 'Spoilage',
            self::Damage => 'Damage',
            self::PersonalConsumption => 'Personal Consumption',
            self::Missing => 'Missing',
            self::Adjustment => 'Adjustment',
            self::VoidReversal => 'Void Reversal',
        };
    }

    /**
     * Manual movement types an owner can pick from an "Adjust Stock" form.
     * Sale and Void Reversal are only ever created by the system.
     */
    public static function manualTypes(): array
    {
        return [
            self::StockAdded,
            self::BatchProduced,
            self::Spoilage,
            self::Damage,
            self::PersonalConsumption,
            self::Missing,
            self::Adjustment,
        ];
    }

    public function isDeduction(): bool
    {
        return in_array($this, [
            self::Sale, self::Spoilage, self::Damage,
            self::PersonalConsumption, self::Missing,
        ], true);
    }
}

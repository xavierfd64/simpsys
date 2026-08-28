<?php

namespace App\Enums;

enum SupplyInventoryMovementType: string
{
    case StockAdded = 'stock_added';
    case ManualUsage = 'manual_usage';
    case Spoilage = 'spoilage';
    case Adjustment = 'adjustment';

    public function label(): string
    {
        return match ($this) {
            self::StockAdded => 'Stock Added',
            self::ManualUsage => 'Manual Usage',
            self::Spoilage => 'Spoilage',
            self::Adjustment => 'Adjustment',
        };
    }

    public function isDeduction(): bool
    {
        return in_array($this, [self::ManualUsage, self::Spoilage], true);
    }
}

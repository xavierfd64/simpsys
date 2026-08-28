<?php

namespace App\Enums;

enum ProductType: string
{
    case ReadyToSell = 'ready_to_sell';
    case MadeToOrder = 'made_to_order';

    public function label(): string
    {
        return match ($this) {
            self::ReadyToSell => 'Ready to Sell',
            self::MadeToOrder => 'Made to Order',
        };
    }
}

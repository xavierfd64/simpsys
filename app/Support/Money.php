<?php

namespace App\Support;

/**
 * Money amounts on products, sales, and expenses are stored as integer
 * centavos to avoid float rounding. Subscription plan prices are a
 * separate, deliberately simpler case: whole-peso integers set by the
 * platform admin, no centavo precision needed.
 */
class Money
{
    public static function format(int $cents): string
    {
        return '₱'.number_format($cents / 100, 2);
    }

    public static function toCents(string|float $pesos): int
    {
        return (int) round(((float) $pesos) * 100);
    }
}

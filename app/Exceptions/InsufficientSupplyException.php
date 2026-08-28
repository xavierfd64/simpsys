<?php

namespace App\Exceptions;

use RuntimeException;

class InsufficientSupplyException extends RuntimeException
{
    public static function forSupply(string $supplyName, float $available, float $requested): self
    {
        return new self("Not enough \"{$supplyName}\": {$available} available, {$requested} requested.");
    }
}

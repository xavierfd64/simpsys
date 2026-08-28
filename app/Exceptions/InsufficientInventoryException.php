<?php

namespace App\Exceptions;

use RuntimeException;

class InsufficientInventoryException extends RuntimeException
{
    public static function forProduct(string $productName, int $available, int $requested): self
    {
        return new self("Not enough stock for \"{$productName}\": {$available} available, {$requested} requested.");
    }
}

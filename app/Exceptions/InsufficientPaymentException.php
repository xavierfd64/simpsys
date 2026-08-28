<?php

namespace App\Exceptions;

use RuntimeException;

class InsufficientPaymentException extends RuntimeException
{
    public static function for(int $total, int $amountReceived): self
    {
        return new self("Amount received is less than the total due ({$amountReceived} < {$total}).");
    }
}

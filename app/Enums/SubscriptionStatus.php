<?php

namespace App\Enums;

enum SubscriptionStatus: string
{
    case Trial = 'trial';
    case Active = 'active';
    case Expired = 'expired';
    case Cancelled = 'cancelled';
    case Suspended = 'suspended';

    public function label(): string
    {
        return match ($this) {
            self::Trial => 'Trial',
            self::Active => 'Active',
            self::Expired => 'Expired',
            self::Cancelled => 'Cancelled',
            self::Suspended => 'Suspended',
        };
    }

    public function badgeColor(): string
    {
        return match ($this) {
            self::Trial => 'blue',
            self::Active => 'green',
            self::Expired => 'amber',
            self::Cancelled, self::Suspended => 'red',
        };
    }
}

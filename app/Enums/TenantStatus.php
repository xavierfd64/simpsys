<?php

namespace App\Enums;

enum TenantStatus: string
{
    case Trial = 'trial';
    case Active = 'active';
    case Expired = 'expired';
    case Suspended = 'suspended';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Trial => 'Trial',
            self::Active => 'Active',
            self::Expired => 'Expired',
            self::Suspended => 'Suspended',
            self::Cancelled => 'Cancelled',
        };
    }

    public function badgeColor(): string
    {
        return match ($this) {
            self::Trial => 'blue',
            self::Active => 'green',
            self::Expired => 'amber',
            self::Suspended, self::Cancelled => 'red',
        };
    }
}

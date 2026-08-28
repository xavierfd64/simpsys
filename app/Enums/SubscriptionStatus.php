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

    /**
     * Full literal Tailwind class strings — see TenantStatus::badgeClasses()
     * for why these can't be assembled via string interpolation.
     */
    public function badgeClasses(): string
    {
        return match ($this) {
            self::Trial => 'bg-blue-50 text-blue-700',
            self::Active => 'bg-green-50 text-green-700',
            self::Expired => 'bg-amber-50 text-amber-700',
            self::Cancelled, self::Suspended => 'bg-red-50 text-red-700',
        };
    }
}

<?php

namespace App\Enums;

enum TenantMembershipRole: string
{
    case Owner = 'owner';
    case Cashier = 'cashier';
    case KitchenStaff = 'kitchen_staff';

    public function label(): string
    {
        return match ($this) {
            self::Owner => 'Owner',
            self::Cashier => 'Cashier',
            self::KitchenStaff => 'Kitchen Staff',
        };
    }
}

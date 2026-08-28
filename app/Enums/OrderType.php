<?php

namespace App\Enums;

enum OrderType: string
{
    case DineIn = 'dine_in';
    case ToGo = 'to_go';

    public function label(): string
    {
        return match ($this) {
            self::DineIn => 'Dine-In',
            self::ToGo => 'To-Go',
        };
    }
}

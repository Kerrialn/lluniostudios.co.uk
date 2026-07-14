<?php

declare(strict_types=1);

namespace App\Enum;

enum ShippingMethod: string
{
    case PICKUP = 'pickup';
    case DPD = 'dpd';
    case PALLET = 'pallet';

    public function label(): string
    {
        return match ($this) {
            self::PICKUP => 'Pickup',
            self::DPD => 'DPD courier',
            self::PALLET => 'Pallet delivery',
        };
    }
}

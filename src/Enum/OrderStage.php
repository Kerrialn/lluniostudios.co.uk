<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Where a placed order is in the studio (Step 11), marked by hand in admin
 * (decided 9 October 2026), each with its email: In Production (email 04),
 * Dispatched (email 05).
 * Kept apart from the payment status, so an order in production can still
 * have a deposit's balance to pay.
 */
enum OrderStage: string
{
    case IN_PRODUCTION = 'in_production';
    case DISPATCHED = 'dispatched';

    public function label(): string
    {
        return match ($this) {
            self::IN_PRODUCTION => 'In Production',
            self::DISPATCHED => 'Dispatched',
        };
    }
}

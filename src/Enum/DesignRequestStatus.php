<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Where a "Reserve Your Design" request stands. New when received; Quoted
 * once a quote has been sent; Paid once the customer has paid (both set by
 * the quotes step, Step 10b, or by hand in admin until then).
 */
enum DesignRequestStatus: string
{
    case NEW = 'new';
    case QUOTED = 'quoted';
    case PAID = 'paid';

    public function label(): string
    {
        return match ($this) {
            self::NEW => 'New',
            self::QUOTED => 'Quoted',
            self::PAID => 'Paid',
        };
    }
}

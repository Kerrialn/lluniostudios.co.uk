<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Where a Bespoke Commissions enquiry stands, set by hand in admin: New when
 * received, Contacted once the studio has been in touch, Closed when done.
 */
enum CommissionStatus: string
{
    case NEW = 'new';
    case CONTACTED = 'contacted';
    case CLOSED = 'closed';

    public function label(): string
    {
        return match ($this) {
            self::NEW => 'New',
            self::CONTACTED => 'Contacted',
            self::CLOSED => 'Closed',
        };
    }
}

<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Which Bespoke Commissions form an enquiry came from.
 */
enum CommissionClient: string
{
    case HOME = 'home';
    case PROJECT = 'project';

    /**
     * The tab's name on the page, e.g. for email 03's "Commission type".
     */
    public function label(): string
    {
        return match ($this) {
            self::HOME => 'For Your Home',
            self::PROJECT => 'For Your Project',
        };
    }
}

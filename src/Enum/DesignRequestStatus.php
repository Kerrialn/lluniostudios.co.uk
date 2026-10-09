<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Where a "Reserve Your Design" request stands. New when received; Quoted
 * once a quote has been sent; Deposit Paid once a quote's deposit is paid;
 * Paid once paid in full (the whole quote, or the balance after a deposit).
 * Set by the quotes step (Step 10b), or by hand in admin.
 */
enum DesignRequestStatus: string
{
    case NEW = 'new';
    case QUOTED = 'quoted';
    case DEPOSIT_PAID = 'deposit_paid';
    case PAID = 'paid';

    public function label(): string
    {
        return match ($this) {
            self::NEW => 'New',
            self::QUOTED => 'Quoted',
            self::DEPOSIT_PAID => 'Deposit Paid',
            self::PAID => 'Paid',
        };
    }
}

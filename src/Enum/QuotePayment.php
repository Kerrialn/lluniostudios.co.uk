<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * How a quote is paid (decided 8 October 2026): in full, or a deposit now
 * and the balance later ("Request balance", Step 10b.4).
 */
enum QuotePayment: string
{
    case FULL = 'full';
    case DEPOSIT = 'deposit';

    public function label(): string
    {
        return match ($this) {
            self::FULL => 'Full payment',
            self::DEPOSIT => 'Deposit',
        };
    }
}

<?php

declare(strict_types=1);

namespace App\Enum;

enum OrderStatus: string
{
    case PENDING = 'pending';
    case AWAITING_PAYMENT = 'awaiting_payment';
    // A quote's deposit paid, the balance still owed (Step 10b.4).
    case DEPOSIT_PAID = 'deposit_paid';
    case PAID = 'paid';
    case FAILED = 'failed';
    case CANCELLED = 'cancelled';
    case FULFILLED = 'fulfilled';

    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'Pending',
            self::AWAITING_PAYMENT => 'Awaiting Payment',
            self::DEPOSIT_PAID => 'Deposit Paid',
            self::PAID => 'Paid',
            self::FAILED => 'Failed',
            self::CANCELLED => 'Cancelled',
            self::FULFILLED => 'Fulfilled',
        };
    }
}

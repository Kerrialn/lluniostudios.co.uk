<?php

declare(strict_types=1);

namespace App\Payment;

final readonly class StripePaymentIntentResult
{
    public function __construct(
        public string $id,
        public string $clientSecret,
        public string $status,
    ) {
    }
}

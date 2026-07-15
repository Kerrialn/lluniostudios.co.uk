<?php

declare(strict_types=1);

namespace App\Shipping;

use App\Enum\ShippingMethod;

final readonly class ShippingQuote
{
    public function __construct(
        public ShippingMethod $method,
        public string $carrier,
        public string $serviceName,
        public int $priceInPence,
        public ?int $estimatedDays = null,
    ) {
    }

    public function priceInGbp(): float
    {
        return $this->priceInPence / 100;
    }

    /**
     * Stable identifier used as the radio value / form choice at checkout.
     */
    public function id(): string
    {
        return $this->method->value . ':' . $this->serviceName;
    }
}

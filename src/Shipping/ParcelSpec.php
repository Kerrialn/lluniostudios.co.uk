<?php

declare(strict_types=1);

namespace App\Shipping;

/**
 * Physical description of the shipment used to obtain shipping quotes.
 * Weight in grams, dimensions in millimetres, value in pence.
 */
final readonly class ParcelSpec
{
    public function __construct(
        public int $weightGrams,
        public int $lengthMm,
        public int $widthMm,
        public int $heightMm,
        public int $valueInPence,
    ) {
    }

    public function weightKg(): float
    {
        return $this->weightGrams / 1000;
    }

    public function longestSideCm(): float
    {
        return max($this->lengthMm, $this->widthMm, $this->heightMm) / 10;
    }

    /**
     * Length + girth in cm, where girth = 2 x (width + height).
     * DPD's oversize rule is based on length + girth.
     */
    public function lengthPlusGirthCm(): float
    {
        $sides = [$this->lengthMm, $this->widthMm, $this->heightMm];
        rsort($sides);
        [$longest, $a, $b] = $sides;

        return ($longest + 2 * ($a + $b)) / 10;
    }
}

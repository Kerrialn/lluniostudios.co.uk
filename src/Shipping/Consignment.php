<?php

declare(strict_types=1);

namespace App\Shipping;

/**
 * Everything sent to one address in one delivery: one parcel (box) per lamp.
 * Carriers quote and book the consignment as a whole (e.g. a DPD multi-parcel
 * consignment), but each box must fit the carrier's per-parcel limits.
 */
final readonly class Consignment
{
    /**
     * @param list<ParcelSpec> $parcels
     */
    public function __construct(
        public array $parcels,
    ) {
    }

    public function parcelCount(): int
    {
        return count($this->parcels);
    }

    public function isEmpty(): bool
    {
        return $this->parcels === [];
    }

    public function totalWeightKg(): float
    {
        return array_sum(array_map(static fn (ParcelSpec $parcel): float => $parcel->weightKg(), $this->parcels));
    }

    public function totalValueInPence(): int
    {
        return array_sum(array_map(static fn (ParcelSpec $parcel): int => $parcel->valueInPence, $this->parcels));
    }
}

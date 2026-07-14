<?php

declare(strict_types=1);

namespace App\Shipping\Provider;

use App\Entity\Address;
use App\Enum\ShippingMethod;
use App\Shipping\ParcelSpec;
use App\Shipping\ShippingProviderInterface;
use App\Shipping\ShippingQuote;

/**
 * Deterministic stand-in for the DPD UK API used until live credentials exist.
 * Weight-banded pricing for parcels within DPD's size/weight limits.
 */
final class MockDpdProvider implements ShippingProviderInterface
{
    use DpdEligibility;

    public function supports(ParcelSpec $parcel): bool
    {
        return $this->isDpdEligible($parcel);
    }

    public function quote(ParcelSpec $parcel, Address $destination): array
    {
        $kg = $parcel->weightKg();

        $price = match (true) {
            $kg <= 2.0 => 595,
            $kg <= 10.0 => 895,
            $kg <= 20.0 => 1295,
            default => 1795,
        };

        return [
            new ShippingQuote(
                method: ShippingMethod::DPD,
                carrier: 'DPD',
                serviceName: 'DPD Next Day',
                priceInPence: $price,
                estimatedDays: 1,
            ),
        ];
    }
}

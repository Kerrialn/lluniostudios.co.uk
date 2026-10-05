<?php

declare(strict_types=1);

namespace App\Shipping\Provider;

use App\Entity\Address;
use App\Enum\ShippingMethod;
use App\Shipping\Consignment;
use App\Shipping\ParcelSpec;
use App\Shipping\ShippingProviderInterface;
use App\Shipping\ShippingQuote;

/**
 * Deterministic stand-in for the DPD UK API used until live credentials exist.
 * Prices a multi-parcel consignment as the sum of a weight-banded price per
 * box. Only offered when every box is within DPD's size/weight limits.
 */
final class MockDpdProvider implements ShippingProviderInterface
{
    use DpdEligibility;

    public function supports(Consignment $consignment): bool
    {
        return $this->isDpdEligibleConsignment($consignment);
    }

    public function quote(Consignment $consignment, Address $destination): array
    {
        $price = 0;
        foreach ($consignment->parcels as $parcel) {
            $price += $this->priceForParcel($parcel);
        }

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

    private function priceForParcel(ParcelSpec $parcel): int
    {
        $kg = $parcel->weightKg();

        return match (true) {
            $kg <= 2.0 => 595,
            $kg <= 10.0 => 895,
            $kg <= 20.0 => 1295,
            default => 1795,
        };
    }
}

<?php

declare(strict_types=1);

namespace App\Shipping\Provider;

use App\Entity\Address;
use App\Enum\ShippingMethod;
use App\Shipping\ParcelSpec;
use App\Shipping\ShippingProviderInterface;
use App\Shipping\ShippingQuote;

/**
 * Deterministic stand-in for the Palletways API used until live credentials exist.
 * Handles anything too heavy/large for DPD (> 31 kg or oversize).
 */
final class MockPalletwaysProvider implements ShippingProviderInterface
{
    use DpdEligibility;

    public function supports(ParcelSpec $parcel): bool
    {
        // Pallet handles whatever DPD cannot.
        return ! $this->isDpdEligible($parcel);
    }

    public function quote(ParcelSpec $parcel, Address $destination): array
    {
        $kg = $parcel->weightKg();

        [$service, $price] = match (true) {
            $kg <= 250.0 => ['Quarter pallet', 4500],
            $kg <= 500.0 => ['Half pallet', 6500],
            default => ['Full pallet', 9500],
        };

        return [
            new ShippingQuote(
                method: ShippingMethod::PALLET,
                carrier: 'Palletways',
                serviceName: $service,
                priceInPence: $price,
                estimatedDays: 3,
            ),
        ];
    }
}

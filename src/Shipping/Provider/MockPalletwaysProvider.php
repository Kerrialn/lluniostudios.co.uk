<?php

declare(strict_types=1);

namespace App\Shipping\Provider;

use App\Entity\Address;
use App\Enum\ShippingMethod;
use App\Shipping\Consignment;
use App\Shipping\ShippingProviderInterface;
use App\Shipping\ShippingQuote;

/**
 * Deterministic stand-in for the Palletways API used until live credentials exist.
 * Handles anything too heavy/large for DPD (> 31 kg or oversize).
 */
final class MockPalletwaysProvider implements ShippingProviderInterface
{
    use DpdEligibility;

    public function supports(Consignment $consignment): bool
    {
        // Pallet handles whatever DPD cannot.
        return ! $consignment->isEmpty() && ! $this->isDpdEligibleConsignment($consignment);
    }

    public function quote(Consignment $consignment, Address $destination): array
    {
        $kg = $consignment->totalWeightKg();

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

<?php

declare(strict_types=1);

namespace App\Shipping\Provider;

use App\Entity\Address;
use App\Enum\ShippingMethod;
use App\Shipping\Consignment;
use App\Shipping\ShippingProviderInterface;
use App\Shipping\ShippingQuote;

/**
 * Free collection from the studio. Always available regardless of size.
 */
final class PickupProvider implements ShippingProviderInterface
{
    public function supports(Consignment $consignment): bool
    {
        return true;
    }

    public function quote(Consignment $consignment, Address $destination): array
    {
        return [
            new ShippingQuote(
                method: ShippingMethod::PICKUP,
                carrier: 'Pickup',
                serviceName: 'Collect from studio',
                priceInPence: 0,
                estimatedDays: 0,
            ),
        ];
    }
}

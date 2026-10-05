<?php

declare(strict_types=1);

namespace App\Shipping;

use App\Entity\Address;

interface ShippingProviderInterface
{
    /**
     * Whether this provider can carry the consignment (every box within its
     * size/weight limits).
     */
    public function supports(Consignment $consignment): bool;

    /**
     * Live/estimated quotes for the whole consignment to the destination.
     *
     * @return list<ShippingQuote>
     */
    public function quote(Consignment $consignment, Address $destination): array;
}

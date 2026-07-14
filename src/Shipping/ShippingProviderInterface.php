<?php

declare(strict_types=1);

namespace App\Shipping;

use App\Entity\Address;

interface ShippingProviderInterface
{
    /**
     * Whether this provider can carry the given parcel (weight/size tier).
     */
    public function supports(ParcelSpec $parcel): bool;

    /**
     * Live/estimated quotes for the parcel to the destination.
     *
     * @return list<ShippingQuote>
     */
    public function quote(ParcelSpec $parcel, Address $destination): array;
}

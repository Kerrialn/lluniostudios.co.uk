<?php

declare(strict_types=1);

namespace App\Shipping\Provider;

use App\Shipping\ParcelSpec;

/**
 * DPD UK acceptance limits: up to 31 kg, longest side up to 175 cm,
 * length + girth up to 300 cm. Anything larger/heavier must go by pallet.
 */
trait DpdEligibility
{
    public const MAX_WEIGHT_KG = 31.0;

    public const MAX_LONGEST_SIDE_CM = 175.0;

    public const MAX_LENGTH_PLUS_GIRTH_CM = 300.0;

    public function isDpdEligible(ParcelSpec $parcel): bool
    {
        return $parcel->weightKg() <= self::MAX_WEIGHT_KG
            && $parcel->longestSideCm() <= self::MAX_LONGEST_SIDE_CM
            && $parcel->lengthPlusGirthCm() <= self::MAX_LENGTH_PLUS_GIRTH_CM;
    }
}

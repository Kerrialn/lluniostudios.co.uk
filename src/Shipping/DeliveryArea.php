<?php

declare(strict_types=1);

namespace App\Shipping;

/**
 * Where checkout delivers (decided 7 October 2026): the UK mainland only.
 * Postcodes in the Scottish Highlands and Islands, Northern Ireland, the
 * Isle of Man, the Isles of Scilly and the Channel Islands go through the
 * request route instead ("Reserve Your Design"), with delivery quoted
 * individually. The Highlands and Islands districts follow the usual DPD
 * "Highlands & Islands" list; to confirm with DPD when the account exists.
 */
final class DeliveryArea
{
    /**
     * Shown on the Card Payment page above "Reserve Your Design" for a
     * postcode outside the UK mainland.
     */
    public const OUTSIDE_NOTE = 'This address is outside the UK mainland, delivery will be quoted for individually. Reserve now to secure your casting slot.';

    /**
     * Whole postcode areas outside the mainland: Northern Ireland, Isle of
     * Man, Guernsey, Jersey, Outer Hebrides, Shetland, Orkney and Caithness
     * (KW), Inverness and the Highlands (IV).
     */
    private const OUTSIDE_AREAS = ['BT', 'IM', 'GY', 'JE', 'HS', 'ZE', 'KW', 'IV'];

    /**
     * Districts outside the mainland within otherwise mainland areas, as
     * [area, from, to] district numbers.
     */
    private const OUTSIDE_DISTRICTS = [
        ['AB', 31, 38],
        ['AB', 44, 45],
        ['AB', 52, 56],
        ['FK', 17, 21],
        ['G', 83, 83],
        ['KA', 27, 28],
        ['PA', 20, 49],
        ['PA', 60, 78],
        ['PH', 4, 26],
        ['PH', 30, 44],
        ['PH', 49, 50],
        // Isles of Scilly.
        ['TR', 21, 25],
    ];

    /**
     * A full UK postcode, e.g. "BH21 3EG" (any spacing or case).
     */
    private const POSTCODE = '/^([A-Z]{1,2})(\d[A-Z\d]?)\s*(\d[A-Z]{2})$/';

    /**
     * "bh213eg" -> "BH21 3EG"; null if it isn't a full UK postcode.
     */
    public static function normalise(string $postcode): ?string
    {
        $postcode = strtoupper(trim(preg_replace('/\s+/', ' ', $postcode) ?? ''));
        if (! preg_match(self::POSTCODE, $postcode, $m)) {
            return null;
        }

        return $m[1] . $m[2] . ' ' . $m[3];
    }

    /**
     * Whether checkout delivers to this postcode (false for anything that
     * isn't a full UK postcode).
     */
    public static function isMainland(string $postcode): bool
    {
        $normalised = self::normalise($postcode);
        if ($normalised === null) {
            return false;
        }

        preg_match('/^([A-Z]{1,2})(\d+)/', $normalised, $m);
        [$area, $district] = [$m[1], (int) $m[2]];
        if (\in_array($area, self::OUTSIDE_AREAS, true)) {
            return false;
        }

        foreach (self::OUTSIDE_DISTRICTS as [$outsideArea, $from, $to]) {
            if ($area === $outsideArea && $district >= $from && $district <= $to) {
                return false;
            }
        }

        return true;
    }
}

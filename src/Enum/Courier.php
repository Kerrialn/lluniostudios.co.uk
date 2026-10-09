<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Who delivers a dispatched order (Step 11.3, decided 9 October 2026): DPD
 * for parcels, Palletways for pallets. DPD's tracking link is built from the
 * tracking number (the address to confirm with DPD at launch, Step 12);
 * Palletways has none here, so its customers get the number only.
 */
enum Courier: string
{
    case DPD = 'dpd';
    case PALLETWAYS = 'palletways';

    public function label(): string
    {
        return match ($this) {
            self::DPD => 'DPD',
            self::PALLETWAYS => 'Palletways',
        };
    }

    public function trackingUrl(string $trackingNumber): ?string
    {
        return match ($this) {
            self::DPD => 'https://track.dpd.co.uk/search?reference=' . rawurlencode(str_replace(' ', '', $trackingNumber)),
            self::PALLETWAYS => null,
        };
    }
}

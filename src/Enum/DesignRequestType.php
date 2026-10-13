<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Where a request came from: "Reserve Your Design" (Studio and Gallery
 * requests: Custom options, 3 or more lamps, outside the UK mainland) or
 * "Begin the Conversation" (Step 7c: an enquiry for a larger piece, Atelier,
 * Estate or Monument). Both are quoted and paid the same way (Step 10b).
 */
enum DesignRequestType: string
{
    case RESERVATION = 'reservation';
    case ENQUIRY = 'enquiry';

    public function label(): string
    {
        return match ($this) {
            self::RESERVATION => 'Reservation',
            self::ENQUIRY => 'Enquiry',
        };
    }
}

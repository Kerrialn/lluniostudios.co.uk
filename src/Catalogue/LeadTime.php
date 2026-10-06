<?php

declare(strict_types=1);

namespace App\Catalogue;

/**
 * Dispatch lead time for direct orders (Studio and Gallery): the single
 * source for the basket line, size notes and, later, the gift arrive-by date.
 * Available in Twig as `lead_time` (e.g. {{ lead_time.label }}).
 */
final class LeadTime
{
    public const MIN_DAYS = 7;

    public const MAX_DAYS = 14;

    public function label(): string
    {
        return self::MIN_DAYS . '–' . self::MAX_DAYS . ' days';
    }
}

<?php

declare(strict_types=1);

namespace App\Catalogue;

/**
 * Dispatch lead time for direct orders (Studio and Gallery): 1–3 weeks
 * (decided 16 October 2026, was 7–14 days). The single source for the
 * basket and order notes, the configurator's dispatch line, the gift date
 * that switches an order to Reserve Your Design (GiftDate) and the expected
 * dispatch date suggested in admin (OrderDates). Available in Twig as
 * `lead_time` (e.g. {{ lead_time.label }}).
 */
final class LeadTime
{
    public const MIN_WEEKS = 1;

    public const MAX_WEEKS = 3;

    public const MAX_DAYS = self::MAX_WEEKS * 7;

    public function label(): string
    {
        return self::MIN_WEEKS . '–' . self::MAX_WEEKS . ' weeks';
    }
}

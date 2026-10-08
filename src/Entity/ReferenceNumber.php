<?php

declare(strict_types=1);

namespace App\Entity;

use Carbon\CarbonImmutable;

/**
 * Reference numbers (decided 8 October 2026): a prefix, the date as DDMMYY
 * (UK time) and a random 4-digit number, e.g. LR-081026-4821. LR requests,
 * LC commission enquiries, LS orders. A number already taken is replaced
 * before saving (ReferenceNumberChecker). Earlier numbers (e.g.
 * LR-20261008-A0A403) are kept as they are.
 */
final class ReferenceNumber
{
    public const PATTERN = '/^L[A-Z]-\d{6}-\d{4}$/';

    public static function make(string $prefix, ?\DateTimeInterface $now = null): string
    {
        $date = CarbonImmutable::instance($now ?? new CarbonImmutable())->setTimezone('Europe/London');

        // 1000-9999: always four digits.
        return $prefix . '-' . $date->format('dmy') . '-' . random_int(1000, 9999);
    }
}

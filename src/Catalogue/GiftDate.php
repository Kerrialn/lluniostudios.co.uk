<?php

declare(strict_types=1);

namespace App\Catalogue;

use Carbon\CarbonImmutable;

/**
 * The earliest "arrive by" date a gift order can ask for (decided 7 October
 * 2026): today (UK time) plus the longest lead time (14 calendar days, see
 * LeadTime), then 2 working days for DPD. Working days are Monday to Friday
 * except England and Wales bank holidays (lamps are dispatched from Dorset).
 * One-off bank holidays (e.g. for a coronation) are not included.
 */
final class GiftDate
{
    public const DPD_WORKING_DAYS = 2;

    public const TIMEZONE = 'Europe/London';

    public function earliest(?\DateTimeInterface $now = null): CarbonImmutable
    {
        $date = CarbonImmutable::instance($now ?? new \DateTimeImmutable())
            ->setTimezone(self::TIMEZONE)
            ->startOfDay()
            ->addDays(LeadTime::MAX_DAYS);

        for ($added = 0; $added < self::DPD_WORKING_DAYS;) {
            $date = $date->addDay();
            if (self::isWorkingDay($date)) {
                ++$added;
            }
        }

        return $date;
    }

    public static function isWorkingDay(\DateTimeInterface $date): bool
    {
        return (int) $date->format('N') <= 5 && ! \in_array($date->format('Y-m-d'), self::bankHolidays((int) $date->format('Y')), true);
    }

    /**
     * England and Wales bank holidays for a year, with the usual substitute
     * days when one falls at a weekend.
     *
     * @return list<string> dates as Y-m-d
     */
    public static function bankHolidays(int $year): array
    {
        $date = static fn (string $text): CarbonImmutable => CarbonImmutable::parse($text, self::TIMEZONE);
        $easter = self::easterSunday($year);

        $newYear = $date("{$year}-01-01");
        if ($newYear->isWeekend()) {
            $newYear = $newYear->next(CarbonImmutable::MONDAY);
        }

        // Christmas Day and Boxing Day: a weekend day moves to the next free weekday.
        $christmas = $date("{$year}-12-25");
        $boxing = $date("{$year}-12-26");
        $christmasObserved = match ((int) $christmas->format('N')) {
            6 => $date("{$year}-12-27"),
            7 => $date("{$year}-12-27"),
            default => $christmas,
        };
        $boxingObserved = match ((int) $boxing->format('N')) {
            6 => $date("{$year}-12-28"),
            7 => $date("{$year}-12-28"),
            default => $boxing,
        };
        if ((int) $christmas->format('N') === 7) {
            // Christmas on a Sunday: Boxing Day stays on Monday 26th and Christmas moves to Tuesday 27th.
            $boxingObserved = $boxing;
        }

        return array_map(static fn (CarbonImmutable $day): string => $day->format('Y-m-d'), [
            $newYear,
            $easter->subDays(2),
            $easter->addDay(),
            $date("first monday of may {$year}"),
            $date("last monday of may {$year}"),
            $date("last monday of august {$year}"),
            $christmasObserved,
            $boxingObserved,
        ]);
    }

    /**
     * Easter Sunday (Gregorian calendar; the "anonymous" algorithm, so no
     * calendar extension is needed).
     */
    private static function easterSunday(int $year): CarbonImmutable
    {
        $a = $year % 19;
        $b = intdiv($year, 100);
        $c = $year % 100;
        $d = intdiv($b, 4);
        $e = $b % 4;
        $f = intdiv($b + 8, 25);
        $g = intdiv($b - $f + 1, 3);
        $h = (19 * $a + $b - $d - $g + 15) % 30;
        $i = intdiv($c, 4);
        $k = $c % 4;
        $l = (32 + 2 * $e + 2 * $i - $h - $k) % 7;
        $m = intdiv($a + 11 * $h + 22 * $l, 451);
        $month = intdiv($h + $l - 7 * $m + 114, 31);
        $day = (($h + $l - 7 * $m + 114) % 31) + 1;

        return CarbonImmutable::create($year, $month, $day, 0, 0, 0, self::TIMEZONE);
    }
}

<?php

declare(strict_types=1);

namespace App\Tests\Catalogue;

use App\Catalogue\GiftDate;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The earliest gift "arrive by" date: today + 14 calendar days, then 2
 * working days (no weekends or England and Wales bank holidays).
 */
final class GiftDateTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string}>
     */
    public static function days(): iterable
    {
        // Wed 7 Oct 2026 + 14 = Wed 21 Oct; then Thu 22, Fri 23.
        yield 'midweek' => ['2026-10-07 10:00', '2026-10-23'];
        // Thu 8 Oct + 14 = Thu 22 Oct; then Fri 23, (weekend) Mon 26.
        yield 'over a weekend' => ['2026-10-08 10:00', '2026-10-26'];
        // Sat 10 Oct + 14 = Sat 24 Oct; then Mon 26, Tue 27.
        yield 'starting on a Saturday' => ['2026-10-10 10:00', '2026-10-27'];
        // Thu 10 Dec + 14 = Thu 24 Dec; then (Christmas Fri 25, weekend, Boxing Day moved to Mon 28) Tue 29, Wed 30.
        yield 'over Christmas 2026' => ['2026-12-10 09:00', '2026-12-30'];
        // Wed 16 Dec + 14 = Wed 30 Dec; then Thu 31, (New Year Fri 1 Jan, weekend) Mon 4 Jan.
        yield 'over New Year' => ['2026-12-16 09:00', '2027-01-04'];
        // Wed 10 Mar 2027 + 14 = Wed 24 Mar; then Thu 25, (Good Friday 26, weekend, Easter Monday 29) Tue 30.
        yield 'over Easter 2027' => ['2027-03-10 09:00', '2027-03-30'];
        // UK time: 23:30 on 7 Oct in London is still 7 Oct (22:30 UTC).
        yield 'late evening, UK time' => ['2026-10-07 22:30 UTC', '2026-10-23'];
        // 00:30 on 8 Oct in London (23:30 UTC on the 7th) counts as the 8th.
        yield 'just after midnight, UK time' => ['2026-10-07 23:30 UTC', '2026-10-26'];
    }

    #[DataProvider('days')]
    public function testEarliest(string $now, string $expected): void
    {
        $zone = str_ends_with($now, 'UTC') ? new \DateTimeZone('UTC') : new \DateTimeZone('Europe/London');
        $earliest = (new GiftDate())->earliest(new \DateTimeImmutable(str_replace(' UTC', '', $now), $zone));

        self::assertSame($expected, $earliest->format('Y-m-d'));
    }

    /**
     * @return iterable<string, array{int, list<string>}>
     */
    public static function years(): iterable
    {
        yield '2026 (Boxing Day on a Saturday)' => [2026, ['2026-01-01', '2026-04-03', '2026-04-06', '2026-05-04', '2026-05-25', '2026-08-31', '2026-12-25', '2026-12-28']];
        yield '2027 (Christmas on a Saturday)' => [2027, ['2027-01-01', '2027-03-26', '2027-03-29', '2027-05-03', '2027-05-31', '2027-08-30', '2027-12-27', '2027-12-28']];
        yield '2022 (Christmas on a Sunday)' => [2022, ['2022-01-03', '2022-04-15', '2022-04-18', '2022-05-02', '2022-05-30', '2022-08-29', '2022-12-27', '2022-12-26']];
    }

    /**
     * @param list<string> $expected
     */
    #[DataProvider('years')]
    public function testBankHolidays(int $year, array $expected): void
    {
        self::assertSame($expected, GiftDate::bankHolidays($year));
    }
}

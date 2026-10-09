<?php

declare(strict_types=1);

namespace App\Service;

use App\Catalogue\LeadTime;
use App\Entity\Order;
use App\Entity\Quote;
use Doctrine\ORM\EntityManagerInterface;

/**
 * An order's dates for its emails and stages: a quote's estimated casting
 * date (as sent to the customer), and the expected dispatch date suggested
 * when marking In Production (Step 11.2, decided 9 October 2026): the
 * casting date for a quote's order, otherwise the end of the 7–14 day lead
 * time from payment; never before today. UK dates.
 */
final readonly class OrderDates
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * A quote's order: the estimated casting date the customer was sent.
     */
    public function castingDate(Order $order): ?\DateTimeImmutable
    {
        $request = $order->getDesignRequest();
        if ($request === null) {
            return null;
        }

        $quote = $this->entityManager->getRepository(Quote::class)->findOneBy([
            'designRequest' => $request,
        ]);
        $sent = $quote?->getSent()['castingDate'] ?? null;
        if (\is_string($sent)) {
            return new \DateTimeImmutable($sent);
        }

        return $quote?->getCastingDate();
    }

    public function suggestedDispatch(Order $order, ?\DateTimeImmutable $today = null): \DateTimeImmutable
    {
        return self::suggest($this->castingDate($order), $order->getPaidAt() ?? $order->getCreatedAt(), $today);
    }

    /**
     * The casting date if there is one, otherwise the paid date (UK) plus the
     * lead time's longest; never before today (UK). A calendar date (midnight
     * in the server's time zone, as dates are stored), so it shows as itself.
     */
    public static function suggest(?\DateTimeImmutable $castingDate, \DateTimeInterface $paidAt, ?\DateTimeImmutable $today = null): \DateTimeImmutable
    {
        $uk = new \DateTimeZone('Europe/London');
        $todayDay = ($today ?? new \DateTimeImmutable('now'))->setTimezone($uk)->format('Y-m-d');
        $day = $castingDate !== null
            ? $castingDate->format('Y-m-d')
            : \DateTimeImmutable::createFromInterface($paidAt)->setTimezone($uk)->modify('+' . LeadTime::MAX_DAYS . ' days')->format('Y-m-d');

        return self::day(max($day, $todayDay));
    }

    /**
     * Today's date in the UK, as a calendar date.
     */
    public static function today(): \DateTimeImmutable
    {
        return self::day((new \DateTimeImmutable('now', new \DateTimeZone('Europe/London')))->format('Y-m-d'));
    }

    /**
     * A calendar date from "Y-m-d" (null if it isn't one).
     */
    public static function day(string $ymd): ?\DateTimeImmutable
    {
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $ymd);

        return $date === false || $date->format('Y-m-d') !== $ymd ? null : $date;
    }
}

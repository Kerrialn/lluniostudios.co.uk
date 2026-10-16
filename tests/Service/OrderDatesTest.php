<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\Order;
use App\Enum\OrderStatus;
use App\Service\OrderDates;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;

/**
 * The expected dispatch date suggested when marking In Production, and the
 * stage itself (Step 11.2).
 */
final class OrderDatesTest extends TestCase
{
    private const TODAY = '2026-10-09';

    public function testAQuotesCastingDate(): void
    {
        self::assertSame('2026-11-16', OrderDates::suggest(new \DateTimeImmutable('2026-11-16'), new \DateTimeImmutable('2026-10-01'), new \DateTimeImmutable(self::TODAY))->format('Y-m-d'));
    }

    public function testTheEndOfTheLeadTimeFromPayment(): void
    {
        self::assertSame('2026-10-29', OrderDates::suggest(null, new \DateTimeImmutable('2026-10-08 15:00', new \DateTimeZone('Europe/London')), new \DateTimeImmutable(self::TODAY))->format('Y-m-d'));
    }

    public function testNeverBeforeToday(): void
    {
        self::assertSame(self::TODAY, OrderDates::suggest(new \DateTimeImmutable('2026-10-01'), new \DateTimeImmutable('2026-09-01'), new \DateTimeImmutable(self::TODAY))->format('Y-m-d'), 'a casting date already passed');
        self::assertSame(self::TODAY, OrderDates::suggest(null, new \DateTimeImmutable('2026-09-01'), new \DateTimeImmutable(self::TODAY))->format('Y-m-d'), 'paid long ago');
    }

    public function testTheStage(): void
    {
        $order = new Order();
        self::assertFalse($order->canStartProduction(), 'not before it is paid');

        $order->setStatus(OrderStatus::DEPOSIT_PAID);
        self::assertTrue($order->canStartProduction(), 'a deposit is enough');
        self::assertSame('Deposit Paid', $order->getDisplayStatus());

        $order->markInProduction(new \DateTimeImmutable('2026-10-22'), new CarbonImmutable());
        self::assertFalse($order->canStartProduction(), 'only once');
        self::assertSame('In Production', $order->getDisplayStatus());
        self::assertSame(OrderStatus::DEPOSIT_PAID, $order->getStatus(), 'the payment status is kept');
        self::assertSame('2026-10-22', $order->getExpectedDispatch()?->format('Y-m-d'));
    }
}

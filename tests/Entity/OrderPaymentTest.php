<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\DesignRequest;
use App\Entity\Order;
use App\Entity\OrderItem;
use App\Enum\DesignRequestStatus;
use App\Enum\OrderStatus;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;

/**
 * What an order charges now, what has been paid and what is still owed
 * (a quote paid by deposit, Step 10b.3; basket orders are paid in full), and
 * the Deposit Paid / Paid statuses (10b.4).
 */
final class OrderPaymentTest extends TestCase
{
    public function testABasketOrderIsChargedAndPaidInFull(): void
    {
        $order = new Order();
        $order->setTotal(81195);
        self::assertSame(81195, $order->getChargeNow());
        self::assertSame(0, $order->getAmountOwed(), 'nothing owed before any payment');

        $order->setAmountPaid($order->getChargeNow());
        self::assertSame(0, $order->getAmountOwed());
    }

    public function testADepositLeavesTheBalanceOwed(): void
    {
        $order = new Order();
        $order->setTotal(391000);
        $order->setChargeNow(156400);
        self::assertSame(156400, $order->getChargeNow());

        $order->setAmountPaid($order->getChargeNow());
        self::assertSame(234600, $order->getAmountOwed());
    }

    public function testAnExtraLineHasNoLamp(): void
    {
        self::assertTrue((new OrderItem(product: null, productTitle: 'Custom pigment development', unitPrice: 15000, quantity: 1))->isExtra());
    }

    public function testTheBalanceCanBeRequestedUntilPaid(): void
    {
        $request = new DesignRequest('Ada Test', 'ada.test@example.test', '07700900000');
        $order = new Order();
        $order->setDesignRequest($request);
        $order->setTotal(391000);
        $order->setChargeNow(156400);
        self::assertFalse($order->canRequestBalance(), 'not before the deposit is paid');

        $order->setStatus(OrderStatus::DEPOSIT_PAID);
        $request->setStatus(DesignRequestStatus::DEPOSIT_PAID);
        $order->setAmountPaid(156400);
        self::assertTrue($order->isPaid(), 'a deposit counts as a payment taken');
        self::assertTrue($order->canRequestBalance());

        $order->markBalancePaid(new CarbonImmutable('2026-11-20 10:00'));
        self::assertSame(391000, $order->getAmountPaid());
        self::assertSame(0, $order->getAmountOwed());
        self::assertFalse($order->canRequestBalance());
        self::assertNotNull($order->getBalancePaidAt());
        self::assertSame(OrderStatus::PAID, $order->getStatus(), 'the order is Paid once the balance is');
        self::assertSame(DesignRequestStatus::PAID, $request->getStatus(), 'and so is its request');
    }

    public function testTheStatusesReadDepositPaidAndPaid(): void
    {
        self::assertSame('Deposit Paid', OrderStatus::DEPOSIT_PAID->label());
        self::assertSame('Paid', OrderStatus::PAID->label());
        self::assertSame('Deposit Paid', DesignRequestStatus::DEPOSIT_PAID->label());
        self::assertSame('Paid', DesignRequestStatus::PAID->label());
    }
}

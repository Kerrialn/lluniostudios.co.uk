<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\Order;
use App\Entity\OrderItem;
use PHPUnit\Framework\TestCase;

/**
 * What an order charges now, what has been paid and what is still owed
 * (a quote paid by deposit, Step 10b.3; basket orders are paid in full).
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
}

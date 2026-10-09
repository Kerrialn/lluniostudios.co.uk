<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\Order;
use App\Entity\OrderItem;
use App\Enum\Courier;
use App\Enum\OrderStatus;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;

/**
 * Dispatched (Step 11.3): only once paid in full, the courier and its
 * tracking link.
 */
final class OrderDispatchTest extends TestCase
{
    public function testOnlyOncePaidInFull(): void
    {
        $order = new Order();
        $order->setTotal(391000);
        $order->setChargeNow(156400);
        self::assertFalse($order->canDispatch(), 'not before any payment');

        $order->setStatus(OrderStatus::DEPOSIT_PAID);
        $order->setAmountPaid(156400);
        self::assertTrue($order->canDispatch(), 'offered, so the page can explain');
        self::assertFalse($order->canDispatchNow(), 'but not while the balance is owed');

        $order->markInProduction(new \DateTimeImmutable('2026-10-22'), new CarbonImmutable());
        $order->markBalancePaid(new CarbonImmutable());
        self::assertTrue($order->canDispatchNow(), 'in production and paid in full');

        $order->markDispatched(Courier::DPD, '1550 1234 5678', new CarbonImmutable());
        self::assertSame('Dispatched', $order->getDisplayStatus());
        self::assertSame(OrderStatus::PAID, $order->getStatus(), 'the payment status is kept');
        self::assertFalse($order->canDispatch(), 'only once');
        self::assertFalse($order->canStartProduction());
        self::assertSame('https://track.dpd.co.uk/search?reference=155012345678', $order->getTrackingUrl());
    }

    public function testPalletwaysHasNoLinkAndIsLikelyForPallets(): void
    {
        self::assertNull(Courier::PALLETWAYS->trackingUrl('PW123456'));

        $order = new Order();
        self::assertSame(Courier::DPD, $order->getLikelyCourier());
        $order->setShippingServiceName('Palletways Economy (estimate)');
        self::assertSame(Courier::PALLETWAYS, $order->getLikelyCourier());
    }

    public function testTheOnItsWaySentence(): void
    {
        $lamp = static fn (string $size, int $quantity): OrderItem => new OrderItem(product: null, productTitle: 'Lumen Forma ' . $size, unitPrice: 79900, quantity: $quantity, optionsSnapshot: [[
            'option' => 'The Pigment',
            'value' => 'Grey',
            'lamp' => 0,
        ]]);
        $order = static function (array $items): Order {
            $order = new Order();
            foreach ($items as $item) {
                $order->addItem($item);
            }

            return $order;
        };

        self::assertSame('Your Lumen Forma Gallery lamp has left the studio and is on its way to you.', $order([$lamp('Gallery', 1)])->getOnItsWaySentence());
        self::assertSame('Your Lumen Forma Gallery lamps have left the studio and are on their way to you.', $order([$lamp('Gallery', 2)])->getOnItsWaySentence());
        self::assertSame('Your Lumen Forma Gallery and Studio lamps have left the studio and are on their way to you.', $order([$lamp('Gallery', 1), $lamp('Studio', 1)])->getOnItsWaySentence());
        self::assertSame('Your Lumen Forma Atelier, Gallery and Studio lamps have left the studio and are on their way to you.', $order([$lamp('Atelier', 2), $lamp('Gallery', 1), $lamp('Studio', 1)])->getOnItsWaySentence());
        self::assertSame('Your Lumen Forma Atelier lamp has left the studio and is on its way to you.', $order([$lamp('Atelier', 1), new OrderItem(product: null, productTitle: 'Custom pigment development', unitPrice: 15000, quantity: 1)])->getOnItsWaySentence(), 'extra lines are not lamps');
    }
}

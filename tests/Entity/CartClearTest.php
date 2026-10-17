<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\Cart;
use App\Entity\CartItem;
use App\Entity\Product;
use App\Enum\GiftSendTo;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class CartClearTest extends TestCase
{
    public function testClearEmptiesTheBasketAndForgetsTheGift(): void
    {
        $cart = new Cart();
        $cart->addCartItem(new CartItem(1, new Product()));
        $cart->setGiftArriveBy(new DateTimeImmutable('2026-12-01'), GiftSendTo::cases()[0]);

        $cart->clear();

        self::assertTrue($cart->isEmpty());
        self::assertFalse($cart->isGift());
        self::assertNull($cart->getGiftArriveBy());
        self::assertNull($cart->getGiftSendTo());
    }

    public function testClearForgetsTheGiftOfAnAlreadyEmptiedBasket(): void
    {
        // Paying empties a signed-in buyer's basket first (OrderProcessor::markPaid).
        $cart = new Cart();
        $cart->setGiftArriveBy(new DateTimeImmutable('2026-12-01'), GiftSendTo::cases()[0]);

        $cart->clear();

        self::assertFalse($cart->isGift());
        self::assertNull($cart->getGiftArriveBy());
    }
}

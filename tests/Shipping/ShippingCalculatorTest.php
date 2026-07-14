<?php

declare(strict_types=1);

namespace App\Tests\Shipping;

use App\Entity\Address;
use App\Entity\Cart;
use App\Entity\CartItem;
use App\Entity\Product;
use App\Enum\ShippingMethod;
use App\Service\ShippingCalculator;
use App\Shipping\Provider\DpdProvider;
use App\Shipping\Provider\MockDpdProvider;
use App\Shipping\Provider\MockPalletwaysProvider;
use App\Shipping\Provider\PalletwaysProvider;
use App\Shipping\Provider\PickupProvider;
use App\Shipping\ShippingProviderResolver;
use App\Shipping\ShippingQuote;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\HttpClient\MockHttpClient;

final class ShippingCalculatorTest extends TestCase
{
    private function calculator(): ShippingCalculator
    {
        $http = new MockHttpClient();
        $logger = new NullLogger();

        $resolver = new ShippingProviderResolver(
            new PickupProvider(),
            new MockDpdProvider(),
            new MockPalletwaysProvider(),
            new DpdProvider($http, $logger),
            new PalletwaysProvider($http, $logger),
            'mock',
        );

        return new ShippingCalculator($resolver);
    }

    private function cart(int $weightGrams, int $lengthMm, int $widthMm, int $heightMm, int $unitPricePence): Cart
    {
        $product = new Product();
        $product->setWeightGrams($weightGrams);
        $product->setLengthMm($lengthMm);
        $product->setWidthMm($widthMm);
        $product->setHeightMm($heightMm);

        $item = new CartItem(1, $product);
        $item->setUnitPrice((string) $unitPricePence);

        $cart = new Cart();
        $cart->addCartItem($item);

        return $cart;
    }

    /**
     * @param list<ShippingQuote> $quotes
     *
     * @return list<ShippingMethod>
     */
    private function methods(array $quotes): array
    {
        return array_map(static fn (ShippingQuote $q): ShippingMethod => $q->method, $quotes);
    }

    public function testLightOrderOffersPickupAndDpd(): void
    {
        $quotes = $this->calculator()->quotesForCart(
            $this->cart(20_000, 500, 300, 300, 12000),
            new Address(),
        );

        $methods = $this->methods($quotes);
        self::assertContains(ShippingMethod::PICKUP, $methods);
        self::assertContains(ShippingMethod::DPD, $methods);
        self::assertNotContains(ShippingMethod::PALLET, $methods);
    }

    public function testHeavyOrderOffersPickupAndPallet(): void
    {
        $quotes = $this->calculator()->quotesForCart(
            $this->cart(40_000, 500, 300, 300, 50000),
            new Address(),
        );

        $methods = $this->methods($quotes);
        self::assertContains(ShippingMethod::PICKUP, $methods);
        self::assertContains(ShippingMethod::PALLET, $methods);
        self::assertNotContains(ShippingMethod::DPD, $methods);
    }

    public function testQuotesSortedByPricePickupFirst(): void
    {
        $quotes = $this->calculator()->quotesForCart(
            $this->cart(20_000, 500, 300, 300, 12000),
            new Address(),
        );

        self::assertSame(ShippingMethod::PICKUP, $quotes[0]->method);
        self::assertSame(0, $quotes[0]->priceInPence);
    }

    public function testFindQuoteById(): void
    {
        $cart = $this->cart(20_000, 500, 300, 300, 12000);
        $calculator = $this->calculator();

        $quotes = $calculator->quotesForCart($cart, new Address());
        $target = $quotes[1];

        $found = $calculator->findQuote($cart, new Address(), $target->id());
        self::assertNotNull($found);
        self::assertSame($target->id(), $found->id());
    }
}

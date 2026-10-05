<?php

declare(strict_types=1);

namespace App\Tests\Shipping;

use App\Catalogue\LumenFormaCatalogue;
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
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\HttpClient\MockHttpClient;

final class ShippingCalculatorTest extends TestCase
{
    private function calculator(bool $pickupEnabled = false, bool $palletEnabled = false): ShippingCalculator
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
            $pickupEnabled,
            $palletEnabled,
        );

        return new ShippingCalculator($resolver);
    }

    private function product(int $weightGrams, int $lengthMm, int $widthMm, int $heightMm, int $pricePence): Product
    {
        $product = new Product();
        $product->setWeightGrams($weightGrams);
        $product->setLengthMm($lengthMm);
        $product->setWidthMm($widthMm);
        $product->setHeightMm($heightMm);
        $product->setPrice((string) $pricePence);

        return $product;
    }

    /**
     * A Lumen Forma size with its placeholder weight and dimensions.
     */
    private function lumenForma(string $slug): Product
    {
        $size = array_column(LumenFormaCatalogue::SIZES, null, 'slug')[$slug];

        return $this->product((int) $size['weightGrams'], $size['lengthMm'], $size['widthMm'], $size['heightMm'], $size['price']);
    }

    /**
     * @param list<array{Product, int}> $lines product and quantity per basket line
     */
    private function cart(array $lines): Cart
    {
        $cart = new Cart();
        foreach ($lines as [$product, $quantity]) {
            $item = new CartItem($quantity, $product);
            $item->setUnitPrice($product->getPrice());
            $cart->addCartItem($item);
        }

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

    /**
     * Basket => [boxes in the consignment, expected mock DPD price in pence].
     * Studio is 15kg and Gallery 20kg (placeholders), both in the 10-20kg band
     * (£12.95 per box). Every box is under DPD's 31kg limit, so all five are DPD.
     *
     * @return iterable<string, array{list<array{string, int}>, int, int}>
     */
    public static function lumenFormaBaskets(): iterable
    {
        yield '1 Studio' => [[['lumen-forma-studio', 1]], 1, 1295];
        yield '2 Studio' => [[['lumen-forma-studio', 2]], 2, 2590];
        yield '1 Gallery' => [[['lumen-forma-gallery', 1]], 1, 1295];
        yield '2 Gallery' => [[['lumen-forma-gallery', 2]], 2, 2590];
        yield 'Studio + Gallery' => [[['lumen-forma-studio', 1], ['lumen-forma-gallery', 1]], 2, 2590];
    }

    /**
     * @param list<array{string, int}> $basket
     */
    #[DataProvider('lumenFormaBaskets')]
    public function testLumenFormaBasketGoesAsOneDpdConsignment(array $basket, int $boxes, int $pricePence): void
    {
        $calculator = $this->calculator();
        $cart = $this->cart(array_map(fn (array $line): array => [$this->lumenForma($line[0]), $line[1]], $basket));

        $consignment = $calculator->consignmentForCart($cart);
        self::assertSame($boxes, $consignment->parcelCount(), 'one box per lamp');
        foreach ($consignment->parcels as $box) {
            self::assertLessThanOrEqual(31.0, $box->weightKg());
        }

        $quotes = $calculator->quotesForCart($cart, new Address());
        self::assertSame([ShippingMethod::DPD], $this->methods($quotes), 'DPD only, as one consignment');
        self::assertSame($pricePence, $quotes[0]->priceInPence);
    }

    public function testTwoGalleriesNoLongerCombineIntoOneOverweightParcel(): void
    {
        // Previously the basket was one 40kg parcel, too heavy for DPD.
        $cart = $this->cart([[$this->lumenForma('lumen-forma-gallery'), 2]]);

        $consignment = $this->calculator()->consignmentForCart($cart);
        self::assertSame(40.0, $consignment->totalWeightKg());
        self::assertSame(20.0, $consignment->parcels[0]->weightKg());
    }

    public function testEachBoxIsValuedAtItsLampPrice(): void
    {
        $cart = $this->cart([[$this->lumenForma('lumen-forma-studio'), 1], [$this->lumenForma('lumen-forma-gallery'), 1]]);

        $consignment = $this->calculator()->consignmentForCart($cart);
        self::assertSame(39900, $consignment->parcels[0]->valueInPence);
        self::assertSame(79900, $consignment->parcels[1]->valueInPence);
        self::assertSame(119800, $consignment->totalValueInPence());
    }

    public function testCollectionAndPalletAreOffByDefault(): void
    {
        $light = $this->calculator()->quotesForCart($this->cart([[$this->product(15_000, 150, 150, 400, 39900), 1]]), new Address());
        self::assertNotContains(ShippingMethod::PICKUP, $this->methods($light));
        self::assertNotContains(ShippingMethod::PALLET, $this->methods($light));

        // A box DPD can't carry gets no quote at all while pallet is off.
        $tooHeavy = $this->calculator()->quotesForCart($this->cart([[$this->product(40_000, 500, 300, 300, 50000), 1]]), new Address());
        self::assertSame([], $tooHeavy);
    }

    public function testPalletCanBeSwitchedBackOn(): void
    {
        $quotes = $this->calculator(palletEnabled: true)->quotesForCart(
            $this->cart([[$this->product(40_000, 500, 300, 300, 50000), 1]]),
            new Address(),
        );

        self::assertSame([ShippingMethod::PALLET], $this->methods($quotes));
    }

    public function testCollectionCanBeSwitchedBackOn(): void
    {
        $quotes = $this->calculator(pickupEnabled: true)->quotesForCart(
            $this->cart([[$this->product(15_000, 150, 150, 400, 39900), 1]]),
            new Address(),
        );

        self::assertSame(ShippingMethod::PICKUP, $quotes[0]->method);
        self::assertSame(0, $quotes[0]->priceInPence);
        self::assertContains(ShippingMethod::DPD, $this->methods($quotes));
    }

    public function testFindQuoteById(): void
    {
        $cart = $this->cart([[$this->lumenForma('lumen-forma-gallery'), 2]]);
        $calculator = $this->calculator();

        $target = $calculator->quotesForCart($cart, new Address())[0];

        $found = $calculator->findQuote($cart, new Address(), $target->id());
        self::assertNotNull($found);
        self::assertSame($target->id(), $found->id());
    }
}

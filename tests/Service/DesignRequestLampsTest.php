<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Catalogue\LumenFormaCatalogue;
use App\Catalogue\LumenFormaConfigurator;
use App\Entity\Cart;
use App\Entity\CartItem;
use App\Entity\CartItemOption;
use App\Entity\DesignRequest;
use App\Entity\Product;
use App\Entity\ProductOption;
use App\Entity\ProductOptionValue;
use App\Form\Form\ReserveForm;
use App\Repository\ProductRepository;
use App\Service\DesignRequestLamps;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

/**
 * Step 8: the lamps a "Reserve Your Design" request carries.
 */
final class DesignRequestLampsTest extends TestCase
{
    private const LAMP = [
        'The Pigment' => 'White',
        'Cable Colour' => 'Slate Grey',
        'Footing Material' => 'Brushed Copper',
        'Bulb Style' => 'Crown Gold LED',
    ];

    /**
     * @var array<string, Product>
     */
    private array $products = [];

    private DesignRequestLamps $lamps;

    protected function setUp(): void
    {
        foreach (LumenFormaCatalogue::SIZES as $size) {
            $product = new Product();
            $product->setTitle($size['title']);
            $product->setSlug($size['slug']);
            $product->setPrice((string) $size['price']);
            $product->setDirectOrder($size['direct']);
            foreach (LumenFormaCatalogue::OPTIONS as $position => $definition) {
                if ($definition['requestSizesOnly'] && $size['direct']) {
                    continue;
                }

                $option = new ProductOption();
                $option->setName($definition['name']);
                $option->setStage($definition['stage']);
                $option->setPosition($position);
                foreach ($definition['values'] as $text) {
                    $value = new ProductOptionValue();
                    $value->setValue($text);
                    $value->setGoesToRequest(LumenFormaCatalogue::goesToRequest($text));
                    $option->addProductOptionValue($value);
                }

                $product->addProductOption($option);
            }

            $this->products[$size['slug']] = $product;
        }

        $repository = $this->createStub(ProductRepository::class);
        $repository->method('findBy')->willReturn(array_values($this->products));

        $request = new Request();
        $request->setSession(new Session(new MockArraySessionStorage()));
        $stack = new RequestStack();
        $stack->push($request);

        $this->lamps = new DesignRequestLamps($stack, new LumenFormaConfigurator($repository));
    }

    public function testCarriesTheBasketThenTheLampBeingConfigured(): void
    {
        $cart = new Cart();
        $cart->addCartItem($this->line('lumen-forma-gallery', 1, self::LAMP));
        $this->lamps->holdPending([
            'size' => 'lumen-forma-atelier',
            'quantity' => 2,
            'mode' => 'all',
            'lamps' => [[
                'The Pigment' => 'Custom',
                'Bulb Count' => 'Dual Array',
            ] + self::LAMP],
        ]);

        $items = $this->lamps->items($cart);

        self::assertCount(2, $items);
        self::assertSame(['Gallery', 'Atelier'], [$items[0]->getSizeName(), $items[1]->getSizeName()]);
        self::assertSame(2, $items[1]->getQuantity());
        self::assertSame(169900, $items[1]->getUnitPrice());
        self::assertSame('Dual Array', $items[1]->getValue('Bulb Count'));
        self::assertSame('Slate Grey', $items[0]->getValue('Cable Colour'));
        self::assertTrue(DesignRequestLamps::hasCustomChoice($items));
    }

    public function testTheEditedLineIsReplacedNotAddedTwice(): void
    {
        $cart = new Cart();
        $line = $this->line('lumen-forma-studio', 2, self::LAMP);
        $cart->addCartItem($line);
        $id = (new \ReflectionProperty(CartItem::class, 'id'));
        $id->setValue($line, \Symfony\Component\Uid\Uuid::v7());
        $this->lamps->holdPending([
            'size' => 'lumen-forma-monument',
            'quantity' => 2,
            'mode' => 'all',
            'lamps' => [[
                'Bulb Count' => 'Triple Array',
            ] + self::LAMP],
        ], (string) $line->getId());

        $items = $this->lamps->items($cart);

        self::assertCount(1, $items);
        self::assertSame('Monument', $items[0]->getSizeName());
    }

    public function testPerLampConfigurationsAreKept(): void
    {
        $this->lamps->holdPending([
            'size' => 'lumen-forma-gallery',
            'quantity' => 2,
            'mode' => 'individual',
            'lamps' => [self::LAMP, [
                'The Pigment' => 'Black',
            ] + self::LAMP],
        ]);

        [$item] = $this->lamps->items(null);

        self::assertTrue($item->isCustomisedIndividually());
        self::assertSame(['Lamp 1', 'Lamp 2'], array_column($item->getLampConfigurations(), 'label'));
        self::assertSame('White', $item->getValue('The Pigment', 1));
        self::assertSame('Black', $item->getValue('The Pigment', 2));
        self::assertFalse(DesignRequestLamps::hasCustomChoice([$item]));
    }

    public function testAnyCustomChoiceAsksForCustomDetails(): void
    {
        foreach ([
            'Cable Colour' => 'Custom Textile / Length',
            'Footing Material' => 'Custom Metal Finish',
            'Bulb Style' => 'Custom Specification',
            'The Pigment' => 'Custom',
        ] as $option => $custom) {
            $this->lamps->holdPending([
                'size' => 'lumen-forma-studio',
                'quantity' => 1,
                'mode' => 'all',
                'lamps' => [[
                    $option => $custom,
                ] + self::LAMP],
            ]);

            self::assertTrue(DesignRequestLamps::hasCustomChoice($this->lamps->items(null)), $option);
        }
    }

    public function testForgettingThePendingLampLeavesJustTheBasket(): void
    {
        $this->lamps->holdPending([
            'size' => 'lumen-forma-atelier',
            'quantity' => 1,
            'mode' => 'all',
            'lamps' => [[
                'Bulb Count' => 'Single Array',
            ] + self::LAMP],
        ]);
        $this->lamps->forgetPending();

        self::assertSame([], $this->lamps->items(new Cart()));
    }

    public function testAnIncompletePendingLampIsIgnored(): void
    {
        $this->lamps->holdPending([
            'size' => 'lumen-forma-atelier',
            'quantity' => 1,
            'mode' => 'all',
            'lamps' => [self::LAMP],
        ]);

        self::assertSame([], $this->lamps->items(null));
    }

    public function testALargerPieceIsHeldApartForTheEnquiryWithItsFinishesToBeChosen(): void
    {
        $cart = new Cart();
        $cart->addCartItem($this->line('lumen-forma-gallery', 1, self::LAMP));
        $this->lamps->holdLarger([
            'size' => 'lumen-forma-monument',
            'quantity' => 2,
            'mode' => 'all',
            'lamps' => [[]],
            'finishes' => 'together',
        ]);

        $item = $this->lamps->largerItem();
        self::assertNotNull($item);
        self::assertSame('Monument', $item->getSizeName());
        self::assertSame(2, $item->getQuantity());
        self::assertTrue($item->isFinishesToBeChosen());
        self::assertSame([[
            'label' => null,
            'options' => [],
        ]], $item->getLampConfigurations());
        // Reserve Your Design doesn't carry it: just the basket.
        self::assertCount(1, $this->lamps->items($cart));
        self::assertFalse($this->lamps->hasNewPending());

        $this->lamps->forgetLarger();
        self::assertNull($this->lamps->largerItem());
    }

    public function testADesignFromTheConfiguratorIsNotALargerPiece(): void
    {
        $this->lamps->holdLarger([
            'size' => 'lumen-forma-gallery',
            'quantity' => 3,
            'mode' => 'all',
            'lamps' => [self::LAMP],
        ]);

        self::assertNull($this->lamps->largerItem());
    }

    public function testRequestNumberAndLampCount(): void
    {
        $request = new DesignRequest('Ada Test', 'ada@example.test', '07700 900123');
        $this->lamps->holdPending([
            'size' => 'lumen-forma-gallery',
            'quantity' => 2,
            'mode' => 'all',
            'lamps' => [self::LAMP],
        ]);
        foreach ($this->lamps->items(null) as $item) {
            $request->addItem($item);
        }

        self::assertMatchesRegularExpression('/^LR-\d{6}-\d{4}$/', $request->getRequestNumber());
        self::assertSame('new', $request->getStatus()->value);
        self::assertSame('New', $request->getStatus()->label());
        self::assertSame(2, $request->getLampCount());
    }

    public function testTheAddressInSeparateFields(): void
    {
        $request = new DesignRequest('Ada Test', 'ada@example.test', '07700 900123');
        $request->setAddress('1 High Street', null, 'Douglas', null, 'IM1 1AA', 'IM');

        self::assertSame(['1 High Street', 'Douglas', 'IM1 1AA', 'Isle of Man'], $request->getAddressLines());
        self::assertSame("1 High Street\nDouglas\nIM1 1AA\nIsle of Man", $request->getDeliveryAddress());
        self::assertSame('IM1 1AA', $request->getPostcode());
        self::assertSame('Ada', $request->getFirstName());
        self::assertSame('Cher', (new DesignRequest('  Cher ', 'cher@example.test', '07700 900123'))->getFirstName());
        self::assertNull($request->getLegacyDeliveryAddress());
    }

    public function testAnOlderRequestsPostcodeIsFoundAnywhereInItsAddress(): void
    {
        foreach ([
            "1 High Street\nWimborne\nbh21 1aa" => 'BH21 1AA',
            'Flat 2, 10 Downing St, London SW1A 2AA' => 'SW1A 2AA',
            '1 High Street, Wimborne' => null,
        ] as $address => $postcode) {
            $request = new DesignRequest('Ada Test', 'ada@example.test', '07700 900123');
            (new \ReflectionProperty(DesignRequest::class, 'deliveryAddress'))->setValue($request, $address);
            self::assertSame($postcode, $request->getPostcode(), $address);
            self::assertSame($address, $request->getDeliveryAddress());
        }
    }

    public function testTheCountryAPostcodeBelongsTo(): void
    {
        self::assertSame('GB', ReserveForm::countryForPostcode('BT1 1AA'));
        self::assertSame('GB', ReserveForm::countryForPostcode('BH21 1AA'));
        self::assertSame('IM', ReserveForm::countryForPostcode('im1 1aa'));
        self::assertSame('GG', ReserveForm::countryForPostcode('GY1 1AA'));
        self::assertSame('JE', ReserveForm::countryForPostcode('JE2 3AB'));
    }

    /**
     * @param array<string, string> $choices
     */
    private function line(string $slug, int $quantity, array $choices): CartItem
    {
        $product = $this->products[$slug];
        $line = new CartItem(quantity: $quantity, product: $product);
        foreach ($product->getProductOptions() as $option) {
            foreach ($option->getProductOptionValues() as $value) {
                if (($choices[$option->getName()] ?? null) === $value->getValue()) {
                    new CartItemOption(cartItem: $line, productOption: $option, productOptionValue: $value);
                }
            }
        }

        return $line;
    }
}

<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\Cart;
use App\Entity\CartItem;
use App\Entity\CartItemOption;
use App\Entity\Product;
use App\Entity\ProductOption;
use App\Entity\ProductOptionValue;
use App\Service\BasketRules;
use PHPUnit\Framework\TestCase;

final class BasketRulesTest extends TestCase
{
    private BasketRules $rules;

    protected function setUp(): void
    {
        $this->rules = new BasketRules();
    }

    private function product(bool $direct = true): Product
    {
        return (new Product())->setDirectOrder($direct);
    }

    private function value(string $label, bool $goesToRequest = false): ProductOptionValue
    {
        $option = new ProductOption();
        $option->setName('The Pigment');
        $value = new ProductOptionValue();
        $value->setValue($label);
        $value->setGoesToRequest($goesToRequest);
        $option->addProductOptionValue($value);

        return $value;
    }

    private function line(Cart $cart, int $quantity, ?Product $product = null, ?ProductOptionValue $value = null): CartItem
    {
        $item = new CartItem($quantity, $product ?? $this->product());
        $value ??= $this->value('White');
        new CartItemOption($item, $value->getProductOption(), $value);
        $cart->addCartItem($item);

        return $item;
    }

    public function testTwoLampsInTotalCanCheckOut(): void
    {
        $cart = new Cart();
        $this->line($cart, 1);
        $this->line($cart, 1);

        self::assertSame(2, $this->rules->lampCount($cart));
        self::assertTrue($this->rules->canCheckout($cart));
    }

    public function testThreeLampsAcrossLinesAreBlocked(): void
    {
        $cart = new Cart();
        $this->line($cart, 2);
        $this->line($cart, 1);

        self::assertFalse($this->rules->canCheckout($cart));
        self::assertSame([BasketRules::LIMIT_MESSAGE], $this->rules->checkoutBlockers($cart));
    }

    public function testRequestSizeAndCustomOptionAreBlocked(): void
    {
        $requestSize = new Cart();
        $this->line($requestSize, 1, $this->product(direct: false));
        self::assertSame([BasketRules::REQUEST_SIZE_MESSAGE], $this->rules->checkoutBlockers($requestSize));

        $custom = new Cart();
        $this->line($custom, 1, null, $this->value('Custom', goesToRequest: true));
        self::assertSame([BasketRules::CUSTOM_OPTION_MESSAGE], $this->rules->checkoutBlockers($custom));
    }

    public function testAddingAThirdLampIsRefused(): void
    {
        $cart = new Cart();
        $this->line($cart, 2);

        self::assertSame(BasketRules::LIMIT_MESSAGE, $this->rules->refusalForAdd($cart, $this->product(), 1, [$this->value('White')]));
    }

    public function testAddingThreeInOneGoIsRefused(): void
    {
        self::assertSame(BasketRules::LIMIT_MESSAGE, $this->rules->refusalForAdd(new Cart(), $this->product(), 3, [$this->value('White')]));
    }

    public function testAddingUpToTheLimitIsAllowed(): void
    {
        $cart = new Cart();
        $this->line($cart, 1);

        self::assertNull($this->rules->refusalForAdd($cart, $this->product(), 1, [$this->value('Grey')]));
    }

    public function testEditingALineDoesNotCountItTwice(): void
    {
        $cart = new Cart();
        $line = $this->line($cart, 2);

        // Changing the 2-lamp line's options keeps 2 lamps: allowed.
        self::assertNull($this->rules->refusalForAdd($cart, $this->product(), 2, [$this->value('Black')], $line));
        // Raising it to 3 is not.
        self::assertSame(BasketRules::LIMIT_MESSAGE, $this->rules->refusalForAdd($cart, $this->product(), 3, [$this->value('Black')], $line));
    }

    public function testRouteNotesUseThePrototypeWordingInItsOrder(): void
    {
        self::assertSame('Direct order and checkout.', $this->rules->routeNote([]));
        self::assertSame(
            'Quantities of three or more proceed through the Reserve request process, not direct checkout. Custom options proceed through the Reserve request process.',
            $this->rules->routeNote([BasketRules::CUSTOM_OPTION_MESSAGE, BasketRules::LIMIT_MESSAGE]),
        );
        self::assertSame('This size requires a delivery quote.', $this->rules->routeNote([BasketRules::REQUEST_SIZE_MESSAGE]));
    }

    public function testRequestSizesAndCustomValuesAreRefusedOnAdd(): void
    {
        self::assertSame(BasketRules::REQUEST_SIZE_MESSAGE, $this->rules->refusalForAdd(new Cart(), $this->product(direct: false), 1, [$this->value('White')]));
        self::assertSame(BasketRules::CUSTOM_OPTION_MESSAGE, $this->rules->refusalForAdd(new Cart(), $this->product(), 1, [$this->value('Custom', goesToRequest: true)]));
    }
}

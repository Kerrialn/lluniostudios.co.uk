<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\CartItem;
use App\Entity\CartItemOption;
use App\Entity\OrderItem;
use App\Entity\Product;
use App\Entity\ProductOption;
use App\Entity\ProductOptionValue;
use PHPUnit\Framework\TestCase;

final class CartItemLampsTest extends TestCase
{
    private function option(string $name, int $position): ProductOption
    {
        $option = new ProductOption();
        $option->setName($name);
        $option->setPosition($position);

        return $option;
    }

    private function value(ProductOption $option, string $label): ProductOptionValue
    {
        $value = new ProductOptionValue();
        $value->setValue($label);
        $option->addProductOptionValue($value);

        return $value;
    }

    public function testSharedConfigurationIsOneGroupInConfiguratorOrder(): void
    {
        $pigment = $this->option('The Pigment', 0);
        $cable = $this->option('Cable Colour', 1);
        $item = new CartItem(2, new Product());
        new CartItemOption($item, $cable, $this->value($cable, 'Orange'));
        new CartItemOption($item, $pigment, $this->value($pigment, 'White'));

        $groups = $item->getLampConfigurations();

        self::assertCount(1, $groups);
        self::assertNull($groups[0]['label']);
        self::assertSame(['The Pigment', 'Cable Colour'], array_map(static fn (CartItemOption $o): string => $o->getProductOption()->getName(), $groups[0]['options']));
    }

    public function testIndividualLampsAreGroupedPerLamp(): void
    {
        $pigment = $this->option('The Pigment', 0);
        $white = $this->value($pigment, 'White');
        $black = $this->value($pigment, 'Black');

        $item = new CartItem(2, new Product());
        $item->setCustomisedIndividually(true);
        new CartItemOption($item, $pigment, $white, lamp: 1);
        new CartItemOption($item, $pigment, $black, lamp: 2);

        $groups = $item->getLampConfigurations();

        self::assertSame(['Lamp 1', 'Lamp 2'], array_column($groups, 'label'));
        self::assertSame('White', $groups[0]['options'][0]->getProductOptionValue()->getValue());
        self::assertSame('Black', $groups[1]['options'][0]->getProductOptionValue()->getValue());
    }

    public function testOrderSnapshotKeepsLampsApart(): void
    {
        $orderItem = new OrderItem(new Product(), 'Lumen Forma Studio', 39900, 2, [
            [
                'option' => 'The Pigment',
                'value' => 'White',
                'lamp' => 1,
            ],
            [
                'option' => 'The Pigment',
                'value' => 'Black',
                'lamp' => 2,
            ],
        ]);

        $groups = $orderItem->getLampConfigurations();
        self::assertSame(['Lamp 1', 'Lamp 2'], array_column($groups, 'label'));
        self::assertSame('Black', $groups[1]['options'][0]['value']);

        // Older orders have no lamp key: one shared group.
        $old = new OrderItem(new Product(), 'Lumen Forma Studio', 39900, 1, [[
            'option' => 'The Pigment',
            'value' => 'White',
        ]]);
        self::assertSame([null], array_column($old->getLampConfigurations(), 'label'));
    }
}

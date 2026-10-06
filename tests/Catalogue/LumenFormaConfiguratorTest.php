<?php

declare(strict_types=1);

namespace App\Tests\Catalogue;

use App\Catalogue\LumenFormaCatalogue;
use App\Catalogue\LumenFormaConfigurator;
use App\Entity\Product;
use PHPUnit\Framework\TestCase;

final class LumenFormaConfiguratorTest extends TestCase
{
    public function testSizeNamesHaveNoCollectionPrefixOrAbbreviation(): void
    {
        $names = array_map(
            static fn (array $size): string => LumenFormaConfigurator::sizeName(self::product($size['title'], $size['price'], $size['direct'])),
            LumenFormaCatalogue::SIZES,
        );

        self::assertSame(['Studio', 'Gallery', 'Atelier', 'Estate', 'Monument'], $names);
    }

    public function testPriceLabels(): void
    {
        self::assertSame('£399', LumenFormaConfigurator::priceLabel(self::product('Lumen Forma Studio', 39900, true)));
        self::assertSame('Starting at £1,699', LumenFormaConfigurator::priceLabel(self::product('Lumen Forma Atelier', 169900, false)));
        self::assertSame('Starting at £7,999', LumenFormaConfigurator::priceLabel(self::product('Lumen Forma Monument', 799900, false)));
    }

    public function testStartingChoicesLeaveThePigmentEmpty(): void
    {
        self::assertSame('lumen-forma-gallery', LumenFormaCatalogue::DEFAULT_SIZE);
        self::assertArrayNotHasKey('The Pigment', LumenFormaCatalogue::DEFAULT_VALUES);
        self::assertArrayNotHasKey('Bulb Count', LumenFormaCatalogue::DEFAULT_VALUES);
        self::assertSame(['Cable Colour', 'Footing Material', 'Bulb Style'], array_keys(LumenFormaCatalogue::DEFAULT_VALUES));

        $values = array_column(LumenFormaCatalogue::OPTIONS, 'values', 'name');
        foreach (LumenFormaCatalogue::DEFAULT_VALUES as $option => $value) {
            self::assertContains($value, $values[$option]);
        }
    }

    private static function product(string $title, int $pence, bool $direct): Product
    {
        $product = new Product();
        $product->setTitle($title);
        $product->setPrice((string) $pence);
        $product->setDirectOrder($direct);

        return $product;
    }
}

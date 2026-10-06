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

    public function testStartsOnGalleryWithEveryOptionEmpty(): void
    {
        self::assertSame('lumen-forma-gallery', LumenFormaCatalogue::DEFAULT_SIZE);
        self::assertFalse(\defined(LumenFormaCatalogue::class . '::DEFAULT_VALUES'), 'No option is pre-selected.');
    }

    public function testEveryOptionHasAChoiceMessage(): void
    {
        self::assertSame('Please choose a pigment to continue.', LumenFormaConfigurator::warning('The Pigment'));
        self::assertSame('Please choose a cable to continue.', LumenFormaConfigurator::warning('Cable Colour'));
        self::assertSame('Please choose a footing to continue.', LumenFormaConfigurator::warning('Footing Material'));
        self::assertSame('Please choose a bulb style to continue.', LumenFormaConfigurator::warning('Bulb Style'));
        self::assertSame('Please choose a bulb count to continue.', LumenFormaConfigurator::warning('Bulb Count'));

        self::assertSame(array_column(LumenFormaCatalogue::OPTIONS, 'name'), array_keys(LumenFormaCatalogue::CHOICE_NOUNS));
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

<?php

declare(strict_types=1);

namespace App\Tests\Catalogue;

use App\Catalogue\LumenFormaCatalogue;
use PHPUnit\Framework\TestCase;

final class LumenFormaCatalogueTest extends TestCase
{
    public function testPricesAndRoutes(): void
    {
        $bySlug = array_column(LumenFormaCatalogue::SIZES, null, 'slug');

        self::assertSame(39900, $bySlug['lumen-forma-studio']['price']);
        self::assertSame(79900, $bySlug['lumen-forma-gallery']['price']);
        self::assertSame(169900, $bySlug['lumen-forma-atelier']['price']);
        self::assertSame(349900, $bySlug['lumen-forma-estate']['price']);
        self::assertSame(799900, $bySlug['lumen-forma-monument']['price']);

        $direct = array_keys(array_filter($bySlug, static fn (array $size): bool => $size['direct']));
        self::assertSame(['lumen-forma-studio', 'lumen-forma-gallery'], $direct);
    }

    public function testTitlesHaveNoSizeAbbreviations(): void
    {
        $titles = array_column(LumenFormaCatalogue::SIZES, 'title');

        self::assertSame(['Lumen Forma Studio', 'Lumen Forma Gallery', 'Lumen Forma Atelier', 'Lumen Forma Estate', 'Lumen Forma Monument'], $titles);
    }

    public function testStageTwoIsThePigment(): void
    {
        $pigment = LumenFormaCatalogue::OPTIONS[0];

        self::assertSame('The Pigment', $pigment['name']);
        self::assertSame(['White', 'Grey', 'Black', 'Custom'], $pigment['values']);
    }

    public function testOnlyBulbCountIsLimitedToRequestSizes(): void
    {
        $requestOnly = array_column(array_filter(
            LumenFormaCatalogue::OPTIONS,
            static fn (array $option): bool => $option['requestSizesOnly'],
        ), 'name');

        self::assertSame(['Bulb Count'], $requestOnly);
    }

    public function testCustomValuesGoToRequest(): void
    {
        foreach (LumenFormaCatalogue::OPTIONS as $option) {
            foreach ($option['values'] as $value) {
                self::assertSame(
                    str_starts_with($value, 'Custom'),
                    LumenFormaCatalogue::goesToRequest($value),
                    $value,
                );
            }
        }

        self::assertTrue(LumenFormaCatalogue::goesToRequest('Custom Metal Finish'));
        self::assertFalse(LumenFormaCatalogue::goesToRequest('Black Braided Cotton'));
    }
}

<?php

declare(strict_types=1);

namespace App\Tests\Shipping;

use App\Shipping\DeliveryArea;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Checkout delivers to the UK mainland only; the rest goes by request.
 */
final class DeliveryAreaTest extends TestCase
{
    /**
     * @return iterable<string, array{string, bool}>
     */
    public static function postcodes(): iterable
    {
        yield 'Dorset' => ['DT1 1AA', true];
        yield 'London' => ['SW1A 1AA', true];
        yield 'London, letter in the district' => ['EC1A 1BB', true];
        yield 'Glasgow' => ['G1 1AA', true];
        yield 'Aberdeen city' => ['AB10 1AA', true];
        yield 'Edinburgh' => ['EH1 1AA', true];
        yield 'Perth (PH1)' => ['PH1 1AA', true];
        yield 'Cornwall mainland (TR1)' => ['TR1 1AA', true];
        yield 'Cardiff' => ['CF10 1AA', true];
        yield 'lower case, no space' => ['bh213eg', true];
        yield 'Northern Ireland' => ['BT1 1AA', false];
        yield 'Isle of Man' => ['IM1 1AA', false];
        yield 'Guernsey' => ['GY1 1AA', false];
        yield 'Jersey' => ['JE2 3AA', false];
        yield 'Outer Hebrides' => ['HS1 2AA', false];
        yield 'Shetland' => ['ZE1 0AA', false];
        yield 'Orkney / Caithness' => ['KW15 1AA', false];
        yield 'Inverness' => ['IV1 1AA', false];
        yield 'Highland Aberdeenshire (AB37)' => ['AB37 9AA', false];
        yield 'Argyll (PA20)' => ['PA20 0AA', false];
        yield 'Highland Perthshire (PH17)' => ['PH17 2AA', false];
        yield 'Isle of Arran (KA27)' => ['KA27 8AA', false];
        yield 'Helensburgh (G83)' => ['G83 7AA', false];
        yield 'Isles of Scilly' => ['TR21 0AA', false];
        yield 'not a postcode' => ['Hello', false];
        yield 'half a postcode' => ['BH21', false];
    }

    #[DataProvider('postcodes')]
    public function testIsMainland(string $postcode, bool $mainland): void
    {
        self::assertSame($mainland, DeliveryArea::isMainland($postcode));
    }

    /**
     * @return iterable<string, array{string, ?string, bool}>
     */
    public static function partials(): iterable
    {
        yield 'full postcode' => ['SW1A 1AA', 'SW1A', true];
        yield 'as a wallet shares it' => ['BT1 1', 'BT1', false];
        yield 'district only' => ['IV1', 'IV1', false];
        yield 'letter in the district' => ['EC1A 1', 'EC1A', true];
        yield 'Scilly district' => ['TR21', 'TR21', false];
        yield 'not a postcode' => ['London', null, false];
    }

    #[DataProvider('partials')]
    public function testOutward(string $postcode, ?string $outward, bool $mainland): void
    {
        self::assertSame($outward, DeliveryArea::outward($postcode));
        self::assertSame($mainland, $outward !== null && DeliveryArea::isMainlandOutward($outward));
    }

    public function testNormalise(): void
    {
        self::assertSame('SW1A 1AA', DeliveryArea::normalise(' sw1a   1aa '));
        self::assertSame('EC1A 1BB', DeliveryArea::normalise('ec1a1bb'));
        self::assertNull(DeliveryArea::normalise('BH21'));
    }
}

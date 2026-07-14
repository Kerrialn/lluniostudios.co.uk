<?php

declare(strict_types=1);

namespace App\Tests\Shipping;

use App\Shipping\ParcelSpec;
use PHPUnit\Framework\TestCase;

final class ParcelSpecTest extends TestCase
{
    public function testWeightConversion(): void
    {
        $parcel = new ParcelSpec(31000, 100, 100, 100, 0);
        self::assertSame(31.0, $parcel->weightKg());
    }

    public function testLongestSideCm(): void
    {
        $parcel = new ParcelSpec(0, 1750, 300, 200, 0);
        self::assertSame(175.0, $parcel->longestSideCm());
    }

    public function testLengthPlusGirthCm(): void
    {
        // longest = 1000mm, other two = 1000mm each => (1000 + 2*(1000+1000))/10 = 500cm
        $parcel = new ParcelSpec(0, 1000, 1000, 1000, 0);
        self::assertSame(500.0, $parcel->lengthPlusGirthCm());
    }
}

<?php

declare(strict_types=1);

namespace App\Tests\Shipping;

use App\Entity\Address;
use App\Enum\ShippingMethod;
use App\Shipping\ParcelSpec;
use App\Shipping\Provider\MockDpdProvider;
use App\Shipping\Provider\MockPalletwaysProvider;
use PHPUnit\Framework\TestCase;

final class ShippingTierTest extends TestCase
{
    private MockDpdProvider $dpd;

    private MockPalletwaysProvider $pallet;

    private Address $destination;

    protected function setUp(): void
    {
        $this->dpd = new MockDpdProvider();
        $this->pallet = new MockPalletwaysProvider();
        $this->destination = new Address();
        $this->destination->setRecipientName('Test');
        $this->destination->setPostcode('SW1A 1AA');
    }

    public function testWithinAllLimitsIsDpd(): void
    {
        $parcel = new ParcelSpec(30_000, 500, 300, 300, 12000);
        self::assertTrue($this->dpd->supports($parcel));
        self::assertFalse($this->pallet->supports($parcel));
    }

    public function testExactly31kgIsStillDpd(): void
    {
        $parcel = new ParcelSpec(31_000, 500, 300, 300, 12000);
        self::assertTrue($this->dpd->supports($parcel));
    }

    public function testJustOver31kgGoesPallet(): void
    {
        $parcel = new ParcelSpec(31_001, 500, 300, 300, 12000);
        self::assertFalse($this->dpd->supports($parcel));
        self::assertTrue($this->pallet->supports($parcel));
    }

    public function testLongestSideOver175cmGoesPallet(): void
    {
        // 1760mm = 176cm longest side, light weight
        $parcel = new ParcelSpec(5_000, 1760, 100, 100, 12000);
        self::assertFalse($this->dpd->supports($parcel));
        self::assertTrue($this->pallet->supports($parcel));
    }

    public function testGirthOver300cmGoesPallet(): void
    {
        // longest 100cm (ok) but length+girth = 500cm (> 300)
        $parcel = new ParcelSpec(5_000, 1000, 1000, 1000, 12000);
        self::assertFalse($this->dpd->supports($parcel));
        self::assertTrue($this->pallet->supports($parcel));
    }

    public function testDpdQuoteIsWeightBanded(): void
    {
        $light = $this->dpd->quote(new ParcelSpec(1_500, 300, 200, 200, 5000), $this->destination);
        $heavy = $this->dpd->quote(new ParcelSpec(25_000, 500, 300, 300, 5000), $this->destination);

        self::assertSame(ShippingMethod::DPD, $light[0]->method);
        self::assertLessThan($heavy[0]->priceInPence, $light[0]->priceInPence);
    }

    public function testPalletQuoteMethod(): void
    {
        $quotes = $this->pallet->quote(new ParcelSpec(40_000, 500, 300, 300, 5000), $this->destination);
        self::assertSame(ShippingMethod::PALLET, $quotes[0]->method);
        self::assertSame('Palletways', $quotes[0]->carrier);
    }
}

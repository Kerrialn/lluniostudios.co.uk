<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\DesignRequest;
use App\Entity\DesignRequestItem;
use App\Entity\Quote;
use App\Entity\QuoteLine;
use App\Enum\QuotePayment;
use PHPUnit\Framework\TestCase;

/**
 * A quote's totals: lamps (quantity × price each), extra lines, delivery;
 * the deposit due now (rounded to the penny) and the balance left.
 */
final class QuoteTest extends TestCase
{
    public function testTotalsDepositAndBalance(): void
    {
        $request = new DesignRequest('Ada Test', 'ada@example.test', '07700 900123');
        $quote = new Quote($request);
        $quote->addLine(QuoteLine::forLamp(new DesignRequestItem(null, 'Lumen Forma Atelier', 'Atelier', 169900, 2, false, []), 185000));
        $quote->addLine(QuoteLine::extra('Custom pigment development', 15000));
        $quote->setDelivery(6000, 'DPD Next Day Delivery', false);

        self::assertSame(370000, $quote->getLampsTotal());
        self::assertSame(15000, $quote->getExtrasTotal());
        self::assertSame(391000, $quote->getTotal());
        self::assertSame(391000, $quote->getDueNow());
        self::assertSame(0, $quote->getBalance());

        $quote->setPayment(QuotePayment::DEPOSIT);
        $quote->setDepositPercent(40);
        self::assertSame(156400, $quote->getDueNow());
        self::assertSame(234600, $quote->getBalance());
    }

    public function testDepositRoundsToThePenny(): void
    {
        $quote = new Quote(new DesignRequest('Ada Test', 'ada@example.test', '07700 900123'));
        $quote->addLine(QuoteLine::extra('Odd amount', 33333));
        $quote->setPayment(QuotePayment::DEPOSIT);
        $quote->setDepositPercent(50);

        self::assertSame(16667, $quote->getDueNow());
        self::assertSame(16666, $quote->getBalance());
        self::assertSame(Quote::DEFAULT_DEPOSIT_PERCENT, (new Quote(new DesignRequest('A', 'a@example.test', '1')))->getDepositPercent());
    }
}

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
        $quote->setDelivery(6000, 'DPD Delivery', false);

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

    public function testValidUntilTheEndOfTheFourteenthDayUkTime(): void
    {
        // Sent 8 October 2026, 23:30 UK time (22:30 UTC): valid until 22 October, 23:59:59 UK time.
        $expiry = Quote::expiryFor(new \DateTimeImmutable('2026-10-08 22:30:00', new \DateTimeZone('UTC')));
        self::assertSame('2026-10-22 23:59:59', $expiry->setTimezone(new \DateTimeZone('Europe/London'))->format('Y-m-d H:i:s'));
        // Across the clocks going back (25 October): still the end of the UK day.
        $expiry = Quote::expiryFor(new \DateTimeImmutable('2026-10-20 09:00:00', new \DateTimeZone('UTC')));
        self::assertSame('2026-11-03 23:59:59', $expiry->setTimezone(new \DateTimeZone('Europe/London'))->format('Y-m-d H:i:s'));
    }

    public function testSendingKeepsWhatWasSentUntilSentAgain(): void
    {
        $quote = new Quote(new DesignRequest('Ada Test', 'ada@example.test', '07700 900123'));
        $quote->addLine(QuoteLine::extra('Lamp', 100000));
        $quote->setCastingDate(new \DateTimeImmutable('2026-11-16'));
        self::assertFalse($quote->isSent());
        self::assertFalse($quote->isChangedSinceSent());

        $sentAt = new \DateTimeImmutable('2026-10-08 10:00:00', new \DateTimeZone('UTC'));
        $quote->markSent('token-one-abcdefghijk', $sentAt);
        self::assertTrue($quote->isSent());
        self::assertFalse($quote->isChangedSinceSent());
        self::assertFalse($quote->isExpired(new \DateTimeImmutable('2026-10-22 22:00:00', new \DateTimeZone('UTC'))));
        self::assertTrue($quote->isExpired(new \DateTimeImmutable('2026-10-22 23:00:00', new \DateTimeZone('UTC'))));

        $quote->setDelivery(6000, 'DPD Delivery', false);
        self::assertTrue($quote->isChangedSinceSent());
        self::assertSame(100000, $quote->getSent()['total'] ?? null);

        $quote->markSent('token-two-abcdefghijk', $sentAt->modify('+1 day'));
        self::assertFalse($quote->isChangedSinceSent());
        self::assertSame(106000, $quote->getSent()['total'] ?? null);
        self::assertSame('token-one-abcdefghijk', $quote->getToken(), 'the link stays the same when sent again');
    }
}

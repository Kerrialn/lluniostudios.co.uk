<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\DesignRequest;
use App\Entity\ReferenceNumber;
use App\Event\Listener\ReferenceNumberChecker;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Event\PrePersistEventArgs;
use PHPUnit\Framework\TestCase;

/**
 * Reference numbers: prefix, DDMMYY (UK time), 4 random digits; one already
 * taken is replaced before saving.
 */
final class ReferenceNumberTest extends TestCase
{
    public function testPrefixUkDateAndFourDigits(): void
    {
        // 8 October 2026, 23:30 UK time (22:30 UTC) is still the 8th.
        $number = ReferenceNumber::make('LR', new \DateTimeImmutable('2026-10-08 22:30:00', new \DateTimeZone('UTC')));
        self::assertMatchesRegularExpression('/^LR-081026-\d{4}$/', $number);
        self::assertMatchesRegularExpression(ReferenceNumber::PATTERN, $number);
        self::assertGreaterThanOrEqual(1000, (int) substr($number, -4));
    }

    public function testATakenNumberIsReplaced(): void
    {
        $request = new DesignRequest('Ada Test', 'ada@example.test', '07700 900123');
        $first = $request->getReferenceNumber();

        $repository = $this->createMock(EntityRepository::class);
        $repository->method('count')->willReturnCallback(static fn (array $criteria): int => $criteria['requestNumber'] === $first ? 1 : 0);
        $manager = $this->createMock(EntityManagerInterface::class);
        $manager->method('getRepository')->willReturn($repository);

        (new ReferenceNumberChecker())->prePersist(new PrePersistEventArgs($request, $manager));

        self::assertNotSame($first, $request->getReferenceNumber());
        self::assertMatchesRegularExpression('/^LR-\d{6}-\d{4}$/', $request->getRequestNumber());
    }
}

<?php

declare(strict_types=1);

namespace App\Tests\Form;

use App\Form\Form\CommissionFields;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The Bespoke Commissions upload rules the server applies (the same as the
 * page's): PDF, DWG, JPG, PNG or HEIC; up to 50MB a file; 100MB in total.
 */
final class CommissionFieldsTest extends TestCase
{
    private const MB = 1024 * 1024;

    /**
     * @return iterable<string, array{list<array{name: string, size: int, error: int}>, ?string}>
     */
    public static function files(): iterable
    {
        yield 'no files' => [[], null];
        yield 'every allowed type, any case' => [[
            self::file('plan.pdf'), self::file('model.DWG'), self::file('a.jpg'), self::file('b.jpeg'), self::file('c.PNG'), self::file('d.heic'),
        ], null];
        yield 'exactly 50MB' => [[self::file('big.pdf', 50 * self::MB)], null];
        yield 'exactly 100MB in total' => [[self::file('a.pdf', 50 * self::MB), self::file('b.pdf', 50 * self::MB)], null];
        yield 'another type' => [[self::file('plan.pdf'), self::file('notes.txt')], '"notes.txt" can\'t be sent: please choose PDF, DWG, JPG, PNG or HEIC files.'];
        yield 'no extension' => [[self::file('plan')], '"plan" can\'t be sent: please choose PDF, DWG, JPG, PNG or HEIC files.'];
        yield 'ZIP (not allowed)' => [[self::file('drawings.zip')], '"drawings.zip" can\'t be sent: please choose PDF, DWG, JPG, PNG or HEIC files.'];
        yield 'over 50MB' => [[self::file('huge.pdf', 50 * self::MB + 1)], '"huge.pdf" is over 50MB: each file can be up to 50MB.'];
        yield 'over 50MB, refused by the server' => [[self::file('huge.pdf', 0, \UPLOAD_ERR_INI_SIZE)], '"huge.pdf" is over 50MB: each file can be up to 50MB.'];
        yield 'did not arrive whole' => [[self::file('plan.pdf', 0, \UPLOAD_ERR_PARTIAL)], '"plan.pdf" didn\'t arrive. Please choose it again.'];
        yield 'over 100MB in total' => [[self::file('a.pdf', 45 * self::MB), self::file('b.pdf', 45 * self::MB), self::file('c.pdf', 15 * self::MB)], 'These files come to more than 100MB in total. Please choose fewer or smaller files.'];
    }

    /**
     * @param list<array{name: string, size: int, error: int}> $files
     */
    #[DataProvider('files')]
    public function testUploadProblem(array $files, ?string $expected): void
    {
        self::assertSame($expected, CommissionFields::uploadProblem($files));
    }

    public function testTooLargeMessageMatchesTheTotalCheck(): void
    {
        self::assertSame('These files come to more than 100MB in total. Please choose fewer or smaller files.', CommissionFields::tooLargeMessage());
    }

    /**
     * @return array{name: string, size: int, error: int}
     */
    private static function file(string $name, int $size = self::MB, int $error = \UPLOAD_ERR_OK): array
    {
        return [
            'name' => $name,
            'size' => $size,
            'error' => $error,
        ];
    }
}

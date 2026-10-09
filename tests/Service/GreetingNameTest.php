<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Service\GreetingName;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The first name for greetings (Greeting name, decided 9 October 2026).
 */
final class GreetingNameTest extends TestCase
{
    /**
     * @return iterable<string, array{?string, ?string}>
     */
    public static function names(): iterable
    {
        yield 'as typed' => ['Ada Lovelace', 'Ada'];
        yield 'lower case' => ['ada lovelace', 'Ada'];
        yield 'capitals' => ['ADA LOVELACE', 'Ada'];
        yield 'title' => ['Mrs Ada Lovelace', 'Ada'];
        yield 'title with a full stop' => ['Dr. Ada Lovelace', 'Ada'];
        yield 'title and initials' => ['Dr J. R. Smith', 'Smith'];
        yield 'initials run together' => ['J.R. Hartley', 'Hartley'];
        yield 'a single initial' => ['J Smith', 'Smith'];
        yield 'shouted title' => ['MR JOHN SMITH', 'John'];
        yield 'hyphenated' => ['mary-jane watson', 'Mary-Jane'];
        yield 'apostrophe' => ["d'arcy jones", "D'Arcy"];
        yield 'mixed capitals kept' => ['DeShawn Smith', 'DeShawn'];
        yield 'accented, odd capitals' => ['éLODIE durand', 'Élodie'];
        yield 'extra spaces' => ['  ada   lovelace ', 'Ada'];
        yield 'only a title' => ['Mr', null];
        yield 'only initials' => ['A. B.', null];
        yield 'empty' => ['', null];
        yield 'none' => [null, null];
        yield 'not a name' => ['12345', null];
    }

    #[DataProvider('names')]
    public function testFirstName(?string $fullName, ?string $expected): void
    {
        self::assertSame($expected, GreetingName::firstName($fullName));
    }
}

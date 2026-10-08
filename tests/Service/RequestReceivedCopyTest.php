<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\DesignRequest;
use App\Entity\DesignRequestItem;
use App\Entity\Product;
use App\Service\RequestReceivedCopy;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Email 02's opening sentence for every combination of reasons.
 */
final class RequestReceivedCopyTest extends TestCase
{
    /**
     * @return iterable<string, array{bool, int, bool, string}>
     */
    public static function combinations(): iterable
    {
        yield 'custom option only' => [true, 1, false, RequestReceivedCopy::CUSTOM];
        yield 'more than two lamps only' => [false, 3, false, RequestReceivedCopy::MORE_THAN_TWO];
        yield 'larger size only' => [false, 1, true, RequestReceivedCopy::LARGER_SIZE];
        yield 'custom + more than two' => [true, 3, false, RequestReceivedCopy::CUSTOM_AND_MORE_THAN_TWO];
        yield 'custom + larger size' => [true, 2, true, RequestReceivedCopy::CUSTOM_AND_LARGER_SIZE];
        yield 'more than two + larger size' => [false, 3, true, RequestReceivedCopy::MORE_THAN_TWO_AND_LARGER_SIZE];
        yield 'all three' => [true, 4, true, RequestReceivedCopy::ALL_THREE];
        yield 'none of the three' => [false, 2, false, RequestReceivedCopy::NONE];
    }

    #[DataProvider('combinations')]
    public function testOpeningSentence(bool $custom, int $lamps, bool $larger, string $expected): void
    {
        $request = new DesignRequest('Ada Test', 'ada@example.test', '07700 900123');

        // A direct-size line holding the lamps (and any Custom choice), plus a
        // larger-size line when needed; exactly $lamps lamps in total.
        $direct = (new Product())->setTitle('Lumen Forma Gallery')->setDirectOrder(true);
        $request->addItem(new DesignRequestItem($direct, 'Lumen Forma Gallery', 'Gallery', 79900, $larger ? $lamps - 1 : $lamps, false, [
            [
                'option' => 'The Pigment',
                'value' => $custom ? 'Custom' : 'White',
                'lamp' => 0,
            ],
            [
                'option' => 'Cable Colour',
                'value' => 'Slate Grey',
                'lamp' => 0,
            ],
        ]));
        if ($larger) {
            $atelier = (new Product())->setTitle('Lumen Forma Atelier')->setDirectOrder(false);
            $request->addItem(new DesignRequestItem($atelier, 'Lumen Forma Atelier', 'Atelier', 169900, 1, false, [
                [
                    'option' => 'The Pigment',
                    'value' => 'Grey',
                    'lamp' => 0,
                ],
            ]));
        }

        self::assertSame($lamps, $request->getLampCount());
        self::assertSame($expected, RequestReceivedCopy::opening($request));
    }

    public function testACustomCableCountsAsACustomOption(): void
    {
        $request = new DesignRequest('Ada Test', 'ada@example.test', '07700 900123');
        $request->addItem(new DesignRequestItem((new Product())->setTitle('Lumen Forma Studio')->setDirectOrder(true), 'Lumen Forma Studio', 'Studio', 39900, 1, false, [
            [
                'option' => 'Cable Colour',
                'value' => 'Custom Textile / Length',
                'lamp' => 0,
            ],
        ]));

        self::assertSame(RequestReceivedCopy::CUSTOM, RequestReceivedCopy::opening($request));
    }

    public function testTheSharedParagraph(): void
    {
        self::assertSame('Your casting slot is now secured. No payment has been taken.', RequestReceivedCopy::SECURED);
    }
}

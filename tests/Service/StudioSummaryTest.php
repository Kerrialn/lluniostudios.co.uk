<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\DesignRequest;
use App\Entity\DesignRequestItem;
use App\Service\StudioSummary;
use PHPUnit\Framework\TestCase;

/**
 * The studio notification's subject and summary (email rules, Step 11.0).
 */
final class StudioSummaryTest extends TestCase
{
    public function testLampsBySizeInTheRequestsOrder(): void
    {
        $request = $this->request([
            $this->item('Gallery', 2, []),
            $this->item('Atelier', 1, []),
            $this->item('Gallery', 1, []),
        ]);

        self::assertSame(['Gallery × 3', 'Atelier × 1'], StudioSummary::lamps($request));
    }

    public function testCustomChoicesInTheConfiguratorsOrder(): void
    {
        $request = $this->request([
            $this->item('Atelier', 1, [
                [
                    'option' => 'Footing Material',
                    'value' => 'Custom Metal Finish',
                ],
                [
                    'option' => 'The Pigment',
                    'value' => 'Custom',
                ],
                [
                    'option' => 'Cable Colour',
                    'value' => 'Raw Jute',
                ],
            ]),
            $this->item('Gallery', 1, [
                [
                    'option' => 'The Pigment',
                    'value' => 'Custom',
                    'lamp' => 1,
                ],
            ]),
        ]);

        self::assertSame(['pigment', 'footing'], StudioSummary::customNames($request));
        self::assertSame('Custom pigment, footing', StudioSummary::customs($request));
        self::assertSame('New request · Atelier × 1, Gallery × 1 · Custom pigment, footing', StudioSummary::requestSubject($request));
    }

    public function testNoCustomChoice(): void
    {
        $request = $this->request([$this->item('Studio', 3, [[
            'option' => 'The Pigment',
            'value' => 'White',
        ]])]);

        self::assertNull(StudioSummary::customs($request));
        self::assertSame('New request · Studio × 3', StudioSummary::requestSubject($request));
    }

    public function testAGiftCarriedFromCheckout(): void
    {
        $request = $this->request([$this->item('Gallery', 2, [])], 'Gift · Arrive by 23 October 2026');

        self::assertSame('2026-10-23', StudioSummary::giftDate($request)?->format('Y-m-d'));
        self::assertSame('New request · Gallery × 2 · GIFT by 23 Oct', StudioSummary::requestSubject($request));
        self::assertNull(StudioSummary::giftDate($this->request([$this->item('Gallery', 1, [])], 'Two flights of stairs.')));
    }

    /**
     * @param list<DesignRequestItem> $items
     */
    private function request(array $items, ?string $notes = null): DesignRequest
    {
        $request = new DesignRequest('Ada Test', 'ada.test@example.test', '07700900000', $notes);
        foreach ($items as $item) {
            $request->addItem($item);
        }

        return $request;
    }

    /**
     * @param array<int, array{option: string, value: string, lamp?: int}> $options
     */
    private function item(string $size, int $quantity, array $options): DesignRequestItem
    {
        return new DesignRequestItem(null, 'Lumen Forma ' . $size, $size, 79900, $quantity, false, $options);
    }
}

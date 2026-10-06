<?php

declare(strict_types=1);

namespace App\Tests\Catalogue;

use App\Catalogue\LumenFormaCatalogue;
use App\Catalogue\LumenFormaConfiguration;
use App\Catalogue\LumenFormaConfigurator;
use App\Entity\Product;
use App\Entity\ProductOption;
use App\Entity\ProductOptionValue;
use App\Repository\ProductRepository;
use PHPUnit\Framework\TestCase;

/**
 * The server's reading of a design sent by the configurator.
 */
final class LumenFormaConfiguratorReadTest extends TestCase
{
    private const LAMP = [
        'The Pigment' => 'White',
        'Cable Colour' => 'Slate Grey',
        'Footing Material' => 'Brushed Copper',
        'Bulb Style' => 'Crown Gold LED',
    ];

    public function testReadsACompleteDesignForAllLamps(): void
    {
        $design = $this->configurator()->read([
            'size' => 'lumen-forma-gallery',
            'quantity' => 2,
            'mode' => 'all',
            'lamps' => [self::LAMP],
        ]);

        self::assertInstanceOf(LumenFormaConfiguration::class, $design);
        self::assertSame('lumen-forma-gallery', $design->product->getSlug());
        self::assertSame(2, $design->quantity);
        self::assertFalse($design->individually);
        self::assertCount(1, $design->lamps);
        self::assertSame(array_values(self::LAMP), array_map(static fn (ProductOptionValue $value): string => $value->getValue(), $design->values()));
    }

    public function testReadsOneConfigurationPerLampWhenIndividual(): void
    {
        $second = [
            'The Pigment' => 'Black',
        ] + self::LAMP;
        $design = $this->configurator()->read([
            'size' => 'lumen-forma-gallery',
            'quantity' => 2,
            'mode' => 'individual',
            'lamps' => [self::LAMP, $second],
        ]);

        self::assertInstanceOf(LumenFormaConfiguration::class, $design);
        self::assertTrue($design->individually);
        self::assertCount(2, $design->lamps);
        self::assertSame('Black', $design->lamps[1][0][1]->getValue());
    }

    public function testRequestSizesNeedABulbCount(): void
    {
        $configurator = $this->configurator();

        self::assertNull($configurator->read([
            'size' => 'lumen-forma-atelier',
            'quantity' => 1,
            'mode' => 'all',
            'lamps' => [self::LAMP],
        ]));
        self::assertInstanceOf(LumenFormaConfiguration::class, $configurator->read([
            'size' => 'lumen-forma-atelier',
            'quantity' => 1,
            'mode' => 'all',
            'lamps' => [
                self::LAMP + [
                    'Bulb Count' => 'Dual Array',
                ]],
        ]));
    }

    /**
     * @return iterable<string, array{mixed}>
     */
    public static function invalidDesigns(): iterable
    {
        $lamp = self::LAMP;
        $missing = $lamp;
        unset($missing['Bulb Style']);

        yield 'not an object' => ['nonsense'];
        yield 'unknown size' => [[
            'size' => 'lumen-forma-giant',
            'quantity' => 1,
            'mode' => 'all',
            'lamps' => [$lamp],
        ]];
        yield 'no lamps' => [[
            'size' => 'lumen-forma-gallery',
            'quantity' => 1,
            'mode' => 'all',
            'lamps' => [],
        ]];
        yield 'quantity 0' => [[
            'size' => 'lumen-forma-gallery',
            'quantity' => 0,
            'mode' => 'all',
            'lamps' => [$lamp],
        ]];
        yield 'quantity over the maximum' => [[
            'size' => 'lumen-forma-gallery',
            'quantity' => LumenFormaCatalogue::MAX_LAMPS_PER_CONFIGURATION + 1,
            'mode' => 'all',
            'lamps' => [$lamp],
        ]];
        yield 'quantity as text' => [[
            'size' => 'lumen-forma-gallery',
            'quantity' => '1',
            'mode' => 'all',
            'lamps' => [$lamp],
        ]];
        yield 'a choice missing' => [[
            'size' => 'lumen-forma-gallery',
            'quantity' => 1,
            'mode' => 'all',
            'lamps' => [$missing],
        ]];
        yield 'an unknown value' => [[
            'size' => 'lumen-forma-gallery',
            'quantity' => 1,
            'mode' => 'all',
            'lamps' => [[
                'Bulb Style' => 'Neon Tube',
            ] + $lamp],
        ]];
        yield 'an option the size lacks' => [[
            'size' => 'lumen-forma-gallery',
            'quantity' => 1,
            'mode' => 'all',
            'lamps' => [
                $lamp + [
                    'Bulb Count' => 'Dual Array',
                ]],
        ]];
        yield 'individual with too few lamps' => [[
            'size' => 'lumen-forma-gallery',
            'quantity' => 2,
            'mode' => 'individual',
            'lamps' => [$lamp],
        ]];
        yield 'all lamps with two configurations' => [[
            'size' => 'lumen-forma-gallery',
            'quantity' => 2,
            'mode' => 'all',
            'lamps' => [$lamp, $lamp],
        ]];
    }

    public function testOnlyTheLightKeepsItsHeadings(): void
    {
        $headings = array_column($this->configurator()->groups(), 'heading', 'name');

        self::assertSame([
            'The Pigment' => false,
            'Cable Colour' => false,
            'Footing Material' => false,
            'Bulb Style' => true,
            'Bulb Count' => true,
        ], $headings);
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('invalidDesigns')]
    public function testRefusesIncompleteOrUnknownDesigns(mixed $data): void
    {
        self::assertNull($this->configurator()->read($data));
    }

    private function configurator(): LumenFormaConfigurator
    {
        $products = [];
        foreach (LumenFormaCatalogue::SIZES as $size) {
            $product = new Product();
            $product->setTitle($size['title']);
            $product->setSlug($size['slug']);
            $product->setPrice((string) $size['price']);
            $product->setDirectOrder($size['direct']);
            foreach (LumenFormaCatalogue::OPTIONS as $position => $definition) {
                if ($definition['requestSizesOnly'] && $size['direct']) {
                    continue;
                }

                $option = new ProductOption();
                $option->setName($definition['name']);
                $option->setStage($definition['stage']);
                $option->setPosition($position);
                foreach ($definition['values'] as $valuePosition => $text) {
                    $value = new ProductOptionValue();
                    $value->setValue($text);
                    $value->setPosition($valuePosition);
                    $value->setGoesToRequest(LumenFormaCatalogue::goesToRequest($text));
                    $option->addProductOptionValue($value);
                }

                $product->addProductOption($option);
            }

            $products[] = $product;
        }

        $repository = $this->createStub(ProductRepository::class);
        $repository->method('findBy')->willReturn($products);

        return new LumenFormaConfigurator($repository);
    }
}

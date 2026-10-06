<?php

declare(strict_types=1);

namespace App\Catalogue;

use App\Entity\Product;
use App\Entity\ProductOption;
use App\Repository\ProductRepository;

/**
 * What the Lumen Forma configurator shows, read from the five size products
 * and their options (created by app:lumen-forma:setup): the sizes with their
 * prices and specifications, and the option lists in stage order. Options are
 * matched across sizes by name; an option only some sizes have (Bulb Count)
 * lists the sizes it belongs to.
 */
final class LumenFormaConfigurator
{
    public function __construct(
        private readonly ProductRepository $productRepository,
    ) {
    }

    /**
     * @return list<array{slug: string, name: string, price: string, direct: bool, length: ?int, width: ?int, height: ?int, note: string}>
     */
    public function sizes(): array
    {
        return array_map(fn (Product $product): array => [
            'slug' => (string) $product->getSlug(),
            'name' => self::sizeName($product),
            'price' => self::priceLabel($product),
            'direct' => $product->isDirectOrder(),
            'length' => self::cm($product->getLengthMm()),
            'width' => self::cm($product->getWidthMm()),
            'height' => self::cm($product->getHeightMm()),
            'note' => $product->isDirectOrder()
                ? 'Direct Order | ' . LeadTime::MIN_DAYS . '–' . LeadTime::MAX_DAYS . ' Day Dispatch'
                : 'Delivery Quote Required',
        ], $this->products());
    }

    /**
     * Option groups in stage order, then position.
     *
     * @return list<array{name: string, stage: string, values: list<array{value: string, request: bool}>, sizes: list<string>, default: ?string, required: bool, warning: string}>
     */
    public function groups(): array
    {
        $groups = [];
        foreach ($this->products() as $product) {
            foreach ($product->getProductOptions() as $option) {
                $name = $option->getName();
                if (! isset($groups[$name])) {
                    $default = LumenFormaCatalogue::DEFAULT_VALUES[$name] ?? null;
                    $groups[$name] = [
                        'name' => $name,
                        'stage' => (string) $option->getStage(),
                        'position' => $option->getPosition(),
                        'values' => array_values(array_map(static fn ($value): array => [
                            'value' => $value->getValue(),
                            'request' => $value->isGoesToRequest(),
                        ], $option->getProductOptionValues()->toArray())),
                        'sizes' => [],
                        'default' => $default,
                        'required' => null === $default,
                        'warning' => 'Please choose a ' . mb_strtolower((string) preg_replace('/^The /', '', $name)) . ' to continue.',
                    ];
                }
                $groups[$name]['sizes'][] = (string) $product->getSlug();
            }
        }

        $stageOrder = array_flip(array_values(ProductOption::STAGES));
        usort($groups, static fn (array $a, array $b): int => [$stageOrder[$a['stage']] ?? 99, $a['position']] <=> [$stageOrder[$b['stage']] ?? 99, $b['position']]);

        return array_map(static function (array $group): array {
            unset($group['position']);

            return $group;
        }, $groups);
    }

    /**
     * The size products in catalogue order (published or not: the
     * configurator is how they are sold).
     *
     * @return list<Product>
     */
    private function products(): array
    {
        $bySlug = [];
        foreach ($this->productRepository->findBy([
            'slug' => array_column(LumenFormaCatalogue::SIZES, 'slug'),
        ]) as $product) {
            $bySlug[$product->getSlug()] = $product;
        }

        $products = [];
        foreach (LumenFormaCatalogue::SIZES as $size) {
            if (isset($bySlug[$size['slug']])) {
                $products[] = $bySlug[$size['slug']];
            }
        }

        return $products;
    }

    public static function sizeName(Product $product): string
    {
        return (string) preg_replace('/^' . LumenFormaCatalogue::COLLECTION_NAME . '\s+/', '', (string) $product->getTitle());
    }

    /**
     * "£799" for a direct size; "Starting at £1,699" for a Request size,
     * whose price is display-only.
     */
    public static function priceLabel(Product $product): string
    {
        $pounds = '£' . number_format((int) $product->getPrice() / 100, fmod((int) $product->getPrice(), 100.0) > 0 ? 2 : 0);

        return $product->isDirectOrder() ? $pounds : 'Starting at ' . $pounds;
    }

    private static function cm(?int $mm): ?int
    {
        return null === $mm ? null : (int) round($mm / 10);
    }
}

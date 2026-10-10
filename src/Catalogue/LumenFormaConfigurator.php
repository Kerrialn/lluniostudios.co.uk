<?php

declare(strict_types=1);

namespace App\Catalogue;

use App\Entity\Product;
use App\Entity\ProductOption;
use App\Entity\ProductOptionValue;
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
     * @return list<array{name: string, stage: string, values: list<array{value: string, request: bool}>, sizes: list<string>, warning: string, heading: bool, prompt: ?array{label: string, warning: string}}>
     */
    public function groups(): array
    {
        $groups = [];
        foreach ($this->products() as $product) {
            foreach ($product->getProductOptions() as $option) {
                $name = $option->getName();
                if (! isset($groups[$name])) {
                    $groups[$name] = [
                        'name' => $name,
                        'stage' => (string) $option->getStage(),
                        'position' => $option->getPosition(),
                        'values' => array_values(array_map(static fn ($value): array => [
                            'value' => $value->getValue(),
                            'request' => $value->isGoesToRequest(),
                        ], $option->getProductOptionValues()->toArray())),
                        'sizes' => [],
                        'warning' => self::warning($name),
                        'heading' => ! \in_array($name, LumenFormaCatalogue::UNTITLED_OPTIONS, true),
                        'prompt' => LumenFormaCatalogue::CUSTOM_PROMPTS[$name] ?? null,
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
     * Read a design sent by the configurator:
     *   { size: <slug>, quantity: 1..MAX, mode: 'all'|'individual',
     *     lamps: [ { <option name>: <value>, ... }, ... ] }
     * with one entry in lamps for all lamps, or one per lamp when customising
     * individually. Every option the size has must be chosen, by its exact
     * name and value. Returns null for anything incomplete or unknown.
     */
    public function read(mixed $data): ?LumenFormaConfiguration
    {
        if (! \is_array($data) || ! \is_string($data['size'] ?? null) || ! \is_int($data['quantity'] ?? null) || ! \is_array($data['lamps'] ?? null)) {
            return null;
        }

        $product = null;
        foreach ($this->products() as $candidate) {
            if ($candidate->getSlug() === $data['size']) {
                $product = $candidate;
            }
        }

        $quantity = $data['quantity'];
        if (! $product instanceof Product || $quantity < 1 || $quantity > LumenFormaCatalogue::MAX_LAMPS_PER_CONFIGURATION) {
            return null;
        }

        $individually = $quantity >= 2 && 'individual' === ($data['mode'] ?? null);
        $lampsData = array_values($data['lamps']);
        if (\count($lampsData) !== ($individually ? $quantity : 1)) {
            return null;
        }

        $lamps = [];
        foreach ($lampsData as $chosen) {
            if (! \is_array($chosen)) {
                return null;
            }

            $choices = [];
            foreach ($product->getProductOptions() as $option) {
                $wanted = $chosen[$option->getName()] ?? null;
                $match = null;
                foreach ($option->getProductOptionValues() as $value) {
                    if ($value->getValue() === $wanted) {
                        $match = $value;
                    }
                }

                if (! $match instanceof ProductOptionValue) {
                    return null;
                }

                $choices[] = [$option, $match];
            }

            if (\count($chosen) !== \count($choices)) {
                return null;
            }

            $lamps[] = $choices;
        }

        return new LumenFormaConfiguration($product, $quantity, $individually, $lamps);
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

    /**
     * Shown when moving past an option without choosing, e.g. "Please choose
     * a cable to continue.".
     */
    public static function warning(string $option): string
    {
        $noun = LumenFormaCatalogue::CHOICE_NOUNS[$option] ?? mb_strtolower((string) preg_replace('/^The /', '', $option));

        return 'Please choose a ' . $noun . ' to continue.';
    }

    /**
     * The descriptions sent with a design (its "details": one entry per lamp
     * entry, { <option name>: <text> }) for each Custom value chosen, in
     * order: lamp (0-based, as in the configuration), option and text,
     * trimmed and cut to LumenFormaCatalogue::CUSTOM_DESCRIPTION_MAX
     * characters ('' when missing; otherwise kept as written, links included).
     *
     * @return list<array{lamp: int, option: string, text: string}>
     */
    public static function customDescriptions(LumenFormaConfiguration $configuration, mixed $data): array
    {
        $details = \is_array($data) && \is_array($data['details'] ?? null) ? array_values($data['details']) : [];
        $descriptions = [];
        foreach ($configuration->lamps as $index => $choices) {
            $written = \is_array($details[$index] ?? null) ? $details[$index] : [];
            foreach ($choices as [$option, $value]) {
                if (! LumenFormaCatalogue::goesToRequest($value->getValue())) {
                    continue;
                }

                $text = $written[$option->getName()] ?? null;
                $descriptions[] = [
                    'lamp' => $index,
                    'option' => $option->getName(),
                    'text' => \is_string($text) ? trim(mb_substr(trim($text), 0, LumenFormaCatalogue::CUSTOM_DESCRIPTION_MAX)) : '',
                ];
            }
        }

        return $descriptions;
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

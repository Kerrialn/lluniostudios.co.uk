<?php

declare(strict_types=1);

namespace App\Catalogue;

use App\Entity\Product;
use App\Entity\ProductOption;
use App\Entity\ProductOptionValue;

/**
 * A complete design from the configurator, checked against the database:
 * the size, how many lamps, and the chosen value for every option of that
 * size, either once for all lamps or once per lamp ("Customise Individually").
 */
final readonly class LumenFormaConfiguration
{
    /**
     * @param list<list<array{ProductOption, ProductOptionValue}>> $lamps one entry for all lamps, or one per lamp
     */
    public function __construct(
        public Product $product,
        public int $quantity,
        public bool $individually,
        public array $lamps,
    ) {
    }

    /**
     * Every chosen value, across all lamps.
     *
     * @return list<ProductOptionValue>
     */
    public function values(): array
    {
        $values = [];
        foreach ($this->lamps as $choices) {
            foreach ($choices as [, $value]) {
                $values[] = $value;
            }
        }

        return $values;
    }
}

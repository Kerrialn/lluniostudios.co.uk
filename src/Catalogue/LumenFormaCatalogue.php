<?php

declare(strict_types=1);

namespace App\Catalogue;

/**
 * The Lumen Forma range as defined by the Site v4 design: five sizes and the
 * configurator's options. Used by app:lumen-forma:setup to create the data.
 *
 * Prices are in pence. Studio and Gallery are direct orders (checkout); the
 * other sizes always go to Request, so their prices are display-only "from"
 * prices. The price is set by size only; option values carry no price yet.
 *
 * Weights and dimensions are PLACEHOLDERS: the lamp's own size, not the
 * measured packed box, with the heavier end of the weight range. Atelier,
 * Estate and Monument have no weight yet (they are quoted by hand).
 */
final class LumenFormaCatalogue
{
    public const COLLECTION_NAME = 'Lumen Forma';

    public const COLLECTION_SLUG = 'lumen-forma';

    public const DESCRIPTION = 'Hand-cast liquid stone lighting series. Individually poured, cured, and hand-finished to order in our Bournemouth studio.';

    /**
     * @var list<array{slug: string, title: string, price: int, direct: bool, lengthMm: int, widthMm: int, heightMm: int, weightGrams: ?int}>
     */
    public const SIZES = [
        [
            'slug' => 'lumen-forma-studio',
            'title' => 'Lumen Forma Studio (XS)',
            'price' => 39900,
            'direct' => true,
            'lengthMm' => 150,
            'widthMm' => 150,
            'heightMm' => 400,
            'weightGrams' => 15000,
        ],
        [
            'slug' => 'lumen-forma-gallery',
            'title' => 'Lumen Forma Gallery (S)',
            'price' => 79900,
            'direct' => true,
            'lengthMm' => 220,
            'widthMm' => 220,
            'heightMm' => 600,
            'weightGrams' => 20000,
        ],
        [
            'slug' => 'lumen-forma-atelier',
            'title' => 'Lumen Forma Atelier (M)',
            'price' => 169900,
            'direct' => false,
            'lengthMm' => 300,
            'widthMm' => 300,
            'heightMm' => 900,
            'weightGrams' => null,
        ],
        [
            'slug' => 'lumen-forma-estate',
            'title' => 'Lumen Forma Estate (L)',
            'price' => 349900,
            'direct' => false,
            'lengthMm' => 380,
            'widthMm' => 380,
            'heightMm' => 1400,
            'weightGrams' => null,
        ],
        [
            'slug' => 'lumen-forma-monument',
            'title' => 'Lumen Forma Monument (XL)',
            'price' => 799900,
            'direct' => false,
            'lengthMm' => 450,
            'widthMm' => 450,
            'heightMm' => 2000,
            'weightGrams' => null,
        ],
    ];

    /**
     * Options in configurator order. Values starting with "Custom" go to
     * Request. Bulb Count only exists on the Request sizes.
     *
     * @var list<array{name: string, stage: string, requestSizesOnly: bool, values: list<string>}>
     */
    public const OPTIONS = [
        [
            'name' => 'The Pigment',
            'stage' => 'pigment',
            'requestSizesOnly' => false,
            'values' => ['White', 'Grey', 'Black', 'Custom'],
        ],
        [
            'name' => 'Cable Colour',
            'stage' => 'cable',
            'requestSizesOnly' => false,
            'values' => ['Black Braided Cotton', 'Slate Grey', 'Raw Jute', 'Orange', 'Forest Green', 'Natural Linen', 'Black & White', 'Custom Textile / Length'],
        ],
        [
            'name' => 'Footing Material',
            'stage' => 'footing',
            'requestSizesOnly' => false,
            'values' => ['Brushed Copper', 'Brushed Greened Copper', 'Polished Copper', 'Brushed Aluminium', 'Brushed Blackened Aluminium', 'Polished Aluminium', 'Brushed Stainless Steel', 'Polished Stainless Steel', 'Custom Metal Finish'],
        ],
        [
            'name' => 'Bulb Style',
            'stage' => 'light',
            'requestSizesOnly' => false,
            'values' => ['Soft-Filament Amber Globe', 'XXL Spiral Filament', 'Tubular Amber LED', 'Smoked Glass LED', 'Crown Gold LED', 'Clear Vintage Edison', 'Custom Specification'],
        ],
        [
            'name' => 'Bulb Count',
            'stage' => 'light',
            'requestSizesOnly' => true,
            'values' => ['Single Array', 'Dual Array', 'Triple Array'],
        ],
    ];

    public static function goesToRequest(string $value): bool
    {
        return str_starts_with($value, 'Custom');
    }
}

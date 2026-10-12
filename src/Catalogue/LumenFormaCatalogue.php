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
    /**
     * Shown in place of a lamp's options for a larger piece reserved with its
     * size only (Step 7b.4, decided 12 October 2026): "Finishes: to be chosen
     * together" (as a row: Finishes / To be chosen together).
     */
    public const FINISHES_KEY = 'Finishes';

    public const FINISHES_VALUE = 'To be chosen together';

    public const COLLECTION_NAME = 'Lumen Forma';

    public const COLLECTION_SLUG = 'lumen-forma';

    public const DESCRIPTION = 'Hand-cast liquid stone lighting series. Individually poured, cured, and hand-finished to order in our Dorset Studio.';

    /**
     * @var list<array{slug: string, title: string, price: int, direct: bool, lengthMm: int, widthMm: int, heightMm: int, weightGrams: ?int}>
     */
    public const SIZES = [
        [
            'slug' => 'lumen-forma-studio',
            'title' => 'Lumen Forma Studio',
            'price' => 39900,
            'direct' => true,
            'lengthMm' => 150,
            'widthMm' => 150,
            'heightMm' => 400,
            'weightGrams' => 15000,
        ],
        [
            'slug' => 'lumen-forma-gallery',
            'title' => 'Lumen Forma Gallery',
            'price' => 79900,
            'direct' => true,
            'lengthMm' => 220,
            'widthMm' => 220,
            'heightMm' => 600,
            'weightGrams' => 20000,
        ],
        [
            'slug' => 'lumen-forma-atelier',
            'title' => 'Lumen Forma Atelier',
            'price' => 169900,
            'direct' => false,
            'lengthMm' => 300,
            'widthMm' => 300,
            'heightMm' => 900,
            'weightGrams' => null,
        ],
        [
            'slug' => 'lumen-forma-estate',
            'title' => 'Lumen Forma Estate',
            'price' => 349900,
            'direct' => false,
            'lengthMm' => 380,
            'widthMm' => 380,
            'heightMm' => 1400,
            'weightGrams' => null,
        ],
        [
            'slug' => 'lumen-forma-monument',
            'title' => 'Lumen Forma Monument',
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

    /**
     * The configurator starts on Gallery. Every option starts empty and must
     * be chosen (for every lamp) before moving on.
     */
    public const DEFAULT_SIZE = 'lumen-forma-gallery';

    /**
     * Most lamps one configuration (one basket line) can hold. Checkout is
     * separately limited to BasketRules::MAX_CHECKOUT_LAMPS in the whole
     * basket; more than that goes to Reserve.
     */
    public const MAX_LAMPS_PER_CONFIGURATION = 10;

    /**
     * Options listed without a heading in the configurator (the stage title
     * already says what they are). The Light keeps its two headings, which
     * tell Bulb Style and Bulb Count apart.
     *
     * @var list<string>
     */
    public const UNTITLED_OPTIONS = ['The Pigment', 'Cable Colour', 'Footing Material'];

    /**
     * How each option is named in "Please choose a ... to continue.".
     *
     * @var array<string, string>
     */
    public const CHOICE_NOUNS = [
        'The Pigment' => 'pigment',
        'Cable Colour' => 'cable',
        'Footing Material' => 'footing',
        'Bulb Style' => 'bulb style',
        'Bulb Count' => 'bulb count',
    ];

    /**
     * Longest description a customer can write under a Custom choice.
     */
    public const CUSTOM_DESCRIPTION_MAX = 500;

    /**
     * The box under a Custom choice (10 October 2026): its label and the
     * message when it is left empty (it is required whenever its Custom
     * value is chosen, for every lamp). The line under every box is
     * BasketRules::CUSTOM_CHOICE_NOTE. What is written there is carried into
     * Reserve Your Design's "Custom Details".
     *
     * @var array<string, array{label: string, warning: string}>
     */
    public const CUSTOM_PROMPTS = [
        'The Pigment' => [
            'label' => 'Describe the colour you have in mind',
            'warning' => 'Please describe the colour you have in mind to continue.',
        ],
        'Cable Colour' => [
            'label' => 'Describe the cable colour you have in mind or send a link',
            'warning' => 'Please describe the cable colour you have in mind to continue.',
        ],
        'Footing Material' => [
            'label' => 'Describe the footing material and finish you have in mind',
            'warning' => 'Please describe the footing material and finish you have in mind to continue.',
        ],
        'Bulb Style' => [
            'label' => 'Describe the bulb you have in mind or send a link',
            'warning' => 'Please describe the bulb you have in mind to continue.',
        ],
    ];

    public static function goesToRequest(string $value): bool
    {
        return str_starts_with($value, 'Custom');
    }
}

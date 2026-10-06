<?php

declare(strict_types=1);

namespace App\Catalogue;

/**
 * Real Lumen Forma photographs, per size, in the Site v4 prototype's order
 * (Gallery: brightest to darkest). Files are web copies in
 * public/images/lumen-forma (<file>-2000.jpg / -1000.jpg), metadata stripped.
 * A size with no photos shows "Image Coming Soon"; one size's photos are
 * never shown for another.
 *
 * Per photo:
 *  - position    CSS object-position on desktop/tablet (the prototype's crop)
 *  - lightWide / lightNarrow / lightSideways  the photo is light behind the
 *    title on desktop / upright phones / sideways phones (prototype test:
 *    average luminance over 150; sideways measured under the title's lines
 *    as framed sideways), so the title is drawn dark instead of cream
 *  - upright / sideways  framing on phones held upright / sideways: an
 *    object-position (focal point) for a cropped fill, or 'contain' when no
 *    crop can keep the subject whole (the photo is then shown entire on the
 *    dark photo-area background, optionally placed with an object-position:
 *    sideways it sits at the right edge, clear of the title, so the title
 *    has one background behind it). Whole-lamp shots keep the whole lamp;
 *    close-ups keep their lit bulb or detail. Measured per photo: lamp
 *    outline from dark pixels, bulb from the brightest pixels.
 */
final class LumenFormaPhotos
{
    /**
     * Rows: [file, position, lightWide, lightNarrow, lightSideways, upright, sideways].
     *
     * @var array<string, list<array{string, string, bool, bool, bool, string, string}>>
     */
    public const BY_SIZE = [
        'lumen-forma-gallery' => [
            // Whole-lamp shots (lamp outline as fractions of the photo's width and height)
            ['gallery-01', '20% 60%', true, false, false, '0% 50%', 'contain 100% 50%'], // Re-Size APC_0182: lamp x .07-.35
            ['gallery-02', '20% 50%', true, false, false, '0% 50%', 'contain 100% 50%'], // APC_0145-1: lamp x .05-.26
            ['gallery-03', '25% 50%', true, false, false, 'contain', 'contain 100% 50%'], // Re-size APC_0170: lamp x .09-.56: wider than an upright crop
            ['gallery-04', '16% 50%', false, false, false, '0% 50%', 'contain 100% 50%'], // APC_0041: lamp x .06-.45
            ['gallery-05', '18% 50%', false, false, false, '0% 50%', 'contain 100% 50%'], // APC_0069-1: lamp x .05-.44
            // Close-ups (bulb or detail kept in frame)
            ['gallery-06', '50% 40%', false, false, false, '70% 50%', '50% 10%'], // APC_0172: bulb x .58-.64, y .12-.18
            ['gallery-07', '32% 45%', false, false, false, '18% 50%', '50% 56%'], // APC_0050: bulb x .20-.27, y .42-.72
            ['gallery-08', '50% 70%', false, false, false, '50% 50%', '50% 40%'], // Re-size APC_0199: cast body
            ['gallery-09', '40% 60%', false, false, false, '30% 50%', '50% 68%'], // Re-sized APC_0186: switch, lower left
            ['gallery-10', '30% 50%', false, false, false, '0% 50%', '50% 34%'], // APC_0198: bulb x .15-.22, y .31-.46
            ['gallery-11', '60% 50%', false, false, false, '100% 50%', '50% 85%'], // APC_0117: bulbs x .79-.92; lower bulb sideways
            ['gallery-12', '40% 50%', false, false, false, '55% 50%', '50% 60%'], // APC_0116: bulbs x .46-.59; lower bulb sideways
            ['gallery-13', '50% 50%', false, false, false, '30% 50%', '50% 3%'], // APC_0112: bulb x .34-.41, y .03-.13
            ['gallery-14', '50% 50%', false, false, false, '50% 50%', '50% 70%'], // APC_0054: unlit bulb, lower centre
            ['gallery-15', '45% 50%', false, false, false, '35% 50%', '50% 100%'], // APC_0191: lit bulb at the foot (y .85-.99)
            ['gallery-16', '40% 60%', false, false, false, '25% 50%', '50% 84%'], // APC_0057: switch, lower left
            // Whole lamp in a dark room
            ['gallery-17', '20% 50%', false, false, false, '2% 50%', 'contain 100% 50%'], // APC_0259: lamp x ~.13-.30
        ],
    ];

    /**
     * Real photos of single option values, shown on that option's stage, keyed
     * by option name then value (rows as above), e.g.
     *   'Cable Colour' => ['Orange' => ['cable-orange', '50% 50%', false, false, false, '50% 50%', 'contain']],
     * Any value without one shows "Image Coming Soon". None are in use yet:
     * the prototype's two cable photos are parked until the finely edited
     * versions arrive (see "Pending content" in CLAUDE.md).
     *
     * @var array<string, array<string, array{string, string, bool, bool, bool, string, string}>>
     */
    public const BY_OPTION = [];

    /**
     * The photos cross-fading behind the title before the options are
     * revealed (the prototype uses the Gallery photographs).
     */
    public const IDLE_SIZE = 'lumen-forma-gallery';

    /**
     * @return list<array{file: string, position: string, lightWide: bool, lightNarrow: bool, lightSideways: bool, upright: array{fit: string, position: string}, sideways: array{fit: string, position: string}}>
     */
    public static function forSize(string $slug): array
    {
        return array_map(self::photo(...), self::BY_SIZE[$slug] ?? []);
    }

    /**
     * @param array{string, string, bool, bool, bool, string, string} $row
     *
     * @return array{file: string, position: string, lightWide: bool, lightNarrow: bool, lightSideways: bool, upright: array{fit: string, position: string}, sideways: array{fit: string, position: string}}
     */
    private static function photo(array $row): array
    {
        return [
            'file' => $row[0],
            'position' => $row[1],
            'lightWide' => $row[2],
            'lightNarrow' => $row[3],
            'lightSideways' => $row[4],
            'upright' => self::framing($row[5]),
            'sideways' => self::framing($row[6]),
        ];
    }

    /**
     * Every option photo, for the configurator.
     *
     * @return list<array{option: string, value: string, photo: array{file: string, position: string, lightWide: bool, lightNarrow: bool, lightSideways: bool, upright: array{fit: string, position: string}, sideways: array{fit: string, position: string}}}>
     */
    public static function forOptions(): array
    {
        /** @var array<string, array<string, array{string, string, bool, bool, bool, string, string}>> $byOption */
        $byOption = self::BY_OPTION;
        $photos = [];
        foreach ($byOption as $option => $values) {
            foreach ($values as $value => $row) {
                $photos[] = [
                    'option' => $option,
                    'value' => $value,
                    'photo' => self::photo($row),
                ];
            }
        }

        return $photos;
    }

    /**
     * '<x> <y>' is a cropped fill at that focal point; 'contain' or
     * 'contain <x> <y>' shows the whole photo, centred or placed at <x> <y>.
     *
     * @return array{fit: string, position: string}
     */
    private static function framing(string $value): array
    {
        if (! str_starts_with($value, 'contain')) {
            return [
                'fit' => 'cover',
                'position' => $value,
            ];
        }

        return [
            'fit' => 'contain',
            'position' => trim(substr($value, 7)) ?: '50% 50%',
        ];
    }
}

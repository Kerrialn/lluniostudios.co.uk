<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\CommissionEnquiry;
use App\Entity\DesignRequest;

/**
 * The at-a-glance facts for the studio's notification emails (email rules,
 * Step 11.0): the subject ("New request · Atelier × 1 · Custom pigment")
 * and the summary at the top (the lamps by size and quantity, any Custom
 * choices, a gift's "Needed by" date).
 */
final class StudioSummary
{
    /**
     * The short name of each option with a Custom choice.
     */
    private const CUSTOM_NAMES = [
        'The Pigment' => 'pigment',
        'Cable Colour' => 'cable',
        'Footing Material' => 'footing',
        'Bulb Style' => 'bulb',
    ];

    /**
     * The lamps by size, in the request's order, e.g. ["Atelier × 2", "Gallery × 1"].
     *
     * @return list<string>
     */
    public static function lamps(DesignRequest $request): array
    {
        $sizes = [];
        foreach ($request->getItems() as $item) {
            $sizes[$item->getSizeName()] = ($sizes[$item->getSizeName()] ?? 0) + $item->getQuantity();
        }

        $lamps = [];
        foreach ($sizes as $size => $quantity) {
            $lamps[] = $size . ' × ' . $quantity;
        }

        return $lamps;
    }

    /**
     * The options with a Custom choice on any lamp, in the configurator's
     * order, e.g. ["pigment", "footing"].
     *
     * @return list<string>
     */
    public static function customNames(DesignRequest $request): array
    {
        $options = [];
        foreach ($request->getItems() as $item) {
            foreach ($item->getOptionsSnapshot() as $entry) {
                if (str_starts_with($entry['value'], 'Custom')) {
                    $options[$entry['option']] = true;
                }
            }
        }

        $names = [];
        foreach (self::CUSTOM_NAMES as $option => $name) {
            if (isset($options[$option])) {
                $names[] = $name;
                unset($options[$option]);
            }
        }

        foreach (array_keys($options) as $option) {
            $names[] = strtolower($option);
        }

        return $names;
    }

    /**
     * "Custom pigment, footing" when any lamp has a Custom choice, otherwise null.
     */
    public static function customs(DesignRequest $request): ?string
    {
        $names = self::customNames($request);

        return $names === [] ? null : 'Custom ' . implode(', ', $names);
    }

    /**
     * A gift carried from checkout ("Gift · Arrive by 23 October 2026" in the
     * request's notes): its date, otherwise null.
     */
    public static function giftDate(DesignRequest $request): ?\DateTimeImmutable
    {
        if (preg_match('/Gift · Arrive by (\d{1,2} [A-Z][a-z]+ \d{4})/', (string) $request->getAccessNotes(), $match) !== 1) {
            return null;
        }

        $date = \DateTimeImmutable::createFromFormat('!j F Y', $match[1]);

        return $date === false ? null : $date;
    }

    /**
     * "New request · Atelier × 1 · Custom pigment · GIFT by 23 Oct".
     */
    public static function requestSubject(DesignRequest $request): string
    {
        $gift = self::giftDate($request);

        return implode(' · ', array_filter([
            'New request',
            implode(', ', self::lamps($request)),
            self::customs($request),
            $gift === null ? null : 'GIFT by ' . $gift->format('j M'),
        ]));
    }

    /**
     * "New commission · For Your Home · Ada Test · 2 files".
     */
    public static function commissionSubject(CommissionEnquiry $enquiry): string
    {
        $files = $enquiry->getFiles()->count();

        return implode(' · ', array_filter([
            'New commission',
            $enquiry->getClient()->label(),
            $enquiry->getCompany() ?? $enquiry->getName(),
            $files === 0 ? null : $files . ($files === 1 ? ' file' : ' files'),
        ]));
    }
}

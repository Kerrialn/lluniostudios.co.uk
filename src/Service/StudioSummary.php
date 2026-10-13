<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\CommissionEnquiry;
use App\Entity\DesignRequest;
use App\Entity\Order;

/**
 * The at-a-glance facts for the studio's notification emails (email rules,
 * Step 11.0): the subject ("New request · Atelier × 1 · Custom pigment",
 * "New order · Gallery × 2 · £1,598 · GIFT by 23 Oct") and the summary at
 * the top (the lamps by size and quantity, any Custom choices, a gift's
 * "Needed by" date).
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
     * A gift's "Needed by" date (Gift options, 9 October 2026), or for a
     * request made before then, a gift carried from checkout ("Gift · Arrive
     * by 23 October 2026" in its notes); otherwise null.
     */
    public static function giftDate(DesignRequest $request): ?\DateTimeImmutable
    {
        if ($request->isGift()) {
            return $request->getGiftNeededBy();
        }

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
            $gift instanceof \DateTimeImmutable ? 'GIFT by ' . $gift->format('j M') . ($request->isDirectGift() ? ' · DIRECT' : '') : null,
        ]));
    }

    /**
     * A "Begin the Conversation" enquiry (Step 7c.3): "New enquiry · Atelier ×
     * 1 · Video call".
     */
    public static function enquirySubject(DesignRequest $request): string
    {
        return implode(' · ', array_filter([
            'New enquiry',
            implode(', ', self::lamps($request)),
            $request->getTalk()?->label(),
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

    /**
     * An order's lamps by size (its extra lines left out), e.g. ["Gallery × 2"].
     *
     * @return list<string>
     */
    public static function orderLamps(Order $order): array
    {
        $sizes = [];
        foreach ($order->getItems() as $item) {
            if (! $item->isExtra()) {
                $sizes[$item->getSizeName()] = ($sizes[$item->getSizeName()] ?? 0) + $item->getQuantity();
            }
        }

        $lamps = [];
        foreach ($sizes as $size => $quantity) {
            $lamps[] = $size . ' × ' . $quantity;
        }

        return $lamps;
    }

    /**
     * The studio's subject for a payment (Step 11.1):
     * "New order · Gallery × 2 · £1,598 · GIFT by 23 Oct",
     * "Deposit paid · Atelier × 2 · £1,564 of £3,910",
     * "Balance paid · Atelier × 2 · £2,346 · paid in full".
     *
     * @param 'order'|'deposit'|'balance' $kind
     */
    public static function orderSubject(Order $order, string $kind, int $amount): string
    {
        return implode(' · ', array_filter([
            self::orderKind($kind),
            implode(', ', self::orderLamps($order)),
            match ($kind) {
                'deposit' => self::money($amount) . ' of ' . self::money($order->getTotal()),
                default => self::money($amount),
            },
            $kind === 'balance' ? 'paid in full' : null,
            $order->getGiftArriveBy() instanceof \DateTimeImmutable ? 'GIFT by ' . $order->getGiftArriveBy()->format('j M') . ($order->isDirectGift() ? ' · DIRECT' : '') : null,
        ]));
    }

    /**
     * What the payment is: "New order", "Deposit paid" or "Balance paid".
     */
    public static function orderKind(string $kind): string
    {
        return match ($kind) {
            'deposit' => 'Deposit paid',
            'balance' => 'Balance paid',
            default => 'New order',
        };
    }

    /**
     * "£1,598", or "£811.95" when there are pence.
     */
    public static function money(int $pence): string
    {
        return '£' . number_format($pence / 100, $pence % 100 === 0 ? 0 : 2);
    }
}

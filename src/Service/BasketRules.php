<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Cart;
use App\Entity\CartItem;
use App\Entity\Product;
use App\Entity\ProductOptionValue;

/**
 * What can go through instant checkout. Everything else is sent as a Request
 * and quoted by hand:
 *  - at most MAX_CHECKOUT_LAMPS lamps in the whole basket (any mix of direct
 *    sizes), to protect the studio casting schedule;
 *  - direct-order sizes only (Studio, Gallery);
 *  - no option value that goes to Request (the "Custom..." choices).
 * Used when adding to the basket and again at every checkout step.
 */
final class BasketRules
{
    public const MAX_CHECKOUT_LAMPS = 2;

    public const LIMIT_MESSAGE = 'Instant checkout is limited to 2 lamps per order to protect our casting schedule. Larger orders are packed and quoted by hand.';

    public const REQUEST_SIZE_MESSAGE = 'This size is made by request: we confirm the details, delivery and lead time with you before any payment.';

    public const CUSTOM_OPTION_MESSAGE = 'Custom options are quoted by hand: we confirm the details with you before any payment.';

    /**
     * The "Purchase route" notes, shown with the button in the configurator,
     * on the product page and in the basket (see routeNote()).
     */
    public const ROUTE_NOTE_DIRECT = 'Direct order and checkout.';

    /**
     * The prototype's line under the button.
     */
    public const SUB_DIRECT = 'Limited weekly batch capacity. Individually poured, cured, and hand-finished in our studio.';

    public const SUB_RESERVE = 'Small-batch studio capacity. Reserving holds your build slot while we review your specifications and confirm exact lead times.';

    public const ROUTE_NOTES = [
        self::LIMIT_MESSAGE => 'Quantities of three or more proceed through the Reserve request process, not direct checkout.',
        self::REQUEST_SIZE_MESSAGE => 'Hand cast to order. Reserve now to secure your casting slot. Delivery will be quoted for individually.',
        self::CUSTOM_OPTION_MESSAGE => 'Hand cast to order. Reserve now to secure your casting slot. Custom options are developed with you and quoted individually.',
    ];

    /**
     * Shown in the configurator under an option list as soon as a Custom value
     * is chosen there.
     */
    public const CUSTOM_CHOICE_NOTE = 'Custom options are developed with you and quoted individually.';

    /**
     * Replaces the two notes above when a Request size and a Custom choice
     * both apply.
     */
    public const ROUTE_NOTE_SIZE_AND_CUSTOM = 'Hand cast to order. Reserve now to secure your casting slot. Custom options are developed with you, and delivery quoted individually.';

    public function lampCount(Cart $cart, ?CartItem $except = null): int
    {
        $count = 0;
        foreach ($cart->getCartItems() as $item) {
            if ($item !== $except) {
                $count += $item->getQuantity() ?? 0;
            }
        }

        return $count;
    }

    /**
     * Why this basket can't go through checkout, or [] if it can.
     *
     * @return list<string>
     */
    public function checkoutBlockers(Cart $cart): array
    {
        $blockers = [];

        if ($this->lampCount($cart) > self::MAX_CHECKOUT_LAMPS) {
            $blockers[] = self::LIMIT_MESSAGE;
        }

        foreach ($cart->getCartItems() as $item) {
            $product = $item->getProduct();
            if ($product instanceof Product && ! $product->isDirectOrder()) {
                $blockers[] = self::REQUEST_SIZE_MESSAGE;
            }

            foreach ($item->getCartItemOptions() as $option) {
                if ($option->getProductOptionValue()->isGoesToRequest()) {
                    $blockers[] = self::CUSTOM_OPTION_MESSAGE;
                }
            }
        }

        return array_values(array_unique($blockers));
    }

    /**
     * The "Purchase route" note for these reasons (from checkoutBlockers() or
     * refusalForAdd()); the direct-order note when there are none. A Request
     * size and a Custom choice have one note each, or a single combined note
     * when both apply; the 3-or-more-lamps note follows after.
     * purchase_route_controller.js and configurator_controller.js build the
     * same note in the page.
     *
     * @param list<string> $reasons
     */
    public function routeNote(array $reasons): string
    {
        $size = \in_array(self::REQUEST_SIZE_MESSAGE, $reasons, true);
        $custom = \in_array(self::CUSTOM_OPTION_MESSAGE, $reasons, true);

        $notes = [];
        if ($size && $custom) {
            $notes[] = self::ROUTE_NOTE_SIZE_AND_CUSTOM;
        } elseif ($size) {
            $notes[] = self::ROUTE_NOTES[self::REQUEST_SIZE_MESSAGE];
        } elseif ($custom) {
            $notes[] = self::ROUTE_NOTES[self::CUSTOM_OPTION_MESSAGE];
        }

        if (\in_array(self::LIMIT_MESSAGE, $reasons, true)) {
            $notes[] = self::ROUTE_NOTES[self::LIMIT_MESSAGE];
        }

        return $notes === [] ? self::ROUTE_NOTE_DIRECT : implode(' ', $notes);
    }

    public function canCheckout(Cart $cart): bool
    {
        return $this->checkoutBlockers($cart) === [];
    }

    /**
     * Why adding (or, with $replacing, changing a line to) this configuration
     * would stop the basket going through checkout, or null if it's fine.
     *
     * @param iterable<ProductOptionValue> $values
     */
    public function refusalForAdd(Cart $cart, Product $product, int $quantity, iterable $values, ?CartItem $replacing = null): ?string
    {
        if (! $product->isDirectOrder()) {
            return self::REQUEST_SIZE_MESSAGE;
        }

        foreach ($values as $value) {
            if ($value->isGoesToRequest()) {
                return self::CUSTOM_OPTION_MESSAGE;
            }
        }

        if ($this->lampCount($cart, $replacing) + $quantity > self::MAX_CHECKOUT_LAMPS) {
            return self::LIMIT_MESSAGE;
        }

        return null;
    }
}

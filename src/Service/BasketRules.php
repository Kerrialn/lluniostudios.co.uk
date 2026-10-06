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
     * The Site v4 prototype's "Purchase route" notes, shown under the button on
     * the product page and in the basket. Several reasons are joined in this order.
     */
    public const ROUTE_NOTE_DIRECT = 'Direct order and checkout.';

    /**
     * The prototype's line under the button.
     */
    public const SUB_DIRECT = 'Limited weekly batch capacity. Individually poured, cured, and hand-finished in our studio.';

    public const SUB_RESERVE = 'Small-batch studio capacity. Reserving holds your build slot while we review your specifications and confirm exact lead times.';

    public const ROUTE_NOTES = [
        self::LIMIT_MESSAGE => 'Quantities of three or more proceed through the Reserve request process, not direct checkout.',
        self::REQUEST_SIZE_MESSAGE => 'This size requires a delivery quote.',
        self::CUSTOM_OPTION_MESSAGE => 'Custom options proceed through the Reserve request process.',
    ];

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
     * refusalForAdd()); the direct-order note when there are none.
     *
     * @param list<string> $reasons
     */
    public function routeNote(array $reasons): string
    {
        $notes = array_values(array_intersect_key(self::ROUTE_NOTES, array_flip($reasons)));

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

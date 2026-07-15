<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Address;
use App\Entity\Cart;
use App\Shipping\ParcelSpec;
use App\Shipping\ShippingProviderResolver;
use App\Shipping\ShippingQuote;

final readonly class ShippingCalculator
{
    public function __construct(
        private ShippingProviderResolver $resolver,
    ) {
    }

    /**
     * Build a single parcel spec from the cart: total weight across all items,
     * and the largest bounding box per axis (worst-case, so an oversize item
     * pushes the whole order into the pallet tier).
     */
    public function parcelForCart(Cart $cart): ParcelSpec
    {
        $weightGrams = 0;
        $lengthMm = 0;
        $widthMm = 0;
        $heightMm = 0;

        foreach ($cart->getCartItems() as $item) {
            $product = $item->getProduct();
            $quantity = $item->getQuantity() ?? 1;

            $weightGrams += ($product?->getWeightGrams() ?? 0) * $quantity;
            $lengthMm = max($lengthMm, $product?->getLengthMm() ?? 0);
            $widthMm = max($widthMm, $product?->getWidthMm() ?? 0);
            $heightMm = max($heightMm, $product?->getHeightMm() ?? 0);
        }

        return new ParcelSpec($weightGrams, $lengthMm, $widthMm, $heightMm, $cart->getTotal());
    }

    /**
     * @return list<ShippingQuote> sorted by price ascending
     */
    public function quotesForCart(Cart $cart, Address $destination): array
    {
        $parcel = $this->parcelForCart($cart);
        $quotes = [];

        foreach ($this->resolver->providers() as $provider) {
            if (! $provider->supports($parcel)) {
                continue;
            }

            foreach ($provider->quote($parcel, $destination) as $quote) {
                $quotes[] = $quote;
            }
        }

        usort($quotes, static fn (ShippingQuote $a, ShippingQuote $b): int => $a->priceInPence <=> $b->priceInPence);

        return $quotes;
    }

    public function findQuote(Cart $cart, Address $destination, string $quoteId): ?ShippingQuote
    {
        foreach ($this->quotesForCart($cart, $destination) as $quote) {
            if ($quote->id() === $quoteId) {
                return $quote;
            }
        }

        return null;
    }
}

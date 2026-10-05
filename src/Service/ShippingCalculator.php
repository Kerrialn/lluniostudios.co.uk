<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Address;
use App\Entity\Cart;
use App\Shipping\Consignment;
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
     * One box per lamp, all going to the same address as one consignment
     * (e.g. two lamps = one DPD consignment of two parcels). Each box uses
     * the product's weight and dimensions and the lamp's price as its value.
     */
    public function consignmentForCart(Cart $cart): Consignment
    {
        $parcels = [];

        foreach ($cart->getCartItems() as $item) {
            $product = $item->getProduct();
            $box = new ParcelSpec(
                $product?->getWeightGrams() ?? 0,
                $product?->getLengthMm() ?? 0,
                $product?->getWidthMm() ?? 0,
                $product?->getHeightMm() ?? 0,
                (int) $item->getUnitPrice(),
            );

            for ($i = 0; $i < ($item->getQuantity() ?? 1); $i++) {
                $parcels[] = $box;
            }
        }

        return new Consignment($parcels);
    }

    /**
     * @return list<ShippingQuote> sorted by price ascending
     */
    public function quotesForCart(Cart $cart, Address $destination): array
    {
        $consignment = $this->consignmentForCart($cart);
        $quotes = [];

        foreach ($this->resolver->providers() as $provider) {
            if (! $provider->supports($consignment)) {
                continue;
            }

            foreach ($provider->quote($consignment, $destination) as $quote) {
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

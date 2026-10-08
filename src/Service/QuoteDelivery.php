<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Address;
use App\Entity\DesignRequest;
use App\Enum\ShippingMethod;
use App\Shipping\Consignment;
use App\Shipping\DeliveryArea;
use App\Shipping\ParcelSpec;
use App\Shipping\ShippingProviderResolver;
use App\Shipping\ShippingQuote;

/**
 * The delivery estimate for a quote (Step 10b.1): the request's lamps as one
 * consignment (one box per lamp) to the postcode in its address, priced by
 * DPD (parcels) or Palletways (pallets), whichever can carry it and is
 * cheapest. Mock prices until the carriers' accounts exist, so always shown
 * as an estimate the studio can change, with notes on anything that makes it
 * less reliable.
 */
final readonly class QuoteDelivery
{
    public function __construct(
        private ShippingProviderResolver $resolver,
    ) {
    }

    /**
     * @return array{price: ?int, label: string, estimated: bool, notes: list<string>}
     */
    public function estimate(DesignRequest $request): array
    {
        $notes = [];
        $postcode = DeliveryArea::normalise((string) $request->getPostcode());
        if ($postcode === null) {
            $notes[] = 'No postcode found in the delivery address: price delivery by hand.';
        } elseif (! DeliveryArea::isMainland($postcode)) {
            $notes[] = $postcode . ' is outside the UK mainland: carriers charge more there, so check the price.';
        }

        $parcels = [];
        $missing = [];
        foreach ($request->getItems() as $item) {
            $product = $item->getProduct();
            if ($product?->getWeightGrams() === null) {
                $missing[] = $item->getSizeName();
            }

            for ($i = 0; $i < $item->getQuantity(); $i++) {
                $parcels[] = new ParcelSpec(
                    $product?->getWeightGrams() ?? 0,
                    $product?->getLengthMm() ?? 0,
                    $product?->getWidthMm() ?? 0,
                    $product?->getHeightMm() ?? 0,
                    $item->getUnitPrice(),
                );
            }
        }

        if ($missing !== []) {
            $notes[] = 'No packed weight yet for ' . implode(', ', array_values(array_unique($missing))) . ': the estimate counts it as weightless.';
        }

        $consignment = new Consignment($parcels);
        $destination = new Address();
        $destination->setPostcode($postcode ?? '');
        $destination->setCountry('GB');

        $quotes = [];
        foreach ($this->resolver->quoteProviders() as $provider) {
            if ($provider->supports($consignment)) {
                array_push($quotes, ...$provider->quote($consignment, $destination));
            }
        }

        usort($quotes, static fn (ShippingQuote $a, ShippingQuote $b): int => $a->priceInPence <=> $b->priceInPence);
        $best = $quotes[0] ?? null;
        if (! $best instanceof ShippingQuote) {
            return [
                'price' => null,
                'label' => 'Delivery',
                'estimated' => false,
                'notes' => [...$notes, 'No carrier could price this consignment: price delivery by hand.'],
            ];
        }

        return [
            'price' => $best->priceInPence,
            'label' => $best->method === ShippingMethod::PALLET ? 'Palletways · ' . $best->serviceName : 'DPD Next Day Delivery',
            'estimated' => ! $this->resolver->isLive(),
            'notes' => $notes,
        ];
    }
}

<?php

declare(strict_types=1);

namespace App\Shipping;

use App\Shipping\Provider\DpdProvider;
use App\Shipping\Provider\MockDpdProvider;
use App\Shipping\Provider\MockPalletwaysProvider;
use App\Shipping\Provider\PalletwaysProvider;
use App\Shipping\Provider\PickupProvider;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Selects the active set of shipping providers. Checkout offers DPD only.
 * Collect-from-studio and Palletways stay in the code but are switched off
 * (SHIPPING_PICKUP_ENABLED / SHIPPING_PALLET_ENABLED, both off by default).
 * SHIPPING_MODE=live uses the real carrier APIs; anything else (default
 * "mock") uses the deterministic stand-ins so checkout works without carrier keys.
 */
final class ShippingProviderResolver
{
    public function __construct(
        private readonly PickupProvider $pickup,
        private readonly MockDpdProvider $mockDpd,
        private readonly MockPalletwaysProvider $mockPalletways,
        private readonly DpdProvider $dpd,
        private readonly PalletwaysProvider $palletways,
        #[Autowire('%env(SHIPPING_MODE)%')]
        private readonly string $mode = 'mock',
        #[Autowire('%env(bool:SHIPPING_PICKUP_ENABLED)%')]
        private readonly bool $pickupEnabled = false,
        #[Autowire('%env(bool:SHIPPING_PALLET_ENABLED)%')]
        private readonly bool $palletEnabled = false,
    ) {
    }

    /**
     * @return list<ShippingProviderInterface>
     */
    public function providers(): array
    {
        $live = $this->mode === 'live';
        $providers = [$live ? $this->dpd : $this->mockDpd];

        if ($this->palletEnabled) {
            $providers[] = $live ? $this->palletways : $this->mockPalletways;
        }

        if ($this->pickupEnabled) {
            $providers[] = $this->pickup;
        }

        return $providers;
    }
}

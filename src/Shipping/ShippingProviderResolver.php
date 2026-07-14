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
 * Selects the active set of shipping providers. Pickup is always offered.
 * SHIPPING_MODE=live uses the real DPD/Palletways APIs; anything else (default
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
    ) {
    }

    /**
     * @return list<ShippingProviderInterface>
     */
    public function providers(): array
    {
        if ($this->mode === 'live') {
            return [$this->pickup, $this->dpd, $this->palletways];
        }

        return [$this->pickup, $this->mockDpd, $this->mockPalletways];
    }
}

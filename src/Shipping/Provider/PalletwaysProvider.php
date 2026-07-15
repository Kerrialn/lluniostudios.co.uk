<?php

declare(strict_types=1);

namespace App\Shipping\Provider;

use App\Entity\Address;
use App\Enum\ShippingMethod;
use App\Shipping\ParcelSpec;
use App\Shipping\ShippingProviderInterface;
use App\Shipping\ShippingQuote;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Throwable;

/**
 * Live Palletways quotes for >31 kg / oversize shipments. Credentials via
 * PALLETWAYS_API_BASE / PALLETWAYS_API_KEY. Unconfigured → no quotes (mock used).
 *
 * NOTE: finalise the request/response mapping against Palletways' API once the
 * account is issued — this class is the integration seam.
 */
final class PalletwaysProvider implements ShippingProviderInterface
{
    use DpdEligibility;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly LoggerInterface $logger,
        #[Autowire('%env(PALLETWAYS_API_BASE)%')]
        private readonly string $apiBase = '',
        #[Autowire('%env(PALLETWAYS_API_KEY)%')]
        private readonly string $apiKey = '',
    ) {
    }

    public function isConfigured(): bool
    {
        return $this->apiBase !== '' && $this->apiKey !== '';
    }

    public function supports(ParcelSpec $parcel): bool
    {
        return $this->isConfigured() && ! $this->isDpdEligible($parcel);
    }

    public function quote(ParcelSpec $parcel, Address $destination): array
    {
        if (! $this->isConfigured()) {
            return [];
        }

        try {
            $response = $this->httpClient->request('POST', rtrim($this->apiBase, '/') . '/quotes', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->apiKey,
                    'Accept' => 'application/json',
                ],
                'json' => [
                    'deliveryPostcode' => $destination->getPostcode(),
                    'deliveryCountryCode' => $destination->getCountry(),
                    'weightKg' => $parcel->weightKg(),
                    'lengthCm' => $parcel->lengthCm(),
                    'widthCm' => $parcel->widthCm(),
                    'heightCm' => $parcel->heightCm(),
                ],
            ]);

            $data = $response->toArray(false);
            $priceInPence = (int) round(((float) ($data['price'] ?? 0)) * 100);

            if ($priceInPence <= 0) {
                return [];
            }

            return [
                new ShippingQuote(
                    method: ShippingMethod::PALLET,
                    carrier: 'Palletways',
                    serviceName: (string) ($data['serviceName'] ?? 'Pallet delivery'),
                    priceInPence: $priceInPence,
                    estimatedDays: isset($data['transitDays']) ? (int) $data['transitDays'] : 3,
                ),
            ];
        } catch (Throwable $throwable) {
            $this->logger->error('Palletways quote failed: ' . $throwable->getMessage());

            return [];
        }
    }
}

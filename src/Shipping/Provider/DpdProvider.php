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
 * Live DPD UK quotes. Requires a DPD API account; credentials are supplied via
 * DPD_API_BASE / DPD_API_KEY. When unconfigured, returns no quotes so the
 * checkout degrades gracefully (the resolver uses mock providers instead).
 *
 * NOTE: the exact request/response shape must be finalised against DPD's API
 * docs once the account is issued — the mapping below is the integration seam.
 */
final class DpdProvider implements ShippingProviderInterface
{
    use DpdEligibility;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly LoggerInterface $logger,
        #[Autowire('%env(DPD_API_BASE)%')]
        private readonly string $apiBase = '',
        #[Autowire('%env(DPD_API_KEY)%')]
        private readonly string $apiKey = '',
    ) {
    }

    public function isConfigured(): bool
    {
        return $this->apiBase !== '' && $this->apiKey !== '';
    }

    public function supports(ParcelSpec $parcel): bool
    {
        return $this->isConfigured() && $this->isDpdEligible($parcel);
    }

    public function quote(ParcelSpec $parcel, Address $destination): array
    {
        if (! $this->isConfigured()) {
            return [];
        }

        try {
            $response = $this->httpClient->request('POST', rtrim($this->apiBase, '/') . '/shipping/quote', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->apiKey,
                    'Accept' => 'application/json',
                ],
                'json' => [
                    'collectionCountryCode' => 'GB',
                    'deliveryCountryCode' => $destination->getCountry(),
                    'deliveryPostcode' => $destination->getPostcode(),
                    'totalWeightKg' => $parcel->weightKg(),
                    'parcelValue' => $parcel->valueInPence / 100,
                ],
            ]);

            $data = $response->toArray(false);

            // Map the carrier response into our value object.
            $priceInPence = (int) round(((float) ($data['totalPrice'] ?? 0)) * 100);

            if ($priceInPence <= 0) {
                return [];
            }

            return [
                new ShippingQuote(
                    method: ShippingMethod::DPD,
                    carrier: 'DPD',
                    serviceName: (string) ($data['serviceName'] ?? 'DPD Next Day'),
                    priceInPence: $priceInPence,
                    estimatedDays: isset($data['transitDays']) ? (int) $data['transitDays'] : 1,
                ),
            ];
        } catch (Throwable $throwable) {
            $this->logger->error('DPD quote failed: ' . $throwable->getMessage());

            return [];
        }
    }
}

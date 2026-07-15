<?php

declare(strict_types=1);

namespace App\Payment;

use RuntimeException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Server-side client for the Revolut Merchant API (sandbox by default).
 *
 * Authenticates with the merchant Secret key. The public key is used separately
 * by the browser SDK (@revolut/checkout) to collect card details against the
 * order token returned by createOrder().
 *
 * @see https://developer.revolut.com/docs/merchant/merchant-api
 */
final readonly class RevolutMerchantClient
{
    public function __construct(
        private HttpClientInterface $httpClient,
        #[Autowire('%env(REVOLUT_API_BASE)%')]
        private string $apiBase,
        #[Autowire('%env(REVOLUT_SECRET_KEY)%')]
        private string $secretKey,
        #[Autowire('%env(REVOLUT_API_VERSION)%')]
        private string $apiVersion,
    ) {
    }

    public function isConfigured(): bool
    {
        return $this->secretKey !== '' && $this->apiBase !== '';
    }

    /**
     * Create an order. Amount is in minor units (pence for GBP).
     */
    public function createOrder(int $amountMinor, string $currency, string $merchantOrderRef, ?string $description = null): RevolutOrder
    {
        $payload = [
            'amount' => $amountMinor,
            'currency' => $currency,
            'merchant_order_data' => [
                'reference' => $merchantOrderRef,
            ],
        ];

        if ($description !== null) {
            $payload['description'] = $description;
        }

        $data = $this->request('POST', '/api/orders', $payload);

        return RevolutOrder::fromArray($data);
    }

    public function retrieveOrder(string $orderId): RevolutOrder
    {
        $data = $this->request('GET', '/api/orders/' . $orderId);

        return RevolutOrder::fromArray($data);
    }

    public function captureOrder(string $orderId): RevolutOrder
    {
        $data = $this->request('POST', '/api/orders/' . $orderId . '/capture');

        return RevolutOrder::fromArray($data);
    }

    /**
     * @param array<string, mixed>|null $payload
     *
     * @return array<string, mixed>
     */
    private function request(string $method, string $path, ?array $payload = null): array
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException('Revolut Merchant API is not configured (REVOLUT_SECRET_KEY / REVOLUT_API_BASE).');
        }

        $options = [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->secretKey,
                'Revolut-Api-Version' => $this->apiVersion,
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ],
        ];

        if ($payload !== null) {
            $options['json'] = $payload;
        }

        $response = $this->httpClient->request($method, rtrim($this->apiBase, '/') . $path, $options);

        return $response->toArray();
    }
}

<?php

declare(strict_types=1);

namespace App\Tests\Payment;

use App\Payment\RevolutMerchantClient;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class RevolutMerchantClientTest extends TestCase
{
    public function testCreateOrderBuildsCorrectRequest(): void
    {
        $captured = [];

        $http = new MockHttpClient(function (string $method, string $url, array $options) use (&$captured): MockResponse {
            $captured['method'] = $method;
            $captured['url'] = $url;
            $captured['headers'] = $options['headers'] ?? [];
            $captured['body'] = $options['body'] ?? null;

            return new MockResponse(
                json_encode([
                    'id' => 'ord_1',
                    'token' => 'tok_1',
                    'state' => 'pending',
                    'checkout_url' => 'https://pay',
                ]),
                [
                    'http_code' => 200,
                    'response_headers' => [
                        'content-type' => 'application/json',
                    ],
                ],
            );
        });

        $client = new RevolutMerchantClient($http, 'https://sandbox-merchant.revolut.com', 'sk_test', '2024-09-01');
        $order = $client->createOrder(12000, 'GBP', 'LS-20260714-ABC123');

        self::assertSame('ord_1', $order->id);
        self::assertSame('tok_1', $order->token);
        self::assertSame('pending', $order->state);

        self::assertSame('POST', $captured['method']);
        self::assertSame('https://sandbox-merchant.revolut.com/api/orders', $captured['url']);

        $headers = implode("\n", $captured['headers']);
        self::assertStringContainsString('Authorization: Bearer sk_test', $headers);
        self::assertStringContainsString('Revolut-Api-Version: 2024-09-01', $headers);

        $body = json_decode((string) $captured['body'], true);
        self::assertSame(12000, $body['amount']);
        self::assertSame('GBP', $body['currency']);
        self::assertSame('LS-20260714-ABC123', $body['merchant_order_data']['reference']);
    }

    public function testUnconfiguredClientThrows(): void
    {
        $client = new RevolutMerchantClient(new MockHttpClient(), '', '', '2024-09-01');

        $this->expectException(RuntimeException::class);
        $client->createOrder(100, 'GBP', 'ref');
    }
}

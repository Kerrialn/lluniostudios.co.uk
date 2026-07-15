<?php

declare(strict_types=1);

namespace App\Tests\Payment;

use App\Payment\RevolutWebhookVerifier;
use PHPUnit\Framework\TestCase;

final class RevolutWebhookVerifierTest extends TestCase
{
    private const SECRET = 'wsk_test_signing_secret';

    private function sign(string $body, string $timestamp, string $secret = self::SECRET): string
    {
        return 'v1=' . hash_hmac('sha256', 'v1.' . $timestamp . '.' . $body, $secret);
    }

    public function testValidSignaturePasses(): void
    {
        $verifier = new RevolutWebhookVerifier(self::SECRET);
        $body = '{"event":"ORDER_COMPLETED","order_id":"abc"}';
        $ts = (string) (time() * 1000);

        self::assertTrue($verifier->verify($body, $ts, $this->sign($body, $ts)));
    }

    public function testWrongSecretFails(): void
    {
        $verifier = new RevolutWebhookVerifier(self::SECRET);
        $body = '{"event":"ORDER_COMPLETED","order_id":"abc"}';
        $ts = (string) (time() * 1000);

        self::assertFalse($verifier->verify($body, $ts, $this->sign($body, $ts, 'other_secret')));
    }

    public function testTamperedBodyFails(): void
    {
        $verifier = new RevolutWebhookVerifier(self::SECRET);
        $body = '{"event":"ORDER_COMPLETED","order_id":"abc"}';
        $ts = (string) (time() * 1000);
        $signature = $this->sign($body, $ts);

        self::assertFalse($verifier->verify($body . ' ', $ts, $signature));
    }

    public function testStaleTimestampFails(): void
    {
        $verifier = new RevolutWebhookVerifier(self::SECRET);
        $body = '{"event":"ORDER_COMPLETED","order_id":"abc"}';
        $ts = (string) ((time() - 3600) * 1000);

        self::assertFalse($verifier->verify($body, $ts, $this->sign($body, $ts)));
    }

    public function testUnconfiguredFails(): void
    {
        $verifier = new RevolutWebhookVerifier('');
        $body = '{"event":"ORDER_COMPLETED","order_id":"abc"}';
        $ts = (string) (time() * 1000);

        self::assertFalse($verifier->verify($body, $ts, $this->sign($body, $ts, '')));
    }

    public function testMultipleSignaturesInHeader(): void
    {
        $verifier = new RevolutWebhookVerifier(self::SECRET);
        $body = '{"event":"ORDER_COMPLETED","order_id":"abc"}';
        $ts = (string) (time() * 1000);
        $header = 'v1=deadbeef ' . $this->sign($body, $ts);

        self::assertTrue($verifier->verify($body, $ts, $header));
    }
}

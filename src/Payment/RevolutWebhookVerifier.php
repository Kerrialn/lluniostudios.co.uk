<?php

declare(strict_types=1);

namespace App\Payment;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Verifies Revolut webhook signatures.
 *
 * payload_to_sign = "v1.{timestamp}.{raw_body}"
 * expected        = "v1=" . hmac_sha256(signing_secret, payload_to_sign)
 * compared (constant-time) against the Revolut-Signature header, which may hold
 * multiple space-separated signatures during secret rotation.
 *
 * @see https://developer.revolut.com/docs/guides/manage-accounts/webhooks/verify-the-payload-signature
 */
final readonly class RevolutWebhookVerifier
{
    private const TIMESTAMP_TOLERANCE_SECONDS = 300;

    public function __construct(
        #[Autowire('%env(REVOLUT_WEBHOOK_SIGNING_SECRET)%')]
        private string $signingSecret,
    ) {
    }

    public function isConfigured(): bool
    {
        return $this->signingSecret !== '';
    }

    public function verify(string $rawBody, ?string $timestamp, ?string $signatureHeader): bool
    {
        if (! $this->isConfigured() || $timestamp === null || $timestamp === '' || $signatureHeader === null) {
            return false;
        }

        // Reject stale timestamps to mitigate replay attacks.
        // Revolut sends the timestamp in milliseconds; tolerate both ms and s.
        if ((! ctype_digit($timestamp) || abs(time() * 1000 - (int) $timestamp) > self::TIMESTAMP_TOLERANCE_SECONDS * 1000) && (! ctype_digit($timestamp) || abs(time() - (int) $timestamp) > self::TIMESTAMP_TOLERANCE_SECONDS)) {
            return false;
        }

        $payloadToSign = 'v1.' . $timestamp . '.' . $rawBody;
        $expected = 'v1=' . hash_hmac('sha256', $payloadToSign, $this->signingSecret);

        foreach (preg_split('/\s+/', trim($signatureHeader)) ?: [] as $candidate) {
            if ($candidate !== '' && hash_equals($expected, $candidate)) {
                return true;
            }
        }

        return false;
    }
}

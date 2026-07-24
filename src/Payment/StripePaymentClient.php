<?php

declare(strict_types=1);

namespace App\Payment;

use RuntimeException;
use Stripe\Event;
use Stripe\StripeClient;
use Stripe\Webhook;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Server-side client for the Stripe Payments API.
 *
 * Creates PaymentIntents (dynamic payment methods enabled, so card, Apple Pay
 * and Google Pay are offered automatically) and verifies webhook signatures.
 * The publishable key is used separately by the browser (Stripe.js + Payment
 * Element) to collect payment details against the intent's client secret.
 *
 * @see https://docs.stripe.com/payments/paymentintents/lifecycle
 */
final readonly class StripePaymentClient
{
    public function __construct(
        #[Autowire('%env(STRIPE_SECRET_KEY)%')]
        private string $secretKey,
        #[Autowire('%env(STRIPE_PUBLISHABLE_KEY)%')]
        private string $publishableKey,
        #[Autowire('%env(STRIPE_WEBHOOK_SECRET)%')]
        private string $webhookSecret,
    ) {
    }

    public function isConfigured(): bool
    {
        return $this->secretKey !== '' && $this->publishableKey !== '';
    }

    public function getPublishableKey(): string
    {
        return $this->publishableKey;
    }

    public function isWebhookConfigured(): bool
    {
        return $this->webhookSecret !== '';
    }

    /**
     * Create a PaymentIntent. Amount is in minor units (pence for GBP).
     *
     * We deliberately omit `payment_method_types` so Stripe uses dynamic payment
     * methods — card, Apple Pay and Google Pay are enabled/ranked from the
     * Dashboard with no code changes.
     */
    public function createPaymentIntent(int $amountMinor, string $currency, string $merchantOrderRef, ?string $email = null): StripePaymentIntentResult
    {
        $payload = [
            'amount' => $amountMinor,
            'currency' => strtolower($currency),
            'automatic_payment_methods' => [
                'enabled' => true,
            ],
            'metadata' => [
                'order_number' => $merchantOrderRef,
            ],
        ];

        if ($email !== null && $email !== '') {
            $payload['receipt_email'] = $email;
        }

        $intent = $this->client()->paymentIntents->create($payload);

        return new StripePaymentIntentResult(
            id: (string) $intent->id,
            clientSecret: (string) $intent->client_secret,
            status: (string) $intent->status,
        );
    }

    /**
     * Verify and decode a webhook payload against the signing secret.
     *
     * @throws \Stripe\Exception\SignatureVerificationException when invalid
     */
    public function constructWebhookEvent(string $rawBody, ?string $signatureHeader): Event
    {
        if (! $this->isWebhookConfigured()) {
            throw new RuntimeException('Stripe webhook is not configured (STRIPE_WEBHOOK_SECRET).');
        }

        return Webhook::constructEvent($rawBody, (string) $signatureHeader, $this->webhookSecret);
    }

    private function client(): StripeClient
    {
        if ($this->secretKey === '') {
            throw new RuntimeException('Stripe is not configured (STRIPE_SECRET_KEY).');
        }

        return new StripeClient($this->secretKey);
    }
}

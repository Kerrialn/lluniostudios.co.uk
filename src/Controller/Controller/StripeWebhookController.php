<?php

namespace App\Controller\Controller;

use App\Payment\StripePaymentClient;
use App\Repository\OrderRepository;
use App\Service\OrderProcessor;
use App\Service\QuotePayments;
use Psr\Log\LoggerInterface;
use Stripe\Exception\SignatureVerificationException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class StripeWebhookController extends AbstractController
{
    public function __construct(
        private readonly StripePaymentClient $stripeClient,
        private readonly OrderRepository $orderRepository,
        private readonly OrderProcessor $orderProcessor,
        private readonly LoggerInterface $logger,
        private readonly QuotePayments $quotePayments,
    ) {
    }

    #[Route(path: '/webhook/stripe', name: 'webhook_stripe', methods: ['POST'])]
    public function handle(Request $request): Response
    {
        $rawBody = $request->getContent();
        $signature = $request->headers->get('Stripe-Signature');

        try {
            $event = $this->stripeClient->constructWebhookEvent($rawBody, $signature);
        } catch (SignatureVerificationException) {
            $this->logger->warning('Stripe webhook signature verification failed');

            return new JsonResponse([
                'error' => 'invalid signature',
            ], Response::HTTP_UNAUTHORIZED);
        }

        $intent = $event->data->object ?? null;
        $paymentIntentId = is_object($intent) && $intent->id !== null ? (string) $intent->id : '';

        if ($paymentIntentId === '') {
            return new JsonResponse([
                'status' => 'ignored',
            ]);
        }

        // A quote's balance (Step 10b.4): paid in full once it succeeds; a
        // failed balance payment leaves the order as it is (the deposit stands).
        $balanceOrder = $this->orderRepository->findByBalancePaymentIntentId($paymentIntentId);
        if ($balanceOrder instanceof \App\Entity\Order) {
            if ($event->type === 'payment_intent.succeeded') {
                $this->quotePayments->finishBalance($balanceOrder);
            }

            return new JsonResponse([
                'status' => 'ok',
            ]);
        }

        $order = $this->orderRepository->findByStripePaymentIntentId($paymentIntentId);
        if (! $order instanceof \App\Entity\Order) {
            $this->logger->info('Stripe webhook for unknown payment intent ' . $paymentIntentId);

            return new JsonResponse([
                'status' => 'unknown order',
            ]);
        }

        match ($event->type) {
            'payment_intent.succeeded' => $this->orderProcessor->markPaid($order, $event->type),
            'payment_intent.payment_failed', 'payment_intent.canceled' => $this->orderProcessor->markFailed($order, $event->type),
            default => null,
        };

        return new JsonResponse([
            'status' => 'ok',
        ]);
    }
}

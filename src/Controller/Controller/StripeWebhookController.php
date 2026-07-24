<?php

namespace App\Controller\Controller;

use App\Payment\StripePaymentClient;
use App\Repository\OrderRepository;
use App\Service\OrderProcessor;
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
        $paymentIntentId = is_object($intent) && isset($intent->id) ? (string) $intent->id : '';

        if ($paymentIntentId === '') {
            return new JsonResponse([
                'status' => 'ignored',
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

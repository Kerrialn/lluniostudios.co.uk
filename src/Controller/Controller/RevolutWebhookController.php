<?php

namespace App\Controller\Controller;

use App\Payment\RevolutWebhookVerifier;
use App\Repository\OrderRepository;
use App\Service\OrderProcessor;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class RevolutWebhookController extends AbstractController
{
    public function __construct(
        private readonly RevolutWebhookVerifier $verifier,
        private readonly OrderRepository $orderRepository,
        private readonly OrderProcessor $orderProcessor,
        private readonly LoggerInterface $logger,
    ) {
    }

    #[Route(path: '/webhook/revolut', name: 'webhook_revolut', methods: ['POST'])]
    public function handle(Request $request): Response
    {
        $rawBody = $request->getContent();
        $timestamp = $request->headers->get('Revolut-Request-Timestamp');
        $signature = $request->headers->get('Revolut-Signature');

        if (! $this->verifier->verify($rawBody, $timestamp, $signature)) {
            $this->logger->warning('Revolut webhook signature verification failed');

            return new JsonResponse([
                'error' => 'invalid signature',
            ], Response::HTTP_UNAUTHORIZED);
        }

        /** @var array<string, mixed> $payload */
        $payload = json_decode($rawBody, true) ?? [];
        $event = (string) ($payload['event'] ?? '');
        $revolutOrderId = (string) ($payload['order_id'] ?? '');

        if ($revolutOrderId === '') {
            return new JsonResponse([
                'status' => 'ignored',
            ]);
        }

        $order = $this->orderRepository->findByRevolutOrderId($revolutOrderId);
        if (! $order instanceof \App\Entity\Order) {
            $this->logger->info('Revolut webhook for unknown order ' . $revolutOrderId);

            return new JsonResponse([
                'status' => 'unknown order',
            ]);
        }

        match ($event) {
            'ORDER_COMPLETED' => $this->orderProcessor->markPaid($order, $event),
            'ORDER_CANCELLED', 'ORDER_PAYMENT_DECLINED', 'ORDER_PAYMENT_FAILED' => $this->orderProcessor->markFailed($order, $event),
            default => null,
        };

        return new JsonResponse([
            'status' => 'ok',
        ]);
    }
}

<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Order;
use App\Enum\OrderStatus;
use App\Repository\OrderRepository;
use Carbon\CarbonImmutable;
use Psr\Log\LoggerInterface;
use Throwable;

final readonly class OrderProcessor
{
    public function __construct(
        private OrderRepository $orderRepository,
        private EmailService $emailService,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * Mark an order paid. Idempotent: a repeat call (e.g. webhook + return page)
     * is a no-op once the order is already PAID.
     */
    public function markPaid(Order $order, ?string $revolutState = null): void
    {
        if ($order->getStatus() === OrderStatus::PAID) {
            return;
        }

        $order->setStatus(OrderStatus::PAID);
        $order->setPaidAt(new CarbonImmutable());

        if ($revolutState !== null) {
            $order->setRevolutState($revolutState);
        }

        // Empty the buyer's cart now the order is settled.
        $cart = $order->getUser()?->getCart();
        if ($cart instanceof \App\Entity\Cart) {
            foreach ($cart->getCartItems()->toArray() as $cartItem) {
                $cart->removeCartItem($cartItem);
            }
        }

        $this->orderRepository->save($order, true);

        try {
            $this->emailService->sendOrderConfirmationEmail($order);
        } catch (Throwable $throwable) {
            // Never fail the payment flow because of a mail problem.
            $this->logger->error('Order confirmation email failed: ' . $throwable->getMessage());
        }
    }

    public function markFailed(Order $order, ?string $revolutState = null): void
    {
        if ($order->getStatus() === OrderStatus::PAID) {
            return;
        }

        $order->setStatus(OrderStatus::FAILED);
        if ($revolutState !== null) {
            $order->setRevolutState($revolutState);
        }

        $this->orderRepository->save($order, true);
    }
}

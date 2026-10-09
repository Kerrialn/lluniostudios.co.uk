<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Order;
use App\Enum\DesignRequestStatus;
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
     * Mark an order paid: Paid, or Deposit Paid when a quote's deposit was
     * taken (the balance makes it Paid, Order::markBalancePaid). Idempotent:
     * a repeat call (e.g. webhook + return page) is a no-op once paid.
     */
    public function markPaid(Order $order, ?string $revolutState = null): void
    {
        if ($order->isPaid()) {
            return;
        }

        $deposit = $order->getChargeNow() < $order->getTotal();
        $order->setStatus($deposit ? OrderStatus::DEPOSIT_PAID : OrderStatus::PAID);
        $order->setPaidAt(new CarbonImmutable());
        // The whole total, or a quote's deposit.
        $order->setAmountPaid($order->getChargeNow());
        // A quoted request is now paid, or its deposit is (Step 10b).
        $order->getDesignRequest()?->setStatus($deposit ? DesignRequestStatus::DEPOSIT_PAID : DesignRequestStatus::PAID);

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

        // The studio is told of every payment (Step 11.1).
        try {
            $this->emailService->sendOrderPaidNotificationEmail($order, $deposit ? 'deposit' : 'order', $order->getAmountPaid());
        } catch (Throwable $throwable) {
            $this->logger->error('Order paid notification failed: ' . $throwable->getMessage());
        }
    }

    public function markFailed(Order $order, ?string $revolutState = null): void
    {
        if ($order->isPaid()) {
            return;
        }

        $order->setStatus(OrderStatus::FAILED);
        if ($revolutState !== null) {
            $order->setRevolutState($revolutState);
        }

        $this->orderRepository->save($order, true);
    }
}

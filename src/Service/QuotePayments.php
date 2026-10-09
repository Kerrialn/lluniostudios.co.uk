<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Address;
use App\Entity\DesignRequest;
use App\Entity\Order;
use App\Entity\OrderItem;
use App\Entity\Quote;
use App\Enum\OrderStatus;
use App\Payment\StripePaymentClient;
use App\Payment\StripePaymentIntentResult;
use App\Repository\OrderRepository;
use App\Shipping\DeliveryArea;
use Carbon\CarbonImmutable;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Paying a sent quote (Step 10b.3): the request becomes an order with the
 * quote's lines (each design at its quoted price, the extra lines), its
 * delivery and total, linked to the account for the email given; the
 * payment taken is the deposit or the whole total (Order::chargeNow), by
 * card, Apple Pay or Google Pay. Once paid (OrderProcessor::markPaid) the
 * order records what has been paid, and the order and request become Paid
 * (or Deposit Paid).
 *
 * The balance (10b.4): "Request balance" in admin emails the customer to pay
 * the rest on the quote page (its own Stripe payment); once paid the order
 * is paid in full (the order and request become Paid) and a receipt is
 * emailed.
 */
final readonly class QuotePayments
{
    public function __construct(
        private OrderRepository $orders,
        private OrderAccounts $accounts,
        private StripePaymentClient $stripe,
        private OrderProcessor $processor,
        private Quotes $quotes,
        private LoggerInterface $logger,
        private EmailService $emails,
    ) {
    }

    /**
     * The quote's private link for an order made from a quote.
     */
    public function tokenFor(Order $order): ?string
    {
        $request = $order->getDesignRequest();

        return $request === null ? null : $this->quotes->find($request)?->getToken();
    }

    /**
     * Email the customer to pay the balance (may be sent again), with the
     * studio's personal note if one is given.
     */
    public function requestBalance(Order $order, ?string $note = null): void
    {
        $token = $this->tokenFor($order);
        if (! $order->canRequestBalance() || $token === null) {
            throw new \LogicException('No balance to request for ' . $order->getOrderNumber());
        }

        $this->emails->sendBalanceRequestEmail($order, $token, $note);
        $order->setBalanceRequestedAt(new CarbonImmutable());
        $this->orders->save($order, true);
    }

    /**
     * The balance's payment for the page to confirm: the earlier one while
     * it is still for the amount owed, otherwise a new one.
     */
    public function startBalance(Order $order, string $cardName): StripePaymentIntentResult
    {
        $owed = $order->getAmountOwed();
        if ($order->getBalancePaymentIntentId() !== null) {
            try {
                $intent = $this->stripe->retrievePaymentIntent($order->getBalancePaymentIntentId());
                if ($intent->amount === $owed && \in_array($intent->status, ['requires_payment_method', 'requires_confirmation', 'requires_action'], true)) {
                    return $intent;
                }

                if (\in_array($intent->status, ['requires_payment_method', 'requires_confirmation', 'requires_action'], true)) {
                    $this->stripe->cancelPaymentIntent($intent->id);
                }
            } catch (Throwable $throwable) {
                $this->logger->warning('Stripe balance payment lookup failed: ' . $throwable->getMessage());
            }
        }

        $intent = $this->stripe->createPaymentIntent($owed, $order->getCurrency(), $order->getOrderNumber() . '-balance', $order->getEmail(), cardOnly: true);
        $order->setBalancePaymentIntentId($intent->id);
        $this->orders->save($order, true);

        return $intent;
    }

    /**
     * After paying the balance (the page, or Stripe's webhook): Stripe is
     * asked whether it went through; if so the order is paid in full and a
     * receipt is emailed (once).
     */
    public function finishBalance(Order $order): void
    {
        if ($order->getBalancePaidAt() !== null || $order->getBalancePaymentIntentId() === null) {
            return;
        }

        try {
            $intent = $this->stripe->retrievePaymentIntent($order->getBalancePaymentIntentId());
        } catch (Throwable $throwable) {
            $this->logger->error('Stripe balance payment lookup failed: ' . $throwable->getMessage());

            return;
        }

        if ($intent->status !== 'succeeded') {
            return;
        }

        $balance = $order->getAmountOwed();
        $order->markBalancePaid(new CarbonImmutable());
        $this->orders->save($order, true);
        try {
            $this->emails->sendBalanceReceivedEmail($order, $balance);
        } catch (Throwable $throwable) {
            $this->logger->error('Balance receipt email failed: ' . $throwable->getMessage());
        }

        try {
            $this->emails->sendOrderPaidNotificationEmail($order, 'balance', $balance);
        } catch (Throwable $throwable) {
            $this->logger->error('Balance paid notification failed: ' . $throwable->getMessage());
        }
    }

    /**
     * The request's paid order, if any.
     */
    public function paidOrder(DesignRequest $request): ?Order
    {
        foreach ($this->ordersFor($request) as $order) {
            if (\in_array($order->getStatus(), [OrderStatus::DEPOSIT_PAID, OrderStatus::PAID, OrderStatus::FULFILLED], true)) {
                return $order;
            }
        }

        return null;
    }

    /**
     * The order and payment for the page to confirm: the earlier unpaid one
     * while the quote and amount are unchanged (with the latest details),
     * otherwise a new one (the earlier one is cancelled). An order paid in
     * the meantime (e.g. another tab) is finished and returned as paid.
     *
     * @param array<string, mixed> $data the Card Payment form's fields
     *
     * @return array{order: Order, intent: ?StripePaymentIntentResult}
     */
    public function start(Quote $quote, array $data, string $postcode): array
    {
        $request = $quote->getDesignRequest();
        $view = $this->quotes->view($quote);
        $pending = null;
        foreach ($this->ordersFor($request) as $order) {
            if ($order->getStatus() === OrderStatus::AWAITING_PAYMENT) {
                $pending = $order;
                break;
            }
        }

        if ($pending instanceof Order && $pending->getStripePaymentIntentId() !== null) {
            $intent = null;
            try {
                $intent = $this->stripe->retrievePaymentIntent($pending->getStripePaymentIntentId());
            } catch (Throwable $throwable) {
                $this->logger->error('Stripe payment lookup failed: ' . $throwable->getMessage());
            }

            if ($intent?->status === 'succeeded') {
                $this->processor->markPaid($pending);

                return [
                    'order' => $pending,
                    'intent' => null,
                ];
            }

            $unchanged = $pending->getTotal() === $view['total'] && $pending->getChargeNow() === $view['dueNow']
                && \in_array($intent?->status, ['requires_payment_method', 'requires_confirmation', 'requires_action'], true);
            if ($unchanged) {
                $this->applyDetails($pending, $data, $postcode);
                $this->orders->save($pending, true);

                return [
                    'order' => $pending,
                    'intent' => $intent,
                ];
            }

            $this->cancel($pending);
        }

        $order = $this->newOrder($quote, $view, $data, $postcode);
        $intent = $this->stripe->createPaymentIntent($order->getChargeNow(), $order->getCurrency(), $order->getOrderNumber(), $order->getEmail(), cardOnly: true);
        $order->setStripePaymentIntentId($intent->id);
        $this->orders->save($order, true);

        return [
            'order' => $order,
            'intent' => $intent,
        ];
    }

    /**
     * After paying: Stripe is asked whether the payment went through.
     */
    public function finish(Order $order): void
    {
        if ($order->isPaid() || $order->getStripePaymentIntentId() === null) {
            return;
        }

        try {
            if ($this->stripe->retrievePaymentIntent($order->getStripePaymentIntentId())->status === 'succeeded') {
                $this->processor->markPaid($order);
            }
        } catch (Throwable $throwable) {
            $this->logger->error('Stripe payment lookup failed: ' . $throwable->getMessage());
        }
    }

    /**
     * @return list<Order>
     */
    public function ordersFor(DesignRequest $request): array
    {
        return $this->orders->findBy([
            'designRequest' => $request,
        ], [
            'createdAt' => 'DESC',
        ]);
    }

    /**
     * @param array<string, mixed> $view as Quotes::view()
     * @param array<string, mixed> $data
     */
    private function newOrder(Quote $quote, array $view, array $data, string $postcode): Order
    {
        $order = new Order();
        $order->setDesignRequest($quote->getDesignRequest());
        foreach ($view['lamps'] as $lamp) {
            $item = $lamp['item'];
            $options = [];
            foreach ($item?->getOptionsSnapshot() ?? [] as $option) {
                $options[] = [
                    'option' => $option['option'],
                    'value' => $option['value'],
                    'lamp' => (int) ($option['lamp'] ?? 0),
                ];
            }

            $order->addItem(new OrderItem(
                product: $item?->getProduct(),
                productTitle: $item?->getProductTitle() ?? $lamp['label'],
                unitPrice: $lamp['unitPrice'],
                quantity: $lamp['quantity'],
                optionsSnapshot: $options,
            ));
        }

        foreach ($view['extras'] as $extra) {
            $order->addItem(new OrderItem(product: null, productTitle: $extra['label'], unitPrice: $extra['total'], quantity: 1));
        }

        $order->setSubtotal($view['total'] - $view['deliveryPrice']);
        $order->setShippingCost($view['deliveryPrice']);
        $order->setShippingServiceName($view['deliveryLabel']);
        $order->setTotal($view['total']);
        $order->setChargeNow($view['dueNow']);
        $order->setStatus(OrderStatus::AWAITING_PAYMENT);
        $this->applyDetails($order, $data, $postcode);

        return $order;
    }

    /**
     * The account for the email, the delivery address, contact phone, notes
     * and billing address (as the Card Payment page).
     *
     * @param array<string, mixed> $data
     */
    private function applyDetails(Order $order, array $data, string $postcode): void
    {
        $value = static fn (string $key): ?string => ($text = trim((string) ($data[$key] ?? ''))) === '' ? null : $text;
        $email = strtolower((string) $value('email'));
        $request = $order->getDesignRequest();
        // The buyer is the request's name (Greeting name), never a gift recipient's.
        $buyer = $request?->getFullName() ?? $value('cardName');
        $account = $this->accounts->accountFor($email, $buyer);
        $order->setUser($account);
        $order->setEmail($email);
        $order->setCustomerName($buyer);
        // The request's gift (Gift options): its date and choice; the message and recipient's mobile as confirmed on the page.
        $order->setGiftArriveBy($request?->isGift() ? $request->getGiftNeededBy() : null);
        $order->setGiftDetails($request?->getGiftSendTo(), $value('giftMessage'), $value('recipientPhone'));

        $delivery = new Address();
        $delivery->setRecipientName($value('name'));
        $delivery->setCompany($value('company'));
        $delivery->setLine1($value('line1'));
        $delivery->setLine2($value('line2'));
        $delivery->setCity($value('town'));
        $delivery->setCounty($value('county'));
        $delivery->setPostcode($postcode);
        $delivery->setCountry($order->getDesignRequest()?->getCountry() ?? 'GB');
        // DPD's delivery phone: a direct gift's recipient's mobile when given.
        $delivery->setPhone($order->isDirectGift() && $value('recipientPhone') !== null ? $value('recipientPhone') : $value('phone'));
        $delivery->setOwner($account);
        $order->setShippingAddress($delivery);
        $order->setContactPhone($value('phone'));
        $order->setDeliveryNotes($value('notes'));

        $billing = null;
        if (! ($data['billingSame'] ?? true)) {
            $billing = new Address();
            $billing->setRecipientName($value('billingName'));
            $billing->setLine1($value('billingLine1'));
            $billing->setLine2($value('billingLine2'));
            $billing->setCity($value('billingTown'));
            $billing->setCounty($value('billingCounty'));
            $billing->setPostcode(DeliveryArea::normalise((string) $value('billingPostcode')) ?? $value('billingPostcode'));
            $billing->setCountry('GB');
        }

        $order->setBillingAddress($billing);
    }

    private function cancel(Order $order): void
    {
        try {
            if ($order->getStripePaymentIntentId() !== null) {
                $this->stripe->cancelPaymentIntent($order->getStripePaymentIntentId());
            }
        } catch (Throwable $throwable) {
            $this->logger->warning('Cancelling a Stripe payment failed: ' . $throwable->getMessage());
        }

        $order->setStatus(OrderStatus::CANCELLED);
        $this->orders->save($order, true);
    }
}

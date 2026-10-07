<?php

namespace App\Controller\Controller;

use App\Catalogue\GiftDate;
use App\Catalogue\LumenFormaConfigurator;
use App\Entity\Address;
use App\Entity\Cart;
use App\Entity\Order;
use App\Entity\User;
use App\Enum\OrderStatus;
use App\Form\Form\BasketProceedForm;
use App\Form\Form\CardPaymentForm;
use App\Payment\RevolutMerchantClient;
use App\Payment\StripePaymentClient;
use App\Repository\OrderRepository;
use App\Service\BasketRules;
use App\Service\CartResolver;
use App\Service\OrderFactory;
use App\Service\ShippingCalculator;
use App\Shipping\DeliveryArea;
use App\Shipping\ShippingQuote;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Throwable;

/**
 * Checkout (design merge, Step 10). The Card Payment page (10.2) collects
 * contact, delivery and billing details on one page, never asking anyone to
 * sign in, and shows the DPD price for the postcode. Postcodes outside the UK
 * mainland go through "Reserve Your Design" instead, with the basket and the
 * address carried over. Until 10.3 adds the card box to that page, "Pay"
 * saves the order and continues to the separate payment page.
 */
class CheckoutController extends AbstractController
{
    /**
     * Order numbers started in this visitor's session (they may view and pay
     * these without signing in).
     */
    private const SESSION_ORDERS = 'checkout_orders';

    public function __construct(
        private readonly CartResolver $cartResolver,
        private readonly BasketRules $basketRules,
        private readonly OrderRepository $orderRepository,
        private readonly ShippingCalculator $shippingCalculator,
        private readonly OrderFactory $orderFactory,
        private readonly GiftDate $giftDate,
        private readonly RevolutMerchantClient $revolutClient,
        private readonly StripePaymentClient $stripeClient,
        private readonly LoggerInterface $logger,
        #[Autowire('%env(REVOLUT_PUBLIC_KEY)%')]
        private readonly string $revolutPublicKey,
        #[Autowire('%env(REVOLUT_ENV)%')]
        private readonly string $revolutEnv,
        #[Autowire('%env(PAYMENT_PROVIDER)%')]
        private readonly string $paymentProvider,
    ) {
    }

    private function usesStripe(): bool
    {
        return strtolower($this->paymentProvider) !== 'revolut';
    }

    /**
     * The Card Payment page.
     */
    #[Route(path: '/checkout', name: 'checkout', methods: ['GET', 'POST'])]
    public function checkout(Request $request): Response
    {
        $cart = $this->cartResolver->getCart();
        if ($cart->isEmpty() || ! $this->basketRules->canCheckout($cart)) {
            // Safety net: 3+ lamps, Request sizes or Custom options never reach payment.
            return $this->redirectToRoute('cart');
        }

        if ($cart->isGift() && $cart->getGiftArriveBy() < $this->giftDate->earliest()->startOfDay()) {
            // The chosen date has become too soon since it was picked.
            $this->addFlash('error', BasketProceedForm::TOO_EARLY);

            return $this->redirectToRoute('cart');
        }

        $user = $this->getUser();
        $form = $this->createForm(CardPaymentForm::class, [
            'email' => $user instanceof User ? $user->getEmail() : null,
            'billingSame' => true,
        ], [
            'gift' => $cart->isGift(),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            /** @var array<string, mixed> $data */
            $data = $form->getData();
            $postcode = DeliveryArea::normalise((string) ($data['postcode'] ?? ''));
            if ($postcode !== null && ! DeliveryArea::isMainland($postcode)) {
                return $this->reserveInstead($request, $data, $cart);
            }

            if ($form->isValid() && $postcode !== null) {
                return $this->placeOrder($request, $cart, $data, $postcode);
            }
        }

        return $this->render('checkout/index.html.twig', [
            'form' => $form,
            'cart' => $cart,
            'summary' => $this->summary($cart),
            'gift' => $cart->isGift(),
            'giftArriveBy' => $cart->getGiftArriveBy(),
            'delivery' => $this->deliveryFor($cart, (string) $form->get('postcode')->getData()),
            'requiredMissing' => $this->requiredMissing($form),
        ], new Response(status: $form->isSubmitted() ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK));
    }

    /**
     * The delivery line, total and button for a postcode, as the customer
     * types it on the Card Payment page.
     */
    #[Route(path: '/checkout/delivery', name: 'checkout_delivery', methods: ['GET'])]
    public function delivery(Request $request): JsonResponse
    {
        $cart = $this->cartResolver->peekCart();
        if (! $cart instanceof Cart || $cart->isEmpty()) {
            return new JsonResponse(null, Response::HTTP_NOT_FOUND);
        }

        return new JsonResponse($this->deliveryFor($cart, (string) $request->query->get('postcode', '')));
    }

    /**
     * What the summary and button show for this postcode: "Calculated from
     * postcode" until it is a full UK postcode, then the DPD price (UK
     * mainland) or "Reserve Your Design" (elsewhere).
     *
     * @return array{state: string, delivery: string, total: string, button: string}
     */
    private function deliveryFor(Cart $cart, string $postcode): array
    {
        $subtotal = $cart->getTotal();
        $normalised = DeliveryArea::normalise($postcode);
        if ($normalised !== null && ! DeliveryArea::isMainland($normalised)) {
            return [
                'state' => 'outside',
                'delivery' => 'Quoted individually',
                'total' => self::money($subtotal),
                'button' => 'Reserve Your Design',
            ];
        }

        $quote = $normalised === null ? null : $this->quote($cart, $normalised);
        $total = $subtotal + ($quote->priceInPence ?? 0);

        return [
            'state' => $quote instanceof ShippingQuote ? 'mainland' : 'unknown',
            'delivery' => $quote instanceof ShippingQuote ? self::money($quote->priceInPence) : 'Calculated from postcode',
            'total' => self::money($total),
            'button' => 'Pay ' . self::money($total),
        ];
    }

    private function quote(Cart $cart, string $postcode): ?ShippingQuote
    {
        $destination = new Address();
        $destination->setPostcode($postcode);
        $destination->setCountry('GB');

        return $this->shippingCalculator->quotesForCart($cart, $destination)[0] ?? null;
    }

    /**
     * Save the order with every detail, then continue to payment.
     *
     * @param array<string, mixed> $data
     */
    private function placeOrder(Request $request, Cart $cart, array $data, string $postcode): Response
    {
        $value = static fn (string $key): ?string => ($text = trim((string) ($data[$key] ?? ''))) === '' ? null : $text;
        $quote = $this->quote($cart, $postcode);
        if (! $quote instanceof ShippingQuote) {
            $this->addFlash('error', 'We couldn\'t work out delivery for this order. Please try again, or contact us.');

            return $this->redirectToRoute('checkout');
        }

        $user = $this->getUser();
        $delivery = new Address();
        $delivery->setRecipientName($value('name'));
        $delivery->setCompany($value('company'));
        $delivery->setLine1($value('line1'));
        $delivery->setLine2($value('line2'));
        $delivery->setCity($value('town'));
        $delivery->setCounty($value('county'));
        $delivery->setPostcode($postcode);
        $delivery->setCountry('GB');
        // DPD texts the delivery's phone: the recipient's for a gift.
        $delivery->setPhone($cart->isGift() ? $value('recipientMobile') : $value('phone'));
        $delivery->setOwner($user instanceof User ? $user : null);

        $order = $this->orderFactory->createFromCart($cart, $user instanceof User ? $user : null, strtolower((string) $value('email')), $quote, $delivery);
        $order->setContactPhone($value('phone'));
        $order->setDeliveryNotes($value('notes'));
        if (! ($data['billingSame'] ?? true)) {
            $billing = new Address();
            $billing->setRecipientName($value('billingName'));
            $billing->setLine1($value('billingLine1'));
            $billing->setLine2($value('billingLine2'));
            $billing->setCity($value('billingTown'));
            $billing->setCounty($value('billingCounty'));
            $billing->setPostcode(DeliveryArea::normalise((string) $value('billingPostcode')) ?? $value('billingPostcode'));
            $billing->setCountry('GB');
            $order->setBillingAddress($billing);
        }

        $this->orderRepository->save($order, true);

        if ($this->usesStripe()) {
            $this->startStripePayment($request, $order);
        } else {
            $this->startRevolutPayment($request, $order);
        }

        $order->setStatus(OrderStatus::AWAITING_PAYMENT);
        $this->orderRepository->save($order, true);
        $request->getSession()->set(self::SESSION_ORDERS, array_values(array_unique([...$this->sessionOrders($request), $order->getOrderNumber()])));

        return $this->redirectToRoute('checkout_pay', [
            'orderNumber' => $order->getOrderNumber(),
        ]);
    }

    /**
     * A postcode outside the UK mainland: the basket goes to "Reserve Your
     * Design" (no payment), with the details typed so far filled in there.
     *
     * @param array<string, mixed> $data
     */
    private function reserveInstead(Request $request, array $data, Cart $cart): Response
    {
        $value = static fn (string $key): string => trim((string) ($data[$key] ?? ''));
        $address = array_filter([
            $value('company'),
            $value('line1'),
            $value('line2'),
            $value('town'),
            $value('county'),
            DeliveryArea::normalise($value('postcode')) ?? $value('postcode'),
        ], static fn (string $line): bool => $line !== '');
        $notes = array_filter([
            $cart->isGift() && $cart->getGiftArriveBy() !== null ? 'Gift · Arrive by ' . $cart->getGiftArriveBy()->format('j F Y') . ($value('recipientMobile') !== '' ? ' · Recipient\'s mobile ' . $value('recipientMobile') : '') : '',
            $value('notes'),
        ], static fn (string $line): bool => $line !== '');

        $request->getSession()->set(ReserveController::PREFILL_KEY, [
            'fullName' => $value('name'),
            'email' => $value('email'),
            'phone' => $value('phone'),
            'deliveryAddress' => implode("\n", $address),
            'accessNotes' => implode("\n", $notes),
        ]);

        return $this->redirectToRoute('basket_request', [
            'basket' => 1,
        ]);
    }

    /**
     * The order summary (Site v4 prototype): per line "Lumen Forma", its
     * price, size, quantity, configuration, then each lamp's choices.
     *
     * @return list<array{price: string, head: list<array{string, string}>, lamps: list<array{label: ?string, rows: list<array{string, string}>}>}>
     */
    private function summary(Cart $cart): array
    {
        $lines = [];
        foreach ($cart->getCartItems() as $item) {
            $product = $item->getProduct();
            if ($product === null) {
                continue;
            }

            $head = [['Size', LumenFormaConfigurator::sizeName($product)], ['Quantity', (string) $item->getQuantity()]];
            if ($item->getQuantity() >= 2) {
                $head[] = ['Configuration', $item->isCustomisedIndividually() ? 'Customise Individually' : 'Customise All'];
            }

            $lines[] = [
                'price' => self::money((int) $item->getUnitPrice() * (int) $item->getQuantity()),
                'head' => $head,
                'lamps' => array_map(static fn (array $group): array => [
                    'label' => $group['label'],
                    'rows' => array_map(static fn ($option): array => [$option->getProductOption()->getName(), (string) $option->getProductOptionValue()->getValue()], $group['options']),
                ], $item->getLampConfigurations()),
            ];
        }

        return $lines;
    }

    /**
     * Whether any field is empty that must be filled (the prototype's one
     * message, "Please complete the required fields to continue.").
     *
     * @param FormInterface<mixed> $form
     */
    private function requiredMissing(FormInterface $form): bool
    {
        foreach ($form->getErrors(true) as $error) {
            if ($error->getMessage() === CardPaymentForm::REQUIRED_MESSAGE) {
                return true;
            }
        }

        return false;
    }

    /**
     * "£799", or "£12.95" when there are pence.
     */
    public static function money(int $pence): string
    {
        return '£' . number_format($pence / 100, $pence % 100 === 0 ? 0 : 2);
    }

    /**
     * @return list<string>
     */
    private function sessionOrders(Request $request): array
    {
        $orders = $request->getSession()->get(self::SESSION_ORDERS, []);

        return \is_array($orders) ? array_values(array_filter($orders, 'is_string')) : [];
    }

    /**
     * The order, if this visitor may see it: started in this session, or
     * belonging to the signed-in account.
     */
    private function visibleOrder(Request $request, string $orderNumber): ?Order
    {
        $order = $this->orderRepository->findByOrderNumber($orderNumber);
        if (! $order instanceof Order) {
            return null;
        }

        $user = $this->getUser();
        if (\in_array($orderNumber, $this->sessionOrders($request), true) || ($user instanceof User && $order->getUser() === $user)) {
            return $order;
        }

        return null;
    }

    #[Route(path: '/checkout/pay/{orderNumber}', name: 'checkout_pay')]
    public function pay(Request $request, string $orderNumber): Response
    {
        $order = $this->visibleOrder($request, $orderNumber);
        if (! $order instanceof Order) {
            throw $this->createNotFoundException();
        }

        if ($order->getStatus() === OrderStatus::PAID) {
            return $this->redirectToRoute('checkout_complete', [
                'orderNumber' => $orderNumber,
            ]);
        }

        $completeUrl = $this->generateUrl('checkout_complete', [
            'orderNumber' => $orderNumber,
        ]);

        if ($this->usesStripe()) {
            return $this->render('checkout/pay.html.twig', [
                'order' => $order,
                'provider' => 'stripe',
                'stripeClientSecret' => $request->getSession()->get($this->stripeSecretSessionKey($orderNumber)),
                'stripePublishableKey' => $this->stripeClient->getPublishableKey(),
                'completeUrl' => $completeUrl,
            ]);
        }

        return $this->render('checkout/pay.html.twig', [
            'order' => $order,
            'provider' => 'revolut',
            'revolutToken' => $request->getSession()->get($this->tokenSessionKey($orderNumber)),
            'revolutPublicKey' => $this->revolutPublicKey,
            'revolutMode' => $this->revolutEnv === 'prod' ? 'prod' : 'sandbox',
            'completeUrl' => $completeUrl,
        ]);
    }

    #[Route(path: '/checkout/complete/{orderNumber}', name: 'checkout_complete')]
    public function complete(Request $request, string $orderNumber): Response
    {
        $order = $this->visibleOrder($request, $orderNumber);
        if (! $order instanceof Order) {
            throw $this->createNotFoundException();
        }

        return $this->render('checkout/complete.html.twig', [
            'order' => $order,
        ]);
    }

    private function startStripePayment(Request $request, Order $order): void
    {
        if (! $this->stripeClient->isConfigured()) {
            return;
        }

        try {
            $intent = $this->stripeClient->createPaymentIntent(
                $order->getTotal(),
                $order->getCurrency(),
                $order->getOrderNumber(),
                $order->getEmail(),
            );
            $order->setStripePaymentIntentId($intent->id);
            $request->getSession()->set($this->stripeSecretSessionKey($order->getOrderNumber()), $intent->clientSecret);
        } catch (Throwable $throwable) {
            $this->logger->error('Stripe payment intent creation failed: ' . $throwable->getMessage());
            $this->addFlash('error', 'Could not start payment. Please try again.');
        }
    }

    private function startRevolutPayment(Request $request, Order $order): void
    {
        if (! $this->revolutClient->isConfigured()) {
            return;
        }

        try {
            $revolutOrder = $this->revolutClient->createOrder(
                $order->getTotal(),
                $order->getCurrency(),
                $order->getOrderNumber(),
            );
            $order->setRevolutOrderId($revolutOrder->id);
            $order->setRevolutState($revolutOrder->state);
            $request->getSession()->set($this->tokenSessionKey($order->getOrderNumber()), $revolutOrder->token);
        } catch (Throwable $throwable) {
            $this->logger->error('Revolut order creation failed: ' . $throwable->getMessage());
            $this->addFlash('error', 'Could not start payment. Please try again.');
        }
    }

    private function tokenSessionKey(string $orderNumber): string
    {
        return 'revolut_token_' . $orderNumber;
    }

    private function stripeSecretSessionKey(string $orderNumber): string
    {
        return 'stripe_client_secret_' . $orderNumber;
    }
}

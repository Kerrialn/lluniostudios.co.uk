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
use App\Form\Form\ReserveForm;
use App\Payment\RevolutMerchantClient;
use App\Payment\StripePaymentClient;
use App\Repository\OrderRepository;
use App\Service\BasketRules;
use App\Service\CartResolver;
use App\Service\OrderAccounts;
use App\Service\OrderFactory;
use App\Service\OrderProcessor;
use App\Service\ShippingCalculator;
use App\Shipping\DeliveryArea;
use App\Shipping\ShippingQuote;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Throwable;

/**
 * Checkout (design merge, Step 10). The Card Payment page (10.2) collects
 * contact, delivery and billing details on one page, never asking anyone to
 * sign in, and shows the DPD price for the postcode. Postcodes outside the UK
 * mainland go through "Reserve Your Design" instead, with the basket and the
 * address carried over. Paying (10.3): the page sends the form here first
 * (JSON); once it is complete the order is saved (linked to the account for
 * its email, created quietly if new), a Stripe payment is created for it and
 * the page confirms it with the card typed into Stripe's own boxes. A retry
 * after a declined card reuses the same order while the basket is unchanged.
 */
class CheckoutController extends AbstractController
{
    /**
     * Order numbers started in this visitor's session (they may view and pay
     * these without signing in).
     */
    private const SESSION_ORDERS = 'checkout_orders';

    /**
     * The order being paid for: [order number, basket signature], so a retry
     * (e.g. after a declined card) reuses it while the basket is unchanged.
     */
    private const SESSION_PENDING = 'checkout_pending';

    public const NO_SCRIPT_MESSAGE = 'Please turn on JavaScript in your browser to pay by card.';

    public function __construct(
        private readonly CartResolver $cartResolver,
        private readonly BasketRules $basketRules,
        private readonly OrderRepository $orderRepository,
        private readonly ShippingCalculator $shippingCalculator,
        private readonly OrderFactory $orderFactory,
        private readonly OrderAccounts $orderAccounts,
        private readonly OrderProcessor $orderProcessor,
        private readonly EntityManagerInterface $entityManager,
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
            // A gift sent directly: the delivery address is the recipient's, so the buyer enters their own billing address (decided 9 October 2026).
            'billingSame' => ! $cart->isDirectGift(),
        ]);
        $form->handleRequest($request);

        $asJson = str_contains((string) $request->headers->get('Accept'), 'application/json');
        if ($form->isSubmitted()) {
            /** @var array<string, mixed> $data */
            $data = $form->getData();
            $postcode = DeliveryArea::normalise((string) ($data['postcode'] ?? ''));
            if ($postcode !== null && ! DeliveryArea::isMainland($postcode)) {
                $response = $this->reserveInstead($request, $data, $cart);

                return $asJson ? new JsonResponse([
                    'status' => 'redirect',
                    'url' => $response->getTargetUrl(),
                ]) : $response;
            }

            $valid = $form->isValid() && $postcode !== null;
            if ($asJson) {
                // The page's "Pay": complete details and card first, then the payment.
                $cardReady = $request->request->getBoolean('card_ready');
                if (! $valid || ! $cardReady) {
                    return new JsonResponse([
                        'status' => 'invalid',
                        'errors' => $this->fieldErrors($form),
                        'requiredMissing' => $this->requiredMissing($form) || ! $cardReady,
                    ], Response::HTTP_UNPROCESSABLE_ENTITY);
                }

                return $this->startCardPayment($request, $cart, $data, (string) $postcode);
            }

            if ($valid) {
                if (! $this->usesStripe()) {
                    return $this->payWithRevolut($request, $this->createOrder($request, $cart, $data, (string) $postcode));
                }

                // Paying by card needs the page's script (Stripe's card boxes).
                $form->addError(new FormError(self::NO_SCRIPT_MESSAGE));
            }
        }

        return $this->render('checkout/index.html.twig', [
            'form' => $form,
            'cart' => $cart,
            'summary' => $this->summary($cart),
            'gift' => $cart->isGift(),
            'giftArriveBy' => $cart->getGiftArriveBy(),
            'giftSendTo' => $cart->isGift() ? $cart->getGiftSendTo() : null,
            'directGift' => $cart->isDirectGift(),
            'delivery' => $this->deliveryFor($cart, (string) $form->get('postcode')->getData()),
            'requiredMissing' => $this->requiredMissing($form),
            'formErrors' => array_map(static fn ($error): string => $error->getMessage(), iterator_to_array($form->getErrors())),
            'stripeKey' => $this->usesStripe() && $this->stripeClient->isConfigured() ? $this->stripeClient->getPublishableKey() : '',
            'subtotalPence' => $cart->getTotal(),
        ], new Response(status: $form->isSubmitted() ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK));
    }

    /**
     * Apple Pay / Google Pay (10.4): the wallet's details once the customer
     * has approved, checked like the card form's. Outside the UK mainland the
     * basket goes to Reserve Your Design instead (no payment); otherwise the
     * order is saved as for a card, and the page confirms the payment with
     * the wallet.
     */
    #[Route(path: '/checkout/express', name: 'checkout_express', methods: ['POST'])]
    public function express(Request $request): JsonResponse
    {
        // Only this site's own page may send here.
        if (! \in_array($request->headers->get('Sec-Fetch-Site'), [null, 'same-origin'], true)) {
            return new JsonResponse(null, Response::HTTP_FORBIDDEN);
        }

        $cart = $this->cartResolver->getCart();
        if ($cart->isEmpty() || ! $this->basketRules->canCheckout($cart)) {
            return new JsonResponse([
                'status' => 'error',
                'message' => 'Please pay by card using the form below.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        /** @var array<string, mixed> $wallet */
        $wallet = json_decode($request->getContent(), true) ?: [];
        $text = static fn (mixed $value): string => \is_scalar($value) ? trim((string) $value) : '';
        $shipping = \is_array($wallet['shipping'] ?? null) ? $wallet['shipping'] : [];
        $billing = \is_array($wallet['billing'] ?? null) ? $wallet['billing'] : [];
        $data = [
            'email' => $text($wallet['email'] ?? ''),
            'phone' => $text($wallet['phone'] ?? ''),
            'name' => $text($shipping['name'] ?? ''),
            'line1' => $text($shipping['line1'] ?? ''),
            'line2' => $text($shipping['line2'] ?? ''),
            'town' => $text($shipping['city'] ?? ''),
            'county' => $text($shipping['state'] ?? ''),
            'postcode' => $text($shipping['postal_code'] ?? ''),
            // The name on card is the payer's; for a direct gift the delivery name is the recipient's, so never used for it.
            'cardName' => $text($billing['name'] ?? '') ?: ($cart->isDirectGift() ? '' : $text($shipping['name'] ?? '')),
            'payerName' => $text($billing['name'] ?? ''),
            'giftMessage' => $text($wallet['giftMessage'] ?? ''),
            'recipientPhone' => $text($wallet['recipientPhone'] ?? ''),
            // The wallet's billing address, unless it is the delivery address (or not given).
            'billingSame' => \in_array($text($billing['postal_code'] ?? ''), ['', $text($shipping['postal_code'] ?? '')], true),
            'billingName' => $text($billing['name'] ?? ''),
            'billingLine1' => $text($billing['line1'] ?? ''),
            'billingLine2' => $text($billing['line2'] ?? ''),
            'billingTown' => $text($billing['city'] ?? ''),
            'billingCounty' => $text($billing['state'] ?? ''),
            'billingPostcode' => $text($billing['postal_code'] ?? ''),
        ];

        $postcode = DeliveryArea::normalise((string) $data['postcode']);
        if ($postcode !== null && ! DeliveryArea::isMainland($postcode)) {
            return new JsonResponse([
                'status' => 'redirect',
                'url' => $this->reserveInstead($request, $data, $cart)->getTargetUrl(),
            ]);
        }

        $form = $this->createForm(CardPaymentForm::class, null, [
            'csrf_protection' => false,
        ]);
        $submitted = $data;
        unset($submitted['payerName']);
        if ($submitted['cardName'] === '') {
            // A wallet that shares no payer name: the card form's name is not needed to pay.
            $submitted['cardName'] = '-';
        }
        $form->submit(array_map(static fn (mixed $value): mixed => \is_bool($value) ? ($value ? '1' : null) : $value, $submitted));
        if (! $form->isValid() || $postcode === null) {
            // E.g. an address line longer than the courier allows: the card form explains.
            return new JsonResponse([
                'status' => 'error',
                'message' => 'We couldn\'t use the details from your wallet. Please pay by card using the form below.',
                'errors' => $this->fieldErrors($form),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return $this->startCardPayment($request, $cart, $data, $postcode);
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

        return new JsonResponse($this->deliveryFor($cart, (string) $request->query->get('postcode', ''), $request->query->getBoolean('partial')));
    }

    /**
     * What the summary and button show for this postcode: "Calculated from
     * postcode" until it is a full UK postcode, then the DPD price (UK
     * mainland) or "Reserve Your Design" (elsewhere).
     *
     * @return array{state: string, delivery: string, total: string, button: string, subtotalPence: int, deliveryPence: ?int}
     */
    private function deliveryFor(Cart $cart, string $postcode, bool $partial = false): array
    {
        $subtotal = $cart->getTotal();
        // Apple Pay and Google Pay share only the first half of the postcode until the customer pays.
        $place = $partial ? DeliveryArea::outward($postcode) : DeliveryArea::normalise($postcode);
        $mainland = $place !== null && ($partial ? DeliveryArea::isMainlandOutward($place) : DeliveryArea::isMainland($place));
        if ($place !== null && ! $mainland) {
            return [
                'state' => 'outside',
                'delivery' => 'Quoted individually',
                'total' => self::money($subtotal),
                'button' => 'Reserve Your Design',
                'subtotalPence' => $subtotal,
                'deliveryPence' => null,
            ];
        }

        $quote = $place === null ? null : $this->quote($cart, $place);
        $total = $subtotal + ($quote->priceInPence ?? 0);

        return [
            'state' => $quote instanceof ShippingQuote ? 'mainland' : 'unknown',
            'delivery' => $quote instanceof ShippingQuote ? self::money($quote->priceInPence) : 'Calculated from postcode',
            'total' => self::money($total),
            'button' => 'Pay ' . self::money($total),
            'subtotalPence' => $subtotal,
            'deliveryPence' => $quote?->priceInPence,
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
     * Save the order with every detail, linked to the account for its email.
     *
     * @param array<string, mixed> $data
     */
    private function createOrder(Request $request, Cart $cart, array $data, string $postcode): Order
    {
        $quote = $this->quote($cart, $postcode);
        if (! $quote instanceof ShippingQuote) {
            throw new \RuntimeException('No delivery quote for ' . $postcode);
        }

        $value = static fn (string $key): ?string => ($text = trim((string) ($data[$key] ?? ''))) === '' ? null : $text;
        $email = strtolower((string) $value('email'));
        $account = $this->orderAccounts->accountFor($email, self::buyerName($data));

        $order = $this->orderFactory->createFromCart($cart, $account, $email, $quote, $this->deliveryAddress($cart, $data, $postcode, $account));
        $this->applyDetails($order, $data, $cart);
        $order->setStatus(OrderStatus::AWAITING_PAYMENT);
        $this->orderRepository->save($order, true);
        $request->getSession()->set(self::SESSION_ORDERS, array_values(array_unique([...$this->sessionOrders($request), $order->getOrderNumber()])));

        return $order;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function deliveryAddress(Cart $cart, array $data, string $postcode, User $owner): Address
    {
        $value = static fn (string $key): ?string => ($text = trim((string) ($data[$key] ?? ''))) === '' ? null : $text;
        $delivery = new Address();
        $delivery->setRecipientName($value('name'));
        $delivery->setCompany($value('company'));
        $delivery->setLine1($value('line1'));
        $delivery->setLine2($value('line2'));
        $delivery->setCity($value('town'));
        $delivery->setCounty($value('county'));
        $delivery->setPostcode($postcode);
        $delivery->setCountry('GB');
        // DPD's delivery phone: a direct gift's recipient's mobile when given, otherwise the customer's own.
        $delivery->setPhone($cart->isDirectGift() && $value('recipientPhone') !== null ? $value('recipientPhone') : $value('phone'));
        $delivery->setOwner($owner);

        return $delivery;
    }

    /**
     * The contact phone, delivery instructions, billing address, the buyer's
     * name and the gift's details.
     *
     * @param array<string, mixed> $data
     */
    private function applyDetails(Order $order, array $data, Cart $cart): void
    {
        $value = static fn (string $key): ?string => ($text = trim((string) ($data[$key] ?? ''))) === '' ? null : $text;
        $order->setContactPhone($value('phone'));
        $order->setDeliveryNotes($value('notes'));
        $order->setCustomerName(self::buyerName($data));
        $order->setGiftDetails($cart->isGift() ? $cart->getGiftSendTo() : null, $value('giftMessage'), $value('recipientPhone'));
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

    /**
     * The order and Stripe payment for the page to confirm with the card: the
     * one from a previous attempt while the basket is unchanged, otherwise a
     * new one (the previous one is cancelled).
     *
     * @param array<string, mixed> $data
     */
    private function startCardPayment(Request $request, Cart $cart, array $data, string $postcode): JsonResponse
    {
        $signature = $this->basketSignature($cart, $postcode);
        $pending = $request->getSession()->get(self::SESSION_PENDING);
        $order = \is_array($pending) ? $this->visibleOrder($request, (string) ($pending[0] ?? '')) : null;
        $intent = null;

        if ($order instanceof Order && $order->getStripePaymentIntentId() !== null) {
            try {
                $intent = $this->stripeClient->retrievePaymentIntent($order->getStripePaymentIntentId());
            } catch (Throwable $throwable) {
                $this->logger->error('Stripe payment lookup failed: ' . $throwable->getMessage());
            }

            if ($intent?->status === 'succeeded') {
                // Paid already (e.g. a second tab): finish it.
                $this->orderProcessor->markPaid($order);

                return new JsonResponse([
                    'status' => 'redirect',
                    'url' => $this->generateUrl('checkout_complete', [
                        'orderNumber' => $order->getOrderNumber(),
                    ]),
                ]);
            }

            $reusable = $order->getStatus() === OrderStatus::AWAITING_PAYMENT && ($pending[1] ?? null) === $signature
                && \in_array($intent?->status, ['requires_payment_method', 'requires_confirmation', 'requires_action'], true);
            if ($reusable) {
                // Same basket: keep the order and payment, with the latest details.
                $value = static fn (string $key): ?string => ($text = trim((string) ($data[$key] ?? ''))) === '' ? null : $text;
                $email = strtolower((string) $value('email'));
                $account = $this->orderAccounts->accountFor($email, self::buyerName($data));
                $order->setUser($account);
                $order->setEmail($email);
                $order->setShippingAddress($this->deliveryAddress($cart, $data, $postcode, $account));
                $this->applyDetails($order, $data, $cart);
                $this->orderRepository->save($order, true);
            } else {
                if ($order->getStatus() === OrderStatus::AWAITING_PAYMENT) {
                    $this->cancelPayment($order);
                }

                $order = null;
                $intent = null;
            }
        } else {
            $order = null;
        }

        // A reused order always has its payment; otherwise start both.
        if (! $order instanceof Order) {
            try {
                $order = $this->createOrder($request, $cart, $data, $postcode);
                $intent = $this->stripeClient->createPaymentIntent($order->getTotal(), $order->getCurrency(), $order->getOrderNumber(), $order->getEmail(), cardOnly: true);
                $order->setStripePaymentIntentId($intent->id);
                $this->orderRepository->save($order, true);
            } catch (Throwable $throwable) {
                $this->logger->error('Starting a card payment failed: ' . $throwable->getMessage());

                return new JsonResponse([
                    'status' => 'error',
                    'message' => 'We couldn\'t start the payment. Please try again in a moment.',
                ], Response::HTTP_SERVICE_UNAVAILABLE);
            }
        }

        $request->getSession()->set(self::SESSION_PENDING, [$order->getOrderNumber(), $signature]);
        $billing = $order->getBillingAddress() ?? $order->getShippingAddress();

        return new JsonResponse([
            'status' => 'confirm',
            'clientSecret' => $intent->clientSecret,
            'completeUrl' => $this->generateUrl('checkout_complete', [
                'orderNumber' => $order->getOrderNumber(),
            ], UrlGeneratorInterface::ABSOLUTE_URL),
            'billingDetails' => [
                'name' => trim((string) ($data['cardName'] ?? '')),
                'email' => $order->getEmail(),
                'phone' => $order->getContactPhone(),
                'address' => [
                    'line1' => $billing?->getLine1(),
                    'line2' => $billing?->getLine2(),
                    'city' => $billing?->getCity(),
                    'state' => $billing?->getCounty(),
                    'postal_code' => $billing?->getPostcode(),
                    'country' => 'GB',
                ],
            ],
        ]);
    }

    /**
     * Cancel an unpaid order the customer has moved on from (and its payment).
     */
    private function cancelPayment(Order $order): void
    {
        try {
            if ($order->getStripePaymentIntentId() !== null) {
                $this->stripeClient->cancelPaymentIntent($order->getStripePaymentIntentId());
            }
        } catch (Throwable $throwable) {
            $this->logger->warning('Cancelling a Stripe payment failed: ' . $throwable->getMessage());
        }

        $order->setStatus(OrderStatus::CANCELLED);
        $this->orderRepository->save($order, true);
    }

    /**
     * What the order is for: each basket line, the gift date and the
     * delivery postcode (a change means a new order and payment).
     */
    private function basketSignature(Cart $cart, string $postcode): string
    {
        $lines = array_map(static fn ($item): string => implode(':', [(string) $item->getId(), $item->getQuantity(), $item->getUnitPrice(), (string) $item->getHash()]), $cart->getCartItems()->toArray());
        sort($lines);

        return hash('sha256', implode('|', $lines) . '|' . ($cart->getGiftArriveBy()?->format('Y-m-d') ?? '') . '|' . ($cart->getGiftSendTo()->value ?? '') . '|' . $postcode);
    }

    /**
     * Revolut (switched off; kept): its own payment page, as before.
     */
    private function payWithRevolut(Request $request, Order $order): Response
    {
        $this->startRevolutPayment($request, $order);
        $this->orderRepository->save($order, true);

        return $this->redirectToRoute('checkout_pay', [
            'orderNumber' => $order->getOrderNumber(),
        ]);
    }

    /**
     * Each field's first problem, by field name, for the page to show.
     *
     * @param FormInterface<mixed> $form
     *
     * @return array<string, string>
     */
    private function fieldErrors(FormInterface $form): array
    {
        $errors = [];
        foreach ($form->all() as $name => $child) {
            foreach ($child->getErrors() as $error) {
                $errors[$name] ??= $error->getMessage();
            }
        }

        return $errors;
    }

    /**
     * A postcode outside the UK mainland: the basket goes to "Reserve Your
     * Design" (no payment), with the details typed so far filled in there.
     *
     * @param array<string, mixed> $data
     */
    private function reserveInstead(Request $request, array $data, Cart $cart): RedirectResponse
    {
        $value = static fn (string $key): string => trim((string) ($data[$key] ?? ''));
        $postcode = DeliveryArea::normalise($value('postcode')) ?? $value('postcode');
        // Reserve has no company field: it goes in the notes.
        $notes = array_filter([
            $value('company') !== '' ? 'Company: ' . $value('company') : '',
            $value('notes'),
        ], static fn (string $line): bool => $line !== '');
        // A gift keeps its date and choices; sent directly, the delivery name is the recipient's and the customer's name is the buyer's (if given).
        $direct = $cart->isDirectGift();
        $gift = $cart->isGift() && $cart->getGiftArriveBy() !== null ? [
            'gift' => '1',
            'giftNeededBy' => $cart->getGiftArriveBy()->format('Y-m-d'),
            'giftSendTo' => $cart->getGiftSendTo()->value ?? '',
            'giftRecipientName' => $direct ? $value('name') : '',
            'giftMessage' => $direct ? $value('giftMessage') : '',
            'giftRecipientPhone' => $direct ? $value('recipientPhone') : '',
        ] : [];

        $request->getSession()->set(ReserveController::PREFILL_KEY, [
            ...$gift,
            'fullName' => $direct ? (string) self::buyerName($data) : $value('name'),
            'email' => $value('email'),
            'phone' => $value('phone'),
            'addressLine1' => $value('line1'),
            'addressLine2' => $value('line2'),
            'town' => $value('town'),
            'county' => $value('county'),
            'postcode' => $postcode,
            'country' => ReserveForm::countryForPostcode($postcode),
            'accessNotes' => implode("\n", $notes),
        ]);

        return $this->redirectToRoute('basket_request', [
            'basket' => 1,
        ]);
    }

    /**
     * The buyer's name (Greeting name, 9 October 2026), never a gift
     * recipient's: the billing name if a separate billing address was given,
     * otherwise the name on card, otherwise the wallet's payer name.
     *
     * @param array<string, mixed> $data
     */
    private static function buyerName(array $data): ?string
    {
        $value = static fn (string $key): string => trim((string) ($data[$key] ?? ''));
        foreach ([($data['billingSame'] ?? true) ? '' : $value('billingName'), $value('cardName') === '-' ? '' : $value('cardName'), $value('payerName')] as $name) {
            if ($name !== '') {
                return $name;
            }
        }

        return null;
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

        if ($order->isPaid()) {
            return $this->redirectToRoute('checkout_complete', [
                'orderNumber' => $orderNumber,
            ]);
        }

        if ($this->usesStripe()) {
            // Cards are paid on the Card Payment page itself (10.3).
            return $this->redirectToRoute('checkout');
        }

        $completeUrl = $this->generateUrl('checkout_complete', [
            'orderNumber' => $orderNumber,
        ]);

        return $this->render('checkout/pay.html.twig', [
            'order' => $order,
            'provider' => 'revolut',
            'revolutToken' => $request->getSession()->get($this->tokenSessionKey($orderNumber)),
            'revolutPublicKey' => $this->revolutPublicKey,
            'revolutMode' => $this->revolutEnv === 'prod' ? 'prod' : 'sandbox',
            'completeUrl' => $completeUrl,
        ]);
    }

    /**
     * After paying. Stripe is asked directly whether the payment went through
     * (its webhook says the same later; marking paid twice is harmless), and
     * once paid the basket is emptied.
     */
    #[Route(path: '/checkout/complete/{orderNumber}', name: 'checkout_complete')]
    public function complete(Request $request, string $orderNumber): Response
    {
        $order = $this->visibleOrder($request, $orderNumber);
        if (! $order instanceof Order) {
            throw $this->createNotFoundException();
        }

        if (! $order->isPaid() && $order->getStripePaymentIntentId() !== null) {
            try {
                $intent = $this->stripeClient->retrievePaymentIntent($order->getStripePaymentIntentId());
                if ($intent->status === 'succeeded') {
                    $this->orderProcessor->markPaid($order);
                }
            } catch (Throwable $throwable) {
                $this->logger->error('Stripe payment lookup failed: ' . $throwable->getMessage());
            }
        }

        if ($order->isPaid()) {
            $cart = $this->cartResolver->peekCart();
            if ($cart instanceof Cart && ! $cart->isEmpty()) {
                foreach ($cart->getCartItems()->toArray() as $line) {
                    $cart->removeCartItem($line);
                }

                $cart->setGiftArriveBy(null);
                $this->entityManager->flush();
            }

            $request->getSession()->remove(self::SESSION_PENDING);
        }

        return $this->render('checkout/complete.html.twig', [
            'order' => $order,
        ]);
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
}

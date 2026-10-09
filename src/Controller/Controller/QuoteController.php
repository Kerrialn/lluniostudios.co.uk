<?php

declare(strict_types=1);

namespace App\Controller\Controller;

use App\Entity\Order;
use App\Entity\Quote;
use App\Form\Form\BalancePaymentForm;
use App\Form\Form\CardPaymentForm;
use App\Payment\StripePaymentClient;
use App\Service\QuotePayments;
use App\Service\Quotes;
use App\Shipping\DeliveryArea;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Throwable;

/**
 * The customer's private quote page (Step 10b.2), from the quote email's
 * "Pay now": what was last sent (later changes in admin show only once sent
 * again), valid for 14 days. Anyone with the link can see it, so it is never
 * indexed.
 *
 * Paying (10b.3): under the quote, the Card Payment page's fields (filled in
 * from the request), then the deposit or the whole total by card, Apple Pay
 * or Google Pay (the same page script and replies as the Card Payment page).
 * The delivery postcode must be the one quoted. Once paid, the page shows
 * what has been paid and what is still owed.
 *
 * The balance (10b.4): once the studio has asked for it ("Request balance"),
 * the page also has the name on the card, Stripe's boxes (or Apple Pay /
 * Google Pay) and "Pay balance £x"; once paid, the order is paid in full.
 */
final class QuoteController extends AbstractController
{
    public const POSTCODE_MESSAGE = "Delivery was quoted for %s. To deliver elsewhere, please reply to your quote email and we'll update your quote.";

    public function __construct(
        private readonly Quotes $quotes,
        private readonly QuotePayments $payments,
        private readonly StripePaymentClient $stripe,
        private readonly LoggerInterface $logger,
    ) {
    }

    #[Route(path: '/quote/{token}', name: 'quote_show', methods: ['GET'], requirements: [
        'token' => '[A-Za-z0-9_-]{16,64}',
    ])]
    public function show(string $token): Response
    {
        $quote = $this->sentQuote($token);
        $request = $quote->getDesignRequest();
        $paid = $this->payments->paidOrder($request);
        $form = $this->createForm(CardPaymentForm::class, [
            'email' => $request->getEmail(),
            'phone' => $request->getPhone(),
            'name' => $request->getFullName(),
            'line1' => $request->getAddressLine1(),
            'line2' => $request->getAddressLine2(),
            'town' => $request->getTown(),
            'county' => $request->getCounty(),
            'postcode' => $request->getPostcode(),
            'billingSame' => true,
        ], [
            'action' => $this->generateUrl('quote_pay', [
                'token' => $token,
            ]),
        ]);

        $balanceForm = $paid instanceof Order && $paid->canRequestBalance() && $paid->getBalanceRequestedAt() !== null
            ? $this->container->get('form.factory')->createNamed('card_payment_form', BalancePaymentForm::class, [
                'cardName' => $request->getFullName(),
            ], [
                'action' => $this->generateUrl('quote_balance', [
                    'token' => $token,
                ]),
            ])
            : null;

        $response = $this->render('quote/show.html.twig', [
            'designRequest' => $request,
            'quote' => $this->quotes->view($quote),
            'validUntil' => $quote->getExpiresAt(),
            'expired' => $quote->isExpired() && ! $paid instanceof Order,
            'paidOrder' => $paid,
            'form' => $form,
            'balanceForm' => $balanceForm?->createView(),
            'stripeKey' => $this->stripe->isConfigured() ? $this->stripe->getPublishableKey() : '',
            'token' => $token,
        ]);
        $response->headers->set('X-Robots-Tag', 'noindex, nofollow');

        return $response;
    }

    /**
     * "Pay" (the page's form, or ?wallet=1 once Apple Pay / Google Pay is
     * approved): the details are checked, then the order is saved and a
     * payment started for the page to confirm.
     */
    #[Route(path: '/quote/{token}/pay', name: 'quote_pay', methods: ['POST'], requirements: [
        'token' => '[A-Za-z0-9_-]{16,64}',
    ])]
    public function pay(Request $request, string $token): JsonResponse
    {
        // Only this site's own page may send here.
        if (! \in_array($request->headers->get('Sec-Fetch-Site'), [null, 'same-origin'], true)) {
            return new JsonResponse(null, Response::HTTP_FORBIDDEN);
        }

        $quote = $this->sentQuote($token);
        $designRequest = $quote->getDesignRequest();
        if ($this->payments->paidOrder($designRequest) instanceof Order) {
            return $this->toPage($token);
        }

        if ($quote->isExpired()) {
            return new JsonResponse([
                'status' => 'error',
                'message' => 'This quote has expired. Please reply to your quote email for an updated quote.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $wallet = $request->query->getBoolean('wallet');
        $form = $this->createForm(CardPaymentForm::class);
        if ($wallet) {
            // Apple Pay / Google Pay: the name on the card is the wallet's.
            $fields = $request->request->all($form->getName());
            $fields['cardName'] = trim((string) ($fields['cardName'] ?? '')) ?: (string) ($fields['name'] ?? '');
            $form->submit($fields);
        } else {
            $form->handleRequest($request);
        }

        /** @var array<string, mixed> $data */
        $data = (array) $form->getData();
        $postcode = DeliveryArea::normalise((string) ($data['postcode'] ?? ''));
        $quoted = $designRequest->getPostcode();
        if ($postcode !== null && $quoted !== null && $postcode !== DeliveryArea::normalise($quoted)) {
            $form->get('postcode')->addError(new FormError(\sprintf(self::POSTCODE_MESSAGE, $quoted)));
        }

        $cardReady = $wallet || $request->request->getBoolean('card_ready');
        if (! $form->isSubmitted() || ! $form->isValid() || $postcode === null || ! $cardReady) {
            return new JsonResponse([
                'status' => 'invalid',
                'errors' => $this->fieldErrors($form),
                'requiredMissing' => $this->requiredMissing($form) || ! $cardReady,
                'message' => $wallet ? 'Please complete the required fields above, then try again.' : null,
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        try {
            ['order' => $order, 'intent' => $intent] = $this->payments->start($quote, $data, $postcode);
        } catch (Throwable $throwable) {
            $this->logger->error('Starting a quote payment failed: ' . $throwable->getMessage());

            return new JsonResponse([
                'status' => 'error',
                'message' => 'We couldn\'t start the payment. Please try again in a moment.',
            ], Response::HTTP_SERVICE_UNAVAILABLE);
        }

        if ($intent === null) {
            // Paid already (e.g. another tab).
            return $this->toPage($token);
        }

        $billing = $order->getBillingAddress() ?? $order->getShippingAddress();

        return new JsonResponse([
            'status' => 'confirm',
            'clientSecret' => $intent->clientSecret,
            'completeUrl' => $this->generateUrl('quote_complete', [
                'token' => $token,
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
     * After paying: Stripe is asked whether the payment went through, then
     * "Order & Slot Confirmed" (or "Order Received" while it is confirmed).
     */
    #[Route(path: '/quote/{token}/complete', name: 'quote_complete', methods: ['GET'], requirements: [
        'token' => '[A-Za-z0-9_-]{16,64}',
    ])]
    public function complete(string $token): Response
    {
        $quote = $this->sentQuote($token);
        $order = $this->payments->ordersFor($quote->getDesignRequest())[0] ?? null;
        if (! $order instanceof Order) {
            return $this->redirectToRoute('quote_show', [
                'token' => $token,
            ]);
        }

        $this->payments->finish($order);
        $response = $this->render('quote/complete.html.twig', [
            'order' => $order,
            'token' => $token,
        ]);
        $response->headers->set('X-Robots-Tag', 'noindex, nofollow');

        return $response;
    }

    /**
     * "Pay balance" (the page's form, or ?wallet=1 once Apple Pay / Google Pay
     * is approved): the balance's payment for the page to confirm.
     */
    #[Route(path: '/quote/{token}/balance', name: 'quote_balance', methods: ['POST'], requirements: [
        'token' => '[A-Za-z0-9_-]{16,64}',
    ])]
    public function balance(Request $request, string $token): JsonResponse
    {
        if (! \in_array($request->headers->get('Sec-Fetch-Site'), [null, 'same-origin'], true)) {
            return new JsonResponse(null, Response::HTTP_FORBIDDEN);
        }

        $quote = $this->sentQuote($token);
        $order = $this->payments->paidOrder($quote->getDesignRequest());
        if (! $order instanceof Order || ! $order->canRequestBalance() || $order->getBalanceRequestedAt() === null) {
            return new JsonResponse([
                'status' => 'redirect',
                'url' => $this->generateUrl('quote_show', [
                    'token' => $token,
                ]),
            ]);
        }

        $wallet = $request->query->getBoolean('wallet');
        $form = $this->container->get('form.factory')->createNamed('card_payment_form', BalancePaymentForm::class);
        $fields = $request->request->all('card_payment_form');
        if ($wallet) {
            // Apple Pay / Google Pay: the wallet's card; the name is the customer's.
            $fields['cardName'] = trim((string) ($fields['cardName'] ?? '')) ?: $quote->getDesignRequest()->getFullName();
        }

        $form->submit($fields);
        $cardReady = $wallet || $request->request->getBoolean('card_ready');
        if (! $form->isValid() || ! $cardReady) {
            return new JsonResponse([
                'status' => 'invalid',
                'errors' => $this->fieldErrors($form),
                'requiredMissing' => $this->requiredMissing($form) || ! $cardReady,
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        try {
            $intent = $this->payments->startBalance($order, (string) $form->get('cardName')->getData());
        } catch (Throwable $throwable) {
            $this->logger->error('Starting a balance payment failed: ' . $throwable->getMessage());

            return new JsonResponse([
                'status' => 'error',
                'message' => 'We couldn\'t start the payment. Please try again in a moment.',
            ], Response::HTTP_SERVICE_UNAVAILABLE);
        }

        $billing = $order->getBillingAddress() ?? $order->getShippingAddress();

        return new JsonResponse([
            'status' => 'confirm',
            'clientSecret' => $intent->clientSecret,
            'completeUrl' => $this->generateUrl('quote_balance_complete', [
                'token' => $token,
            ], UrlGeneratorInterface::ABSOLUTE_URL),
            'billingDetails' => [
                'name' => (string) $form->get('cardName')->getData(),
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
     * After paying the balance: "Payment Received" once Stripe confirms it.
     */
    #[Route(path: '/quote/{token}/balance/complete', name: 'quote_balance_complete', methods: ['GET'], requirements: [
        'token' => '[A-Za-z0-9_-]{16,64}',
    ])]
    public function balanceComplete(string $token): Response
    {
        $quote = $this->sentQuote($token);
        $order = $this->payments->paidOrder($quote->getDesignRequest());
        if (! $order instanceof Order || $order->getBalancePaymentIntentId() === null) {
            return $this->redirectToRoute('quote_show', [
                'token' => $token,
            ]);
        }

        $this->payments->finishBalance($order);
        $response = $this->render('quote/balance_complete.html.twig', [
            'order' => $order,
        ]);
        $response->headers->set('X-Robots-Tag', 'noindex, nofollow');

        return $response;
    }

    private function sentQuote(string $token): Quote
    {
        $quote = $this->quotes->findByToken($token);
        if (! $quote instanceof Quote || ! $quote->isSent()) {
            throw $this->createNotFoundException();
        }

        return $quote;
    }

    private function toPage(string $token): JsonResponse
    {
        return new JsonResponse([
            'status' => 'redirect',
            'url' => $this->generateUrl('quote_complete', [
                'token' => $token,
            ]),
        ]);
    }

    /**
     * Each field's first problem, by field name (as the Card Payment page).
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
}

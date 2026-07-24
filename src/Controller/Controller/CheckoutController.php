<?php

namespace App\Controller\Controller;

use App\Entity\Address;
use App\Entity\User;
use App\Enum\OrderStatus;
use App\Enum\ShippingMethod;
use App\Form\Form\AddressForm;
use App\Payment\RevolutMerchantClient;
use App\Payment\StripePaymentClient;
use App\Repository\AddressRepository;
use App\Repository\OrderRepository;
use App\Repository\UserRepository;
use App\Service\CartResolver;
use App\Service\LoginCodeService;
use App\Service\OrderFactory;
use App\Service\ShippingCalculator;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Util\TargetPathTrait;
use Throwable;

class CheckoutController extends AbstractController
{
    use TargetPathTrait;

    private const SESSION_ADDRESS_ID = 'checkout_address_id';

    private const SESSION_EMAIL = 'checkout_email';

    public function __construct(
        private readonly CartResolver $cartResolver,
        private readonly AddressRepository $addressRepository,
        private readonly OrderRepository $orderRepository,
        private readonly UserRepository $userRepository,
        private readonly ShippingCalculator $shippingCalculator,
        private readonly OrderFactory $orderFactory,
        private readonly RevolutMerchantClient $revolutClient,
        private readonly StripePaymentClient $stripeClient,
        private readonly LoginCodeService $loginCodeService,
        private readonly Security $security,
        private readonly EntityManagerInterface $entityManager,
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

    #[Route(path: '/checkout', name: 'checkout')]
    public function address(Request $request): Response
    {
        $cart = $this->cartResolver->getCart();
        if ($cart->isEmpty()) {
            return $this->redirectToRoute('cart');
        }

        $currentUser = $this->currentUser();

        $address = new Address();
        $form = $this->createForm(AddressForm::class, $address, [
            'with_email' => ! $currentUser instanceof User,
            'email' => $currentUser?->getEmail(),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $email = $currentUser instanceof User
                ? $currentUser->getEmail()
                : strtolower(trim((string) $form->get('email')->getData()));

            if (! $currentUser instanceof User) {
                $existing = $this->userRepository->findOneBy([
                    'email' => $email,
                ]);

                // Existing account -> verify identity with an emailed code before
                // continuing, so nobody can check out into someone else's account.
                if ($existing instanceof User) {
                    try {
                        $this->loginCodeService->request($existing);
                    } catch (Throwable $throwable) {
                        // Mail transport down (e.g. banned SMTP account). Don't 500 —
                        // keep them on the address step to retry.
                        $this->logger->error('Checkout login code send failed: ' . $throwable->getMessage());
                        $this->addFlash('error', 'We couldn\'t email your sign-in code right now. Please try again in a moment.');

                        return $this->render('checkout/address.html.twig', [
                            'cart' => $cart,
                            'form' => $form,
                        ]);
                    }

                    $request->getSession()->set(SecurityController::SESSION_PENDING_EMAIL, $email);
                    $this->saveTargetPath($request->getSession(), 'main', $this->generateUrl('checkout'));
                    $this->addFlash('message', 'You already have an account. Enter the 6-digit code we just emailed to continue.');

                    return $this->redirectToRoute('app_login');
                }

                // New email -> create a passwordless account and sign them in.
                $currentUser = new User($email);
                $this->applyName($currentUser, (string) $address->getRecipientName());
                $this->entityManager->persist($currentUser);

                $this->cartResolver->attachToUser($currentUser);
                $this->security->login($currentUser);
            }

            $address->setOwner($currentUser);
            $this->addressRepository->save($address, true);

            $request->getSession()->set(self::SESSION_ADDRESS_ID, (string) $address->getId());
            $request->getSession()->set(self::SESSION_EMAIL, $email);

            return $this->redirectToRoute('checkout_shipping');
        }

        return $this->render('checkout/address.html.twig', [
            'cart' => $cart,
            'form' => $form,
        ]);
    }

    #[Route(path: '/checkout/shipping', name: 'checkout_shipping')]
    public function shipping(Request $request): Response
    {
        $user = $this->currentUser();
        if (! $user instanceof User) {
            return $this->redirectToRoute('checkout');
        }

        $cart = $this->cartResolver->getCart();
        if ($cart->isEmpty()) {
            return $this->redirectToRoute('cart');
        }

        $address = $this->sessionAddress($request);
        if (! $address instanceof Address) {
            return $this->redirectToRoute('checkout');
        }

        $quotes = $this->shippingCalculator->quotesForCart($cart, $address);
        $email = (string) $request->getSession()->get(self::SESSION_EMAIL, $user->getEmail());

        if ($request->isMethod('POST')) {
            $quoteId = (string) $request->request->get('shipping_quote', '');
            $quote = $this->shippingCalculator->findQuote($cart, $address, $quoteId);

            if (! $quote instanceof \App\Shipping\ShippingQuote) {
                $this->addFlash('error', 'Please choose a shipping option.');

                return $this->render('checkout/shipping.html.twig', [
                    'cart' => $cart,
                    'address' => $address,
                    'quotes' => $quotes,
                ]);
            }

            $shippingAddress = $quote->method === ShippingMethod::PICKUP ? null : $address;
            $order = $this->orderFactory->createFromCart($cart, $user, $email, $quote, $shippingAddress);
            $this->orderRepository->save($order, true);

            // Start the payment with the active provider. Skipped gracefully until
            // keys are configured, so the flow stays demoable.
            if ($this->usesStripe()) {
                $this->startStripePayment($request, $order);
            } else {
                $this->startRevolutPayment($request, $order);
            }

            $order->setStatus(OrderStatus::AWAITING_PAYMENT);
            $this->orderRepository->save($order, true);

            return $this->redirectToRoute('checkout_pay', [
                'orderNumber' => $order->getOrderNumber(),
            ]);
        }

        return $this->render('checkout/shipping.html.twig', [
            'cart' => $cart,
            'address' => $address,
            'quotes' => $quotes,
        ]);
    }

    #[Route(path: '/checkout/pay/{orderNumber}', name: 'checkout_pay')]
    public function pay(Request $request, string $orderNumber): Response
    {
        $user = $this->currentUser();
        $order = $this->orderRepository->findByOrderNumber($orderNumber);
        if (! $order instanceof \App\Entity\Order || ! $user instanceof User || $order->getUser() !== $user) {
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
    public function complete(string $orderNumber): Response
    {
        $user = $this->currentUser();
        $order = $this->orderRepository->findByOrderNumber($orderNumber);
        if (! $order instanceof \App\Entity\Order || ! $user instanceof User || $order->getUser() !== $user) {
            throw $this->createNotFoundException();
        }

        return $this->render('checkout/complete.html.twig', [
            'order' => $order,
        ]);
    }

    private function currentUser(): ?User
    {
        $user = $this->getUser();

        return $user instanceof User ? $user : null;
    }

    private function applyName(User $user, string $recipientName): void
    {
        $recipientName = trim($recipientName);
        if ($recipientName === '') {
            return;
        }

        $parts = preg_split('/\s+/', $recipientName) ?: [];
        $user->setFirstName(array_shift($parts) ?: null);
        $user->setLastName($parts !== [] ? implode(' ', $parts) : null);
    }

    private function sessionAddress(Request $request): ?Address
    {
        $addressId = $request->getSession()->get(self::SESSION_ADDRESS_ID);
        if (! is_string($addressId) || $addressId === '') {
            return null;
        }

        return $this->addressRepository->find($addressId);
    }

    private function startStripePayment(Request $request, \App\Entity\Order $order): void
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

    private function startRevolutPayment(Request $request, \App\Entity\Order $order): void
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

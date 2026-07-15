<?php

namespace App\Controller\Controller;

use App\Entity\Address;
use App\Entity\User;
use App\Enum\OrderStatus;
use App\Enum\ShippingMethod;
use App\Form\Form\AddressForm;
use App\Payment\RevolutMerchantClient;
use App\Repository\AddressRepository;
use App\Repository\OrderRepository;
use App\Repository\UserRepository;
use App\Service\CartResolver;
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
        private readonly Security $security,
        private readonly EntityManagerInterface $entityManager,
        private readonly LoggerInterface $logger,
        #[Autowire('%env(REVOLUT_PUBLIC_KEY)%')]
        private readonly string $revolutPublicKey,
        #[Autowire('%env(REVOLUT_ENV)%')]
        private readonly string $revolutEnv,
    ) {
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
                $existing = $this->userRepository->findOneBy(['email' => $email]);

                // Registered account (has a password) -> require login first.
                if ($existing instanceof User && $existing->hasPassword()) {
                    $this->saveTargetPath($request->getSession(), 'main', $this->generateUrl('checkout'));
                    $this->addFlash('message', 'You already have an account. Please log in to continue checkout.');

                    return $this->redirectToRoute('app_login');
                }

                // Otherwise reuse the passwordless account or create a fresh one.
                $currentUser = $existing ?? new User($email);
                if (! $existing instanceof User) {
                    $this->applyName($currentUser, (string) $address->getRecipientName());
                    $this->entityManager->persist($currentUser);
                }

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

            if ($quote === null) {
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

            // Create the Revolut order server-side (sandbox). Skipped gracefully
            // until sandbox keys are configured, so the flow is demoable now.
            if ($this->revolutClient->isConfigured()) {
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
        if ($order === null || ! $user instanceof User || $order->getUser() !== $user) {
            throw $this->createNotFoundException();
        }

        if ($order->getStatus() === OrderStatus::PAID) {
            return $this->redirectToRoute('checkout_complete', [
                'orderNumber' => $orderNumber,
            ]);
        }

        $token = $request->getSession()->get($this->tokenSessionKey($orderNumber));

        return $this->render('checkout/pay.html.twig', [
            'order' => $order,
            'revolutToken' => $token,
            'revolutPublicKey' => $this->revolutPublicKey,
            'revolutMode' => $this->revolutEnv === 'prod' ? 'prod' : 'sandbox',
            'completeUrl' => $this->generateUrl('checkout_complete', [
                'orderNumber' => $orderNumber,
            ]),
        ]);
    }

    #[Route(path: '/checkout/complete/{orderNumber}', name: 'checkout_complete')]
    public function complete(string $orderNumber): Response
    {
        $user = $this->currentUser();
        $order = $this->orderRepository->findByOrderNumber($orderNumber);
        if ($order === null || ! $user instanceof User || $order->getUser() !== $user) {
            throw $this->createNotFoundException();
        }

        return $this->render('checkout/complete.html.twig', [
            'order' => $order,
            // Prompt the customer to secure their account if they haven't yet.
            'needsPassword' => ! $user->hasPassword(),
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

    private function tokenSessionKey(string $orderNumber): string
    {
        return 'revolut_token_' . $orderNumber;
    }
}

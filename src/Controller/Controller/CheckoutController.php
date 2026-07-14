<?php

namespace App\Controller\Controller;

use App\Entity\Address;
use App\Entity\Identity;
use App\Enum\OrderStatus;
use App\Enum\ShippingMethod;
use App\Form\Form\AddressForm;
use App\Payment\RevolutMerchantClient;
use App\Repository\AddressRepository;
use App\Repository\CartRepository;
use App\Repository\OrderRepository;
use App\Service\OrderFactory;
use App\Service\ShippingCalculator;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Throwable;

class CheckoutController extends AbstractController
{
    private const SESSION_ADDRESS_ID = 'checkout_address_id';

    private const SESSION_EMAIL = 'checkout_email';

    public function __construct(
        private readonly CartRepository $cartRepository,
        private readonly AddressRepository $addressRepository,
        private readonly OrderRepository $orderRepository,
        private readonly ShippingCalculator $shippingCalculator,
        private readonly OrderFactory $orderFactory,
        private readonly RevolutMerchantClient $revolutClient,
        private readonly LoggerInterface $logger,
        #[Autowire('%env(REVOLUT_PUBLIC_KEY)%')]
        private readonly string $revolutPublicKey,
        #[Autowire('%env(REVOLUT_ENV)%')]
        private readonly string $revolutEnv,
    ) {
    }

    #[Route(path: '/checkout', name: 'checkout')]
    public function address(#[CurrentUser] Identity $identity, Request $request): Response
    {
        $cart = $this->cartRepository->findOrCreate($identity);
        if ($cart->isEmpty()) {
            return $this->redirectToRoute('cart');
        }

        $address = new Address();
        $prefillEmail = $this->identityEmail($identity);

        $form = $this->createForm(AddressForm::class, $address, [
            'with_email' => true,
            'email' => $prefillEmail,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $address->setOwner($identity);
            $this->addressRepository->save($address, true);

            $request->getSession()->set(self::SESSION_ADDRESS_ID, (string) $address->getId());
            $request->getSession()->set(self::SESSION_EMAIL, (string) $form->get('email')->getData());

            return $this->redirectToRoute('checkout_shipping');
        }

        return $this->render('checkout/address.html.twig', [
            'cart' => $cart,
            'form' => $form,
        ]);
    }

    #[Route(path: '/checkout/shipping', name: 'checkout_shipping')]
    public function shipping(#[CurrentUser] Identity $identity, Request $request): Response
    {
        $cart = $this->cartRepository->findOrCreate($identity);
        if ($cart->isEmpty()) {
            return $this->redirectToRoute('cart');
        }

        $address = $this->sessionAddress($request);
        if (! $address instanceof Address) {
            return $this->redirectToRoute('checkout');
        }

        $quotes = $this->shippingCalculator->quotesForCart($cart, $address);
        $email = (string) $request->getSession()->get(self::SESSION_EMAIL, $this->identityEmail($identity));

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
            $order = $this->orderFactory->createFromCart($cart, $identity, $email, $quote, $shippingAddress);
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
    public function pay(#[CurrentUser] Identity $identity, Request $request, string $orderNumber): Response
    {
        $order = $this->orderRepository->findByOrderNumber($orderNumber);
        if ($order === null || $order->getIdentity() !== $identity) {
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
    public function complete(#[CurrentUser] Identity $identity, string $orderNumber): Response
    {
        $order = $this->orderRepository->findByOrderNumber($orderNumber);
        if ($order === null || $order->getIdentity() !== $identity) {
            throw $this->createNotFoundException();
        }

        return $this->render('checkout/complete.html.twig', [
            'order' => $order,
        ]);
    }

    private function sessionAddress(Request $request): ?Address
    {
        $addressId = $request->getSession()->get(self::SESSION_ADDRESS_ID);
        if (! is_string($addressId) || $addressId === '') {
            return null;
        }

        return $this->addressRepository->find($addressId);
    }

    private function identityEmail(Identity $identity): ?string
    {
        try {
            return $identity->getEmail();
        } catch (Throwable) {
            return null;
        }
    }

    private function tokenSessionKey(string $orderNumber): string
    {
        return 'revolut_token_' . $orderNumber;
    }
}

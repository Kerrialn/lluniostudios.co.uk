<?php

namespace App\Controller\Controller;

use App\Entity\User;
use App\Repository\OrderRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * The customer's order history (Step 10.6), reached by signing in (the
 * "Your orders" link on the Contact page, or the sign-in email's button).
 */
#[Route(path: '/account')]
#[IsGranted('ROLE_USER')]
class AccountController extends AbstractController
{
    public function __construct(
        private readonly OrderRepository $orderRepository,
    ) {
    }

    #[Route(path: '/orders', name: 'account_orders')]
    public function orders(): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        return $this->render('account/orders.html.twig', [
            'orders' => $this->orderRepository->findPlacedByUser($user),
        ]);
    }

    #[Route(path: '/orders/{orderNumber}', name: 'account_order_show')]
    public function orderShow(string $orderNumber): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $order = $this->orderRepository->findByOrderNumber($orderNumber);

        if (! $order instanceof \App\Entity\Order || $order->getUser() !== $user || ! \in_array($order, $this->orderRepository->findPlacedByUser($user), true)) {
            throw $this->createNotFoundException();
        }

        return $this->render('account/order_show.html.twig', [
            'order' => $order,
        ]);
    }
}

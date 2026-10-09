<?php

declare(strict_types=1);

namespace App\Controller\Controller;

use App\Entity\Order;
use App\Repository\OrderRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * "View your order" (Step 11.1): the order's private link from its emails
 * shows that one order without signing in, laid out as in Your Orders.
 * Only placed orders (paid, a deposit paid, or later); never indexed.
 */
final class OrderViewController extends AbstractController
{
    public function __construct(
        private readonly OrderRepository $orders,
    ) {
    }

    #[Route(path: '/order/{token}', name: 'order_view', requirements: [
        'token' => '[A-Za-z0-9_-]{20,64}',
    ], methods: ['GET'])]
    public function show(string $token): Response
    {
        $order = $this->orders->findOneBy([
            'viewToken' => $token,
        ]);
        if (! $order instanceof Order || ! $order->isPlaced()) {
            throw $this->createNotFoundException();
        }

        $response = $this->render('account/order_show.html.twig', [
            'order' => $order,
            'private' => true,
        ]);
        $response->headers->set('X-Robots-Tag', 'noindex, nofollow');

        return $response;
    }
}

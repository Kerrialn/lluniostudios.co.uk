<?php

namespace App\Controller\Controller;

use App\Entity\Identity;
use App\Repository\CartRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

class CheckoutController extends AbstractController
{
    public function __construct(
        private readonly CartRepository $cartRepository,
    ) {
    }

    // NOTE: placeholder — the full checkout flow (address → shipping → payment)
    // is implemented in Phase 5. For now it guards an empty cart and renders a stub.
    #[Route(path: '/checkout', name: 'checkout')]
    public function index(#[CurrentUser] Identity $identity): Response
    {
        $cart = $this->cartRepository->findOrCreate($identity);

        if ($cart->isEmpty()) {
            return $this->redirectToRoute('cart');
        }

        return $this->render('checkout/index.html.twig', [
            'cart' => $cart,
        ]);
    }
}

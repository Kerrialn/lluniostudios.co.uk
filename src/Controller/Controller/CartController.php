<?php

namespace App\Controller\Controller;

use App\Entity\CartItem;
use App\Repository\CartRepository;
use App\Service\CartResolver;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class CartController extends AbstractController
{
    public function __construct(
        private readonly CartRepository $cartRepository,
        private readonly CartResolver $cartResolver,
    ) {
    }

    #[Route(path: '/cart', name: 'cart')]
    public function index(): Response
    {
        $cart = $this->cartResolver->getCart();

        return $this->render('cart/index.html.twig', [
            'cart' => $cart,
        ]);
    }

    #[Route(path: '/cart/remove/{id}', name: 'cart_remove_item', methods: ['POST'])]
    public function removeItem(CartItem $cartItem): Response
    {
        $cart = $this->cartResolver->peekCart();

        if ($cart && $cartItem->getCart() === $cart) {
            $cart->removeCartItem($cartItem);
            $this->cartRepository->save($cart, true);
            $this->addFlash('message', 'Item removed from cart');
        }

        return $this->redirectToRoute('cart');
    }

    #[Route(path: '/cart/update/{id}', name: 'cart_update_item', methods: ['POST'])]
    public function updateQuantity(CartItem $cartItem, Request $request): Response
    {
        $cart = $this->cartResolver->peekCart();

        if ($cart && $cartItem->getCart() === $cart) {
            $quantity = (int) $request->request->get('quantity', 1);

            if ($quantity <= 0) {
                $cart->removeCartItem($cartItem);
                $this->addFlash('message', 'Item removed from cart');
            } else {
                $cartItem->setQuantity($quantity);
                $this->addFlash('message', 'Cart updated');
            }

            $this->cartRepository->save($cart, true);
        }

        return $this->redirectToRoute('cart');
    }
}

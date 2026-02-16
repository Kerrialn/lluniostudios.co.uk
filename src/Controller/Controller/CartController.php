<?php

namespace App\Controller\Controller;

use App\Entity\CartItem;
use App\Entity\Identity;
use App\Repository\CartRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

class CartController extends AbstractController
{
    public function __construct(
        private readonly CartRepository $cartRepository
    ) {
    }

    #[Route(path: '/cart', name: 'cart')]
    public function index(#[CurrentUser] Identity $identity): Response
    {
        $cart = $this->cartRepository->findOrCreate($identity);

        return $this->render('cart/index.html.twig', [
            'cart' => $cart,
        ]);
    }

    #[Route(path: '/cart/remove/{id}', name: 'cart_remove_item', methods: ['POST'])]
    public function removeItem(CartItem $cartItem, #[CurrentUser] Identity $identity): Response
    {
        $cart = $identity->getCart();

        if ($cart && $cartItem->getCart() === $cart) {
            $cart->removeCartItem($cartItem);
            $this->cartRepository->save($cart, true);
            $this->addFlash('message', 'Item removed from cart');
        }

        return $this->redirectToRoute('cart');
    }

    #[Route(path: '/cart/update/{id}', name: 'cart_update_item', methods: ['POST'])]
    public function updateQuantity(CartItem $cartItem, Request $request, #[CurrentUser] Identity $identity): Response
    {
        $cart = $identity->getCart();

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

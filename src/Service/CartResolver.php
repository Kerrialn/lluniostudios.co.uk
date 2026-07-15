<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Cart;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Resolves the "current" cart without any Identity/fingerprint coupling:
 *  - a logged-in User keeps their cart on the user record;
 *  - an anonymous visitor keeps a cart id in the session.
 *
 * When a user authenticates (e.g. account created at checkout) the anonymous
 * session cart is attached to them via {@see attachToUser()}.
 */
final class CartResolver
{
    private const SESSION_KEY = 'cart_id';

    public function __construct(
        private readonly Security $security,
        private readonly RequestStack $requestStack,
        private readonly EntityManagerInterface $entityManager,
        private readonly CartHelper $cartHelper,
    ) {
    }

    /**
     * The active cart, creating (and persisting) one if none exists yet.
     */
    public function getCart(): Cart
    {
        $user = $this->currentUser();

        if ($user instanceof User) {
            $cart = $user->getCart();
            if (! $cart instanceof Cart) {
                $cart = new Cart();
                $user->setCart($cart);
                $this->entityManager->persist($cart);
                $this->entityManager->persist($user);
                $this->entityManager->flush();
            }

            return $cart;
        }

        $cart = $this->peekCart();
        if (! $cart instanceof Cart) {
            $cart = new Cart();
            $this->entityManager->persist($cart);
            $this->entityManager->flush();
            $this->requestStack->getSession()->set(self::SESSION_KEY, (string) $cart->getId());
        }

        return $cart;
    }

    /**
     * The active cart if one already exists, without creating anything.
     * Safe to call on every page (e.g. the nav cart count).
     */
    public function peekCart(): ?Cart
    {
        $user = $this->currentUser();
        if ($user instanceof User) {
            return $user->getCart();
        }

        $cartId = $this->requestStack->getSession()->get(self::SESSION_KEY);
        if (! is_string($cartId) || $cartId === '') {
            return null;
        }

        return $this->entityManager->getRepository(Cart::class)->find($cartId);
    }

    /**
     * Move the anonymous session cart onto the given user, merging with any
     * cart the user already has. Called when a user is established at checkout.
     */
    public function attachToUser(User $user): Cart
    {
        $session = $this->requestStack->getSession();
        $sessionCartId = $session->get(self::SESSION_KEY);
        $sessionCart = is_string($sessionCartId) && $sessionCartId !== ''
            ? $this->entityManager->getRepository(Cart::class)->find($sessionCartId)
            : null;

        $userCart = $user->getCart();

        if ($sessionCart instanceof Cart && $userCart instanceof Cart && $sessionCart !== $userCart) {
            // Merge session items into the user's existing cart, then drop the session cart.
            foreach ($sessionCart->getCartItems()->toArray() as $cartItem) {
                $sessionCart->removeCartItem($cartItem);
                $userCart->addCartItem($cartItem);
            }
            $this->cartHelper->mergeCartItemDuplication($userCart);
            $this->entityManager->remove($sessionCart);
        } elseif ($sessionCart instanceof Cart && ! $userCart instanceof Cart) {
            $user->setCart($sessionCart);
            $sessionCart->setUser($user);
            $userCart = $sessionCart;
        } elseif (! $userCart instanceof Cart) {
            $userCart = new Cart();
            $user->setCart($userCart);
            $this->entityManager->persist($userCart);
        }

        $session->remove(self::SESSION_KEY);
        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return $userCart;
    }

    private function currentUser(): ?User
    {
        $user = $this->security->getUser();

        return $user instanceof User ? $user : null;
    }
}

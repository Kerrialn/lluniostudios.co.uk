<?php

declare(strict_types=1);

namespace App\Event\Listener;

use App\Entity\Cart;
use App\Entity\Identity;
use App\Repository\CartRepository;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;

#[AsEventListener(event: ControllerEvent::class, method: 'getOrCreateCart', priority: 0)]
readonly class CartListener
{
    public function __construct(
        private Security $security,
        private CartRepository $cartRepository,
    )
    {
    }

    public function getOrCreateCart(ControllerEvent $controllerEvent): void
    {
        if (! $controllerEvent->isMainRequest() || str_starts_with($controllerEvent->getRequest()->getUri(), '_')) {
            return;
        }

        $currentUser = $this->security->getUser();
        if (! $currentUser instanceof Identity) {
            throw new UserNotFoundException('User required to be authenticated create cart');
        }

        if ($currentUser->getCart() instanceof Cart) {
            return;
        } else {
            $cart = new Cart();
            $cart->setOwner($currentUser);
            $this->cartRepository->save(cart: $cart);
        }

    }
}

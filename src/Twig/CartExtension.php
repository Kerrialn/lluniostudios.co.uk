<?php

declare(strict_types=1);

namespace App\Twig;

use App\Entity\Cart;
use App\Service\CartResolver;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class CartExtension extends AbstractExtension
{
    public function __construct(
        private readonly CartResolver $cartResolver,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('current_cart', $this->currentCart(...)),
            new TwigFunction('cart_item_count', $this->cartItemCount(...)),
        ];
    }

    public function currentCart(): ?Cart
    {
        return $this->cartResolver->peekCart();
    }

    public function cartItemCount(): int
    {
        return $this->cartResolver->peekCart()?->getItemCount() ?? 0;
    }
}

<?php

namespace App\Service;

use App\Entity\Cart;
use App\Entity\CartItem;

final readonly class CartHelper
{
    public function generateCartItemHash(CartItem $cartItem): string
    {
        $data = [
            'product' => $cartItem->getProduct()->getId(),
        ];

        $options = [];
        foreach ($cartItem->getCartItemOptions() as $option) {
            $optionId = (string) $option->getProductOption()->getId();
            $valueId = (string) $option->getProductOptionValue()->getId();
            $options[$optionId] = $valueId;
        }

        ksort($options);
        $data['options'] = $options;

        return hash('sha256', json_encode($data));
    }

    public function mergeCartItemDuplication(Cart $cart): void
    {
        $seen = [];
        $toRemove = [];

        foreach ($cart->getCartItems() as $cartItem) {
            $hash = $cartItem->getHash();

            if (isset($seen[$hash])) {
                $seen[$hash]->increaseQuantity($cartItem->getQuantity());
                $toRemove[] = $cartItem;
            } else {
                $seen[$hash] = $cartItem;
            }
        }

        foreach ($toRemove as $cartItem) {
            $cart->removeCartItem($cartItem);
        }
    }
}


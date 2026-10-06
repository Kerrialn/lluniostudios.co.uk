<?php

namespace App\Service;

use App\Entity\CartItem;

/**
 * Fingerprint of a basket line's configuration (product, and each option's
 * value per lamp). Identical lines are no longer merged; the hash is kept as a
 * record of the configuration.
 */
final readonly class CartHelper
{
    public function generateCartItemHash(CartItem $cartItem): string
    {
        $data = [
            'product' => $cartItem->getProduct()->getId(),
        ];

        $options = [];
        foreach ($cartItem->getCartItemOptions() as $option) {
            $key = $option->getProductOption()->getId() . ':' . $option->getLamp();
            $options[$key] = (string) $option->getProductOptionValue()->getId();
        }

        ksort($options);
        $data['options'] = $options;

        return hash('sha256', json_encode($data));
    }
}

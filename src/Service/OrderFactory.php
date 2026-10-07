<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Address;
use App\Entity\Cart;
use App\Entity\Order;
use App\Entity\OrderItem;
use App\Entity\User;
use App\Enum\OrderStatus;
use App\Shipping\ShippingQuote;

final readonly class OrderFactory
{
    /**
     * Build a pending order from the cart, snapshotting each line so historical
     * orders are immune to later product/price edits.
     */
    public function createFromCart(
        Cart $cart,
        ?User $user,
        string $email,
        ShippingQuote $shippingQuote,
        ?Address $shippingAddress,
    ): Order {
        $order = new Order();
        $order->setUser($user);
        $order->setEmail($email);
        $order->setStatus(OrderStatus::PENDING);

        foreach ($cart->getCartItems() as $cartItem) {
            $product = $cartItem->getProduct();
            if ($product === null) {
                continue;
            }

            // In configurator order; lamp 0 = every lamp on the line, 1..n = that lamp.
            $options = [];
            foreach ($cartItem->getLampConfigurations() as $index => $group) {
                foreach ($group['options'] as $option) {
                    $options[] = [
                        'option' => $option->getProductOption()->getName(),
                        'value' => $option->getProductOptionValue()->getValue(),
                        'lamp' => $cartItem->isCustomisedIndividually() ? $index + 1 : 0,
                    ];
                }
            }

            $order->addItem(new OrderItem(
                product: $product,
                productTitle: (string) $product->getTitle(),
                unitPrice: (int) $cartItem->getUnitPrice(),
                quantity: $cartItem->getQuantity() ?? 1,
                optionsSnapshot: $options,
            ));
        }

        $order->setSubtotal($cart->getTotal());
        $order->setShippingMethod($shippingQuote->method);
        $order->setShippingCarrier($shippingQuote->carrier);
        $order->setShippingServiceName($shippingQuote->serviceName);
        $order->setShippingCost($shippingQuote->priceInPence);
        $order->setShippingAddress($shippingAddress);
        $order->recalculateTotal();

        return $order;
    }
}

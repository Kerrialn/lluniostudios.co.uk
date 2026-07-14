<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Address;
use App\Entity\Cart;
use App\Entity\Identity;
use App\Entity\Order;
use App\Entity\OrderItem;
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
        Identity $identity,
        string $email,
        ShippingQuote $shippingQuote,
        ?Address $shippingAddress,
    ): Order {
        $order = new Order();
        $order->setIdentity($identity);
        $order->setEmail($email);
        $order->setStatus(OrderStatus::PENDING);

        foreach ($cart->getCartItems() as $cartItem) {
            $product = $cartItem->getProduct();
            if ($product === null) {
                continue;
            }

            $options = [];
            foreach ($cartItem->getCartItemOptions() as $option) {
                $options[] = [
                    'option' => (string) $option->getProductOption()->getName(),
                    'value' => (string) $option->getProductOptionValue()->getValue(),
                ];
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

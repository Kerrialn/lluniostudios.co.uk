<?php

namespace App\Controller\Controller;

use App\Catalogue\GiftDate;
use App\Catalogue\LumenFormaCatalogue;
use App\Catalogue\LumenFormaConfigurator;
use App\Entity\CartItem;
use App\Form\Form\BasketProceedForm;
use App\Repository\CartRepository;
use App\Service\BasketRules;
use App\Service\CartResolver;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * The basket ("Cart" internally). Customers see it at /basket.
 */
class CartController extends AbstractController
{
    public function __construct(
        private readonly CartRepository $cartRepository,
        private readonly CartResolver $cartResolver,
        private readonly BasketRules $basketRules,
        private readonly GiftDate $giftDate,
    ) {
    }

    /**
     * The basket, and "Proceed to Payment": the gift choice ("This is a
     * gift" with an arrive-by date) is checked and saved on the basket, then
     * the customer goes on to checkout.
     */
    #[Route(path: '/basket', name: 'cart')]
    public function index(Request $request): Response
    {
        $cart = $this->cartResolver->getCart();
        $blockers = $this->basketRules->checkoutBlockers($cart);
        $earliest = $this->giftDate->earliest();

        $proceedForm = $this->createForm(BasketProceedForm::class, [
            'gift' => $cart->isGift(),
            'arriveBy' => $cart->getGiftArriveBy(),
        ], [
            'earliest' => $earliest,
        ]);
        $proceedForm->handleRequest($request);
        if ($proceedForm->isSubmitted() && $blockers === [] && ! $cart->isEmpty() && $proceedForm->isValid()) {
            /** @var array{gift: bool, arriveBy: ?\DateTimeImmutable} $choice */
            $choice = $proceedForm->getData();
            $cart->setGiftArriveBy($choice['gift'] ? $choice['arriveBy'] : null);
            $this->cartRepository->save($cart, true);

            return $this->redirectToRoute('checkout');
        }

        return $this->render('cart/index.html.twig', [
            'proceedForm' => $proceedForm,
            'giftEarliest' => $earliest,
            'cart' => $cart,
            'lampCount' => $this->basketRules->lampCount($cart),
            'checkoutBlockers' => $blockers,
            'routeNote' => $this->basketRules->routeNote($blockers),
            // Each line's size ("Gallery"), shown under the "Lumen Forma" title.
            'sizeNames' => array_map(static fn (CartItem $item): string => $item->getProduct() === null ? '' : LumenFormaConfigurator::sizeName($item->getProduct()), array_values($cart->getCartItems()->toArray())),
            // Lines of these sizes are edited in the configurator.
            'configuratorSlugs' => array_column(LumenFormaCatalogue::SIZES, 'slug'),
        ]);
    }

    /**
     * Old address, kept so existing links and bookmarks still work.
     */
    #[Route(path: '/cart', name: 'cart_legacy')]
    public function legacy(): Response
    {
        return $this->redirectToRoute('cart', status: Response::HTTP_MOVED_PERMANENTLY);
    }

    #[Route(path: '/basket/remove/{id}', name: 'cart_remove_item', methods: ['POST'])]
    public function removeItem(CartItem $cartItem): Response
    {
        $cart = $this->cartResolver->peekCart();

        if ($cart && $cartItem->getCart() === $cart) {
            $cart->removeCartItem($cartItem);
            $this->cartRepository->save($cart, true);
            $this->addFlash('message', 'Removed from your basket');
        }

        return $this->redirectToRoute('cart');
    }

    #[Route(path: '/basket/update/{id}', name: 'cart_update_item', methods: ['POST'])]
    public function updateQuantity(CartItem $cartItem, Request $request): Response
    {
        $cart = $this->cartResolver->peekCart();

        if ($cart && $cartItem->getCart() === $cart) {
            $quantity = (int) $request->request->get('quantity', 1);

            if ($quantity <= 0) {
                $cart->removeCartItem($cartItem);
                $this->addFlash('message', 'Removed from your basket');
            } elseif ($this->basketRules->lampCount($cart, $cartItem) + $quantity > BasketRules::MAX_CHECKOUT_LAMPS && $quantity > ($cartItem->getQuantity() ?? 0)) {
                // Lowering is always allowed; raising past the checkout limit is not.
                $this->addFlash('error', BasketRules::LIMIT_MESSAGE);
            } else {
                $cartItem->setQuantity($quantity);
                $this->addFlash('message', 'Your basket has been updated');
            }

            $this->cartRepository->save($cart, true);
        }

        return $this->redirectToRoute('cart');
    }
}

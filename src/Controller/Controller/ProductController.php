<?php

namespace App\Controller\Controller;

use App\Entity\CartItem;
use App\Entity\CartItemOption;
use App\Entity\Product;
use App\Entity\ProductOption;
use App\Entity\ProductOptionValue;
use App\Form\Form\CartItemForm;
use App\Model\CartItemFormModel;
use App\Repository\CartRepository;
use App\Repository\ProductOptionValueRepository;
use App\Repository\ProductRepository;
use App\Service\BasketRules;
use App\Service\CartHelper;
use App\Service\CartResolver;
use Nette\Utils\Strings;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class ProductController extends AbstractController
{
    public function __construct(
        private readonly ProductRepository $productRepository,
        private readonly CartRepository $cartRepository,
        private readonly ProductOptionValueRepository $productOptionValueRepository,
        private readonly CartHelper $cartHelper,
        private readonly CartResolver $cartResolver,
        private readonly BasketRules $basketRules,
    )
    {
    }

    #[Route(path: '/products', name: 'products')]
    public function index(): Response
    {
        $products = $this->productRepository->findPublished();

        return $this->render('products/index.html.twig', [
            'products' => $products,
        ]);
    }

    /**
     * Product page with the add-to-basket form. Every add is its own basket
     * line (identical configurations are not merged). With ?edit=<line id>
     * the form is pre-filled from that basket line and saving updates it in
     * place ("Edit configuration"). When the basket rules say the order must
     * go by request (over the lamp limit, a Request size or a "Custom..."
     * value), the button reads "Reserve Your Design" and sending the form goes
     * to the request route with the whole order instead of adding to the
     * basket.
     */
    #[Route(path: '/products/{slug:product}', name: 'show_product')]
    public function show(Product $product, Request $request): Response
    {
        // Unpublished products are not publicly viewable.
        if (! $product->isPublished()) {
            throw $this->createNotFoundException();
        }

        $editing = $this->editableLine($request, $product);

        $cartItemFormModel = new CartItemFormModel();
        $cartItemFormModel->setQuantity($editing?->getQuantity() ?? 1);
        $cartItemForm = $this->createForm(CartItemForm::class, $cartItemFormModel, [
            'product' => $product,
        ]);
        if ($editing instanceof CartItem) {
            foreach ($editing->getCartItemOptions() as $chosen) {
                $field = Strings::webalize($chosen->getProductOption()->getName());
                if ($cartItemForm->has($field)) {
                    $cartItemForm->get($field)->setData($chosen->getProductOptionValue()->getId());
                }
            }
        }

        $cartItemForm->handleRequest($request);
        if ($cartItemForm->isSubmitted() && $cartItemForm->isValid()) {
            $cart = $this->cartResolver->getCart();
            $quantity = max(1, (int) $cartItemForm->get('quantity')->getData());

            $choices = [];
            foreach ($product->getProductOptions() as $productOption) {
                $valueId = $cartItemForm->get(Strings::webalize($productOption->getName()))->getData();
                $value = $valueId === null ? null : $this->productOptionValueRepository->findOneWithOption($valueId);
                if (! $value instanceof ProductOptionValue || $value->getProductOption() !== $productOption) {
                    throw $this->createNotFoundException();
                }

                $choices[] = [$productOption, $value];
            }

            $refusal = $this->basketRules->refusalForAdd($cart, $product, $quantity, array_column($choices, 1), $editing);
            if ($refusal !== null) {
                // "Reserve Your Design": the whole order goes to the request route
                // (a placeholder until Step 8). Nothing is added to the basket.
                return $this->redirectToRoute('basket_request');
            }

            $line = $editing ?? new CartItem(quantity: $quantity, product: $product);
            $line->setQuantity($quantity);
            $line->setUnitPrice($product->getPrice());
            $this->applyChoices($line, $choices);
            $line->setHash($this->cartHelper->generateCartItemHash($line));
            if (! $editing instanceof CartItem) {
                $cart->addCartItem($line);
            }

            $this->cartRepository->save(cart: $cart, flush: true);

            $this->addFlash('message', $editing instanceof CartItem ? 'Your basket has been updated' : 'Added to your basket');

            return $this->redirectToRoute('cart');
        }

        // Starting state for the purchase route (the page updates it as options change).
        $cart = $this->cartResolver->peekCart();
        $basketLamps = $cart === null ? 0 : $this->basketRules->lampCount($cart, $editing);
        $reasons = [];
        if ($basketLamps + ($editing?->getQuantity() ?? 1) > BasketRules::MAX_CHECKOUT_LAMPS) {
            $reasons[] = BasketRules::LIMIT_MESSAGE;
        }

        if (! $product->isDirectOrder()) {
            $reasons[] = BasketRules::REQUEST_SIZE_MESSAGE;
        }

        return $this->render('products/show.html.twig', [
            'product' => $product,
            'cartItemForm' => $cartItemForm,
            'editing' => $editing,
            'basketLamps' => $basketLamps,
            'reserve' => $reasons !== [],
            'routeNote' => $this->basketRules->routeNote($reasons),
        ]);
    }

    /**
     * The basket line being edited (?edit=<id>), if it is in the visitor's
     * basket and is this product. Lines customised lamp by lamp can only be
     * edited in the configurator.
     */
    private function editableLine(Request $request, Product $product): ?CartItem
    {
        $id = (string) $request->query->get('edit', '');
        $cart = $this->cartResolver->peekCart();
        if ($id === '' || $cart === null) {
            return null;
        }

        foreach ($cart->getCartItems() as $line) {
            if ((string) $line->getId() === $id && $line->getProduct() === $product && ! $line->isCustomisedIndividually()) {
                return $line;
            }
        }

        return null;
    }

    /**
     * Set the line's options to the chosen values (one per option, for every
     * lamp). Existing rows are updated in place so the database never holds
     * two rows for the same option and lamp mid-save.
     *
     * @param list<array{ProductOption, ProductOptionValue}> $choices
     */
    private function applyChoices(CartItem $line, array $choices): void
    {
        $line->setCustomisedIndividually(false);

        $existing = [];
        foreach ($line->getCartItemOptions()->toArray() as $chosen) {
            if ($chosen->getLamp() === 0) {
                $existing[(string) $chosen->getProductOption()->getId()] = $chosen;
            } else {
                $line->removeCartItemOption($chosen);
            }
        }

        foreach ($choices as [$productOption, $value]) {
            $key = (string) $productOption->getId();
            if (isset($existing[$key])) {
                $existing[$key]->setProductOptionValue($value);
                unset($existing[$key]);
            } else {
                new CartItemOption(cartItem: $line, productOption: $productOption, productOptionValue: $value);
            }
        }

        foreach ($existing as $leftover) {
            $line->removeCartItemOption($leftover);
        }
    }
}

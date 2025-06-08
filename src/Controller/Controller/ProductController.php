<?php

namespace App\Controller\Controller;

use App\Entity\CartItem;
use App\Entity\CartItemOption;
use App\Entity\Identity;
use App\Entity\Product;
use App\Form\CartItemForm;
use App\Model\CartItemFormModel;
use App\Repository\CartRepository;
use App\Repository\ProductOptionValueRepository;
use App\Repository\ProductRepository;
use Nette\Utils\Strings;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

class ProductController extends AbstractController
{
    public function __construct(
        private readonly ProductRepository $productRepository,
        private readonly CartRepository $cartRepository,
        private readonly ProductOptionValueRepository $productOptionValueRepository
    )
    {
    }

    #[Route(path: '/products', name: 'products')]
    public function index(): Response
    {
        $products = $this->productRepository->findAll();

        return $this->render('products/index.html.twig', [
            'products' => $products,
        ]);
    }

    #[Route(path: '/products/{slug:product}', name: 'show_product')]
    public function show(Product $product, #[CurrentUser] Identity $identity, Request $request): Response
    {
        $cartItemFormModel = new CartItemFormModel();
        $cartItemForm = $this->createForm(CartItemForm::class, $cartItemFormModel, [
            'product' => $product,
        ]);

        $cartItemForm->handleRequest($request);
        if ($cartItemForm->isSubmitted() && $cartItemForm->isValid()) {
            $quantity = $cartItemForm->get('quantity')->getData();
            $cartItem = new CartItem(quantity: $quantity, product: $product);
            $identity->getCart()->addCartItem($cartItem);

            foreach ($product->getProductOptions() as $productOption) {
                $productOptionId = $cartItemForm->get(Strings::webalize($productOption->getName()))->getData();
                $productOptionValue = $this->productOptionValueRepository->find($productOptionId);
                $cartItemOption = new CartItemOption(cartItem: $cartItem, productOption: $productOption, productOptionValue: $productOptionValue);
                $cartItem->addCartItemOption($cartItemOption);
            }

            $this->cartRepository->save(cart: $cartItem->getCart());
            $this->addFlash('success', 'product added to cart');
            $this->redirectToRoute('show_product', [
                'slug' => $product->getSlug(),
            ]);
        }

        return $this->render('products/show.html.twig', [
            'product' => $product,
            'cartItemForm' => $cartItemForm,
        ]);
    }
}

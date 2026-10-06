<?php

declare(strict_types=1);

namespace App\Controller\Controller;

use App\Catalogue\LumenFormaCatalogue;
use App\Catalogue\LumenFormaConfiguration;
use App\Catalogue\LumenFormaConfigurator;
use App\Catalogue\LumenFormaPhotos;
use App\Entity\CartItem;
use App\Entity\CartItemOption;
use App\Entity\ProductOption;
use App\Repository\CartRepository;
use App\Service\BasketRules;
use App\Service\CartHelper;
use App\Service\CartResolver;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * The Lumen Forma configurator ("Lighting Designs"), built to the Site v4
 * prototype in sub-steps (Step 7). 7.1: page layout, hero and the options
 * panel frame with its six stages. 7.2: the stages' sizes, options and
 * quantity. 7.3: the photo beside the panel (size galleries, option photos,
 * "Image Coming Soon"). 7.4: Customise All / Individually. 7.5: the review,
 * the purchase route and adding the design to the basket.
 */
final class ConfiguratorController extends AbstractController
{
    /**
     * Configurator stages, in order (the last is the review).
     */
    public const STAGES = ['The Scale', 'The Pigment', 'The Cable', 'The Footing', 'The Light', 'Your Lumen Forma'];

    public function __construct(
        private readonly LumenFormaConfigurator $configurator,
        private readonly CartResolver $cartResolver,
        private readonly CartRepository $cartRepository,
        private readonly CartHelper $cartHelper,
        private readonly BasketRules $basketRules,
    ) {
    }

    /**
     * With ?edit=<basket line> ("Edit configuration" in the basket) the panel
     * opens on the review with that line's design loaded, including per-lamp
     * configurations; the button then updates the line in place.
     */
    #[Route(path: '/lighting-designs', name: 'lighting_designs', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $configurator = $this->configurator;
        $cart = $this->cartResolver->peekCart();
        $editing = $this->editableLine((string) $request->query->get('edit', ''));
        $sizes = $configurator->sizes();

        // Each size's own photos; sizes without any show "Image Coming Soon".
        $galleries = [];
        foreach ($sizes as $size) {
            $photos = LumenFormaPhotos::forSize($size['slug']);
            if ([] !== $photos) {
                $galleries[$size['slug']] = $photos;
            }
        }

        return $this->render('configurator/index.html.twig', [
            'stages' => self::STAGES,
            'sizes' => $sizes,
            'groups' => $configurator->groups(),
            'defaultSize' => LumenFormaCatalogue::DEFAULT_SIZE,
            'stageKeys' => array_merge(['scale'], array_values(ProductOption::STAGES), ['review']),
            'idlePhotos' => LumenFormaPhotos::forSize(LumenFormaPhotos::IDLE_SIZE),
            'galleries' => $galleries,
            'optionPhotos' => LumenFormaPhotos::forOptions(),
            'maxLamps' => LumenFormaCatalogue::MAX_LAMPS_PER_CONFIGURATION,
            'basketLamps' => null === $cart ? 0 : $this->basketRules->lampCount($cart, $editing),
            'editing' => $editing,
            'edit' => null === $editing ? null : $this->designOf($editing),
        ]);
    }

    /**
     * "Cast Your Lamp": add the design to the basket as one line (per-lamp
     * options when customised individually). The basket rules are checked
     * again here: a design that must be reserved (a Request size, a Custom
     * value, or more than the checkout lamp limit in the whole basket) goes
     * to the request route instead and nothing is added.
     */
    #[Route(path: '/lighting-designs/add', name: 'lighting_designs_add', methods: ['POST'])]
    public function add(Request $request): Response
    {
        if (! $this->isCsrfTokenValid('submit', (string) $request->request->get('_csrf_token'))) {
            $this->addFlash('error', 'Something went wrong; please try again.');

            return $this->redirectToRoute('lighting_designs');
        }

        try {
            $data = json_decode((string) $request->request->get('configuration'), true, 8, \JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            $data = null;
        }

        $configuration = $this->configurator->read($data);
        if (! $configuration instanceof LumenFormaConfiguration) {
            $this->addFlash('error', 'Please complete every choice for your lamp before adding it to your basket.');

            return $this->redirectToRoute('lighting_designs');
        }

        $cart = $this->cartResolver->getCart();
        $editing = $this->editableLine((string) $request->request->get('edit', ''));
        $refusal = $this->basketRules->refusalForAdd($cart, $configuration->product, $configuration->quantity, $configuration->values(), $editing);
        if (null !== $refusal) {
            // "Reserve Your Design" (a placeholder until Step 8). A line being
            // edited stays as it was.
            return $this->redirectToRoute('basket_request');
        }

        $line = $editing ?? new CartItem(quantity: $configuration->quantity, product: $configuration->product);
        $line->setProduct($configuration->product);
        $line->setQuantity($configuration->quantity);
        $line->setUnitPrice($configuration->product->getPrice());
        $line->setCustomisedIndividually($configuration->individually);
        $this->applyDesign($line, $configuration);
        $line->setHash($this->cartHelper->generateCartItemHash($line));
        if (! $editing instanceof CartItem) {
            $cart->addCartItem($line);
        }

        // No message: the basket page and the count in the nav show the change.
        $this->cartRepository->save(cart: $cart, flush: true);

        return $this->redirectToRoute('cart');
    }

    /**
     * A line in the visitor's basket that the configurator can edit (one of the
     * Lumen Forma sizes), or null.
     */
    private function editableLine(string $id): ?CartItem
    {
        $cart = $this->cartResolver->peekCart();
        if ('' === $id || null === $cart) {
            return null;
        }

        $slugs = array_column(LumenFormaCatalogue::SIZES, 'slug');
        foreach ($cart->getCartItems() as $line) {
            if ((string) $line->getId() === $id && \in_array($line->getProduct()?->getSlug(), $slugs, true)) {
                return $line;
            }
        }

        return null;
    }

    /**
     * A basket line's design in the configurator's terms: the shared choices
     * (lamp 0), or each lamp's when customised individually.
     *
     * @return array{id: string, size: string, quantity: int, mode: string, choices: array<string, string>, lamps: list<array<string, string>>}
     */
    private function designOf(CartItem $line): array
    {
        $quantity = $line->getQuantity() ?? 1;
        $choices = [];
        $lamps = $line->isCustomisedIndividually() ? array_fill(0, $quantity, []) : [];
        foreach ($line->getCartItemOptions() as $chosen) {
            $name = $chosen->getProductOption()->getName();
            $value = $chosen->getProductOptionValue()->getValue();
            if (0 === $chosen->getLamp()) {
                $choices[$name] = $value;
            } elseif (isset($lamps[$chosen->getLamp() - 1])) {
                $lamps[$chosen->getLamp() - 1][$name] = $value;
            }
        }

        return [
            'id' => (string) $line->getId(),
            'size' => (string) $line->getProduct()?->getSlug(),
            'quantity' => $quantity,
            'mode' => $line->isCustomisedIndividually() ? 'individual' : 'all',
            'choices' => $choices,
            'lamps' => $lamps,
        ];
    }

    /**
     * Set the line's options to the design, one row per option per lamp
     * (lamp 0 = every lamp). Existing rows are updated in place, so the
     * database never holds two rows for the same option and lamp mid-save.
     */
    private function applyDesign(CartItem $line, LumenFormaConfiguration $configuration): void
    {
        $existing = [];
        foreach ($line->getCartItemOptions() as $chosen) {
            $existing[$chosen->getProductOption()->getId() . ':' . $chosen->getLamp()] = $chosen;
        }

        foreach ($configuration->lamps as $index => $choices) {
            $lamp = $configuration->individually ? $index + 1 : 0;
            foreach ($choices as [$option, $value]) {
                $key = $option->getId() . ':' . $lamp;
                if (isset($existing[$key])) {
                    $existing[$key]->setProductOptionValue($value);
                    unset($existing[$key]);
                } else {
                    new CartItemOption(cartItem: $line, productOption: $option, productOptionValue: $value, lamp: $lamp);
                }
            }
        }

        foreach ($existing as $leftover) {
            $line->removeCartItemOption($leftover);
        }
    }
}

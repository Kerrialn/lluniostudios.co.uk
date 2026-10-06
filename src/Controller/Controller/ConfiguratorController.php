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

    #[Route(path: '/lighting-designs', name: 'lighting_designs', methods: ['GET'])]
    public function index(): Response
    {
        $configurator = $this->configurator;
        $cart = $this->cartResolver->peekCart();
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
            'basketLamps' => null === $cart ? 0 : $this->basketRules->lampCount($cart),
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
        $refusal = $this->basketRules->refusalForAdd($cart, $configuration->product, $configuration->quantity, $configuration->values());
        if (null !== $refusal) {
            // "Reserve Your Design" (a placeholder until Step 8).
            return $this->redirectToRoute('basket_request');
        }

        $line = new CartItem(quantity: $configuration->quantity, product: $configuration->product);
        $line->setUnitPrice($configuration->product->getPrice());
        $line->setCustomisedIndividually($configuration->individually);
        foreach ($configuration->lamps as $index => $choices) {
            foreach ($choices as [$option, $value]) {
                new CartItemOption(
                    cartItem: $line,
                    productOption: $option,
                    productOptionValue: $value,
                    lamp: $configuration->individually ? $index + 1 : 0,
                );
            }
        }

        $line->setHash($this->cartHelper->generateCartItemHash($line));
        $cart->addCartItem($line);
        $this->cartRepository->save(cart: $cart, flush: true);

        $this->addFlash('message', 'Added to your basket');

        return $this->redirectToRoute('cart');
    }
}

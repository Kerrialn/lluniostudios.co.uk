<?php

namespace App\Controller\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * The old shop's addresses (/products, /products/<slug>, /collections/<slug>
 * and anything under them), kept so old links and bookmarks still work. The
 * pages themselves are retired: every lamp is now chosen in the configurator.
 */
class OldShopController extends AbstractController
{
    #[Route(path: '/products/{rest<.*>?}', name: 'old_shop_products')]
    #[Route(path: '/collections/{rest<.*>?}', name: 'old_shop_collections')]
    public function redirectToLightingDesigns(): Response
    {
        return $this->redirectToRoute('lighting_designs', status: Response::HTTP_MOVED_PERMANENTLY);
    }
}

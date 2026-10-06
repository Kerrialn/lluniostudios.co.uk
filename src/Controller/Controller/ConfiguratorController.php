<?php

declare(strict_types=1);

namespace App\Controller\Controller;

use App\Catalogue\LumenFormaCatalogue;
use App\Catalogue\LumenFormaConfigurator;
use App\Catalogue\LumenFormaPhotos;
use App\Entity\ProductOption;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * The Lumen Forma configurator ("Lighting Designs"), built to the Site v4
 * prototype in sub-steps (Step 7). 7.1: page layout, hero and the options
 * panel frame with its six stages. 7.2: the stages' sizes, options and
 * quantity.
 */
final class ConfiguratorController extends AbstractController
{
    /**
     * Configurator stages, in order (the last is the review).
     */
    public const STAGES = ['The Scale', 'The Pigment', 'The Cable', 'The Footing', 'The Light', 'Your Lumen Forma'];

    #[Route(path: '/lighting-designs', name: 'lighting_designs', methods: ['GET'])]
    public function index(LumenFormaConfigurator $configurator): Response
    {
        return $this->render('configurator/index.html.twig', [
            'stages' => self::STAGES,
            'sizes' => $configurator->sizes(),
            'groups' => $configurator->groups(),
            'defaultSize' => LumenFormaCatalogue::DEFAULT_SIZE,
            'stageKeys' => array_merge(['scale'], array_values(ProductOption::STAGES), ['review']),
            'idlePhotos' => LumenFormaPhotos::forSize(LumenFormaPhotos::IDLE_SIZE),
        ]);
    }
}

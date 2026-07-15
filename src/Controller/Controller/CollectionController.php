<?php

namespace App\Controller\Controller;

use App\Entity\ProductCollection;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class CollectionController extends AbstractController
{
    #[Route(path: '/collections/{slug:collection}', name: 'show_collection')]
    public function show(ProductCollection $collection): Response
    {
        return $this->render('collections/show.html.twig', [
            'collection' => $collection,
        ]);
    }
}

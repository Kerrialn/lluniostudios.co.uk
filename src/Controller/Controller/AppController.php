<?php

namespace App\Controller\Controller;

use App\Entity\Product;
use App\Repository\ProductRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class AppController extends AbstractController
{

    public function __construct(
        private readonly ProductRepository $productRepository
    )
    {
    }

    #[Route('/', name: 'landing')]
    public function landing(): Response
    {
        $product = $this->productRepository->findOneBy(['slug' => 'iron-and-stone']);

        return $this->render('app/landing.html.twig', [
            'product' => $product
        ]);
    }

    #[Route('/maintenance', name: 'maintenance')]
    public function maintenance(): Response
    {
        return $this->render('app/maintenance.html.twig');
    }
}

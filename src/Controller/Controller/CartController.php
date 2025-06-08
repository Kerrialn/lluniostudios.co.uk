<?php

namespace App\Controller\Controller;

use App\Entity\Identity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

class CartController extends AbstractController
{
    #[Route(path: '/cart', name: 'cart')]
    public function cart(#[CurrentUser] Identity $identity): Response
    {
        return $this->render('cart/index.html.twig', [
            'cart' => $identity->getCart(),
        ]);
    }
}

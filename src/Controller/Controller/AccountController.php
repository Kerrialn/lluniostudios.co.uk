<?php

namespace App\Controller\Controller;

use App\Entity\User;
use App\Form\Form\SetPasswordForm;
use App\Repository\OrderRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route(path: '/account')]
#[IsGranted('ROLE_USER')]
class AccountController extends AbstractController
{
    public function __construct(
        private readonly OrderRepository $orderRepository,
    ) {
    }

    #[Route(path: '/orders', name: 'account_orders')]
    public function orders(): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        return $this->render('account/orders.html.twig', [
            'orders' => $this->orderRepository->findByUser($user),
            'needsPassword' => ! $user->hasPassword(),
        ]);
    }

    #[Route(path: '/orders/{orderNumber}', name: 'account_order_show')]
    public function orderShow(string $orderNumber): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $order = $this->orderRepository->findByOrderNumber($orderNumber);

        if ($order === null || $order->getUser() !== $user) {
            throw $this->createNotFoundException();
        }

        return $this->render('account/order_show.html.twig', [
            'order' => $order,
        ]);
    }

    #[Route(path: '/password', name: 'account_set_password')]
    public function setPassword(
        Request $request,
        UserPasswordHasherInterface $passwordHasher,
        EntityManagerInterface $entityManager,
    ): Response {
        /** @var User $user */
        $user = $this->getUser();

        $form = $this->createForm(SetPasswordForm::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $user->setPassword(
                $passwordHasher->hashPassword($user, (string) $form->get('plainPassword')->getData()),
            );
            $entityManager->flush();

            $this->addFlash('message', 'Your password has been set. You can now sign in any time to view your orders.');

            return $this->redirectToRoute('account_orders');
        }

        return $this->render('account/set_password.html.twig', [
            'form' => $form,
            'hasPassword' => $user->hasPassword(),
        ]);
    }
}

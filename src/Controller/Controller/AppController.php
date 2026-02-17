<?php

namespace App\Controller\Controller;

use App\Entity\NewsletterSubscriber;
use App\Enum\FlashMessageEnum;
use App\Form\Form\NewsletterSubscriberForm;
use App\Repository\NewsletterSubscriberRepository;
use App\Repository\ProductRepository;
use App\Service\CartHelper;
use App\Service\EmailService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class AppController extends AbstractController
{
    public function __construct(
        private readonly ProductRepository $productRepository,
        private readonly NewsletterSubscriberRepository $newsletterSubscriberRepository,
        private readonly EmailService $emailService
    )
    {
    }

    #[Route('/', name: 'landing')]
    public function landing(): Response
    {
        $product = $this->productRepository->findOneBy([
            'slug' => 'iron-and-stone',
        ]);

        return $this->render('app/landing.html.twig', [
            'product' => $product,
        ]);
    }

    #[Route('/newsletter/verify/{token}', name: 'newsletter_verify_email', methods: ['GET'])]
    public function verifyNewsletterEmail(string $token): Response
    {
        $subscriber = $this->newsletterSubscriberRepository->findOneByConfirmationToken($token);

        if (!$subscriber) {
            $this->addFlash(FlashMessageEnum::ERROR->value, 'Invalid or expired confirmation link.');

            return $this->redirectToRoute('maintenance');
        }

        $subscriber->confirm();
        $this->newsletterSubscriberRepository->save($subscriber, true);
        $this->addFlash(FlashMessageEnum::SUSCCESS->value, 'Your email has been confirmed. You will be notified of product updates.');

        return $this->redirectToRoute('maintenance');
    }

    #[Route('/maintenance', name: 'maintenance')]
    public function maintenance(
        Request $request
    ): Response
    {
        $newsletterSubscriber = new NewsletterSubscriber();
        $newsletterSubscriberForm = $this->createForm(NewsletterSubscriberForm::class, $newsletterSubscriber);

        $newsletterSubscriberForm->handleRequest($request);
        if($newsletterSubscriberForm->isSubmitted() && $newsletterSubscriberForm->isValid()){

            $this->emailService->sendNewsLetterSubscriptionEmail($newsletterSubscriber, [
                'token' => $newsletterSubscriber->getConfirmationToken(),
            ]);
            $this->newsletterSubscriberRepository->save($newsletterSubscriber, true);
            $this->addFlash(FlashMessageEnum::SUSCCESS->value, 'Thank you for subscribing to our newsletter, we have sent you an email to confirm your subscription');

            return $this->redirectToRoute('maintenance');
        }

        return $this->render('app/maintenance.html.twig', [
            'newsletterSubscriberForm' => $newsletterSubscriberForm
        ]);
    }
}

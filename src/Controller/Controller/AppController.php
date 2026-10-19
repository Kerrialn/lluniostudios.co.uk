<?php

namespace App\Controller\Controller;

use App\Entity\NewsletterSubscriber;
use App\Enum\FlashMessageEnum;
use App\Form\Form\NewsletterSubscriberForm;
use App\Repository\NewsletterSubscriberRepository;
use App\Service\EmailService;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class AppController extends AbstractController
{
    private const ALREADY_SIGNED_UP = "You're already on the list";

    public function __construct(
        private readonly NewsletterSubscriberRepository $newsletterSubscriberRepository,
        private readonly EmailService $emailService,
        private readonly LoggerInterface $logger
    )
    {
    }

    #[Route('/', name: 'landing')]
    public function landing(): Response
    {
        return $this->render('app/landing.html.twig');
    }

    #[Route('/workshop', name: 'workshop', methods: ['GET'])]
    public function workshop(): Response
    {
        return $this->render('app/workshop.html.twig');
    }

    #[Route('/contact', name: 'contact', methods: ['GET'])]
    public function contact(): Response
    {
        return $this->render('app/contact.html.twig');
    }

    #[Route('/terms-and-conditions', name: 'terms', methods: ['GET'])]
    public function terms(): Response
    {
        return $this->render('app/terms.html.twig');
    }

    /**
     * The Privacy Policy (Step 11d, 14 October 2026).
     */
    #[Route('/privacy-policy', name: 'privacy', methods: ['GET'])]
    public function privacy(): Response
    {
        return $this->render('app/privacy.html.twig');
    }

    #[Route('/newsletter/verify/{token}', name: 'newsletter_verify_email', methods: ['GET'])]
    public function verifyNewsletterEmail(string $token): Response
    {
        $subscriber = $this->newsletterSubscriberRepository->findOneByConfirmationToken($token);

        if (! $subscriber instanceof NewsletterSubscriber) {
            $this->addFlash(FlashMessageEnum::ERROR->value, 'Invalid or expired confirmation link.');

            return $this->redirectToRoute('signup');
        }

        $subscriber->confirm();
        $this->newsletterSubscriberRepository->save($subscriber, true);
        $this->addFlash(FlashMessageEnum::SUSCCESS->value, 'Your email has been confirmed. You will be notified of product updates.');

        return $this->redirectToRoute('signup');
    }

    // The signup page's old address; kept so existing links still work.
    #[Route('/maintenance', name: 'maintenance')]
    public function maintenance(): RedirectResponse
    {
        return $this->redirectToRoute('signup', status: Response::HTTP_MOVED_PERMANENTLY);
    }

    #[Route('/signup', name: 'signup')]
    public function signup(
        Request $request
    ): Response
    {
        $newsletterSubscriber = new NewsletterSubscriber();
        $newsletterSubscriberForm = $this->createForm(NewsletterSubscriberForm::class, $newsletterSubscriber);

        $newsletterSubscriberForm->handleRequest($request);
        if($newsletterSubscriberForm->isSubmitted() && $newsletterSubscriberForm->isValid()){

            // Already signed up: say so, and send nothing (no second confirmation or notification).
            if ($this->newsletterSubscriberRepository->findOneByEmail($newsletterSubscriber->getEmail()) instanceof NewsletterSubscriber) {
                $this->addFlash(FlashMessageEnum::SUSCCESS->value, self::ALREADY_SIGNED_UP);

                return $this->redirectToRoute('signup');
            }

            try {
                $this->newsletterSubscriberRepository->save($newsletterSubscriber, true);
            } catch (UniqueConstraintViolationException) {
                // The same email sent twice at once: the other request saved it.
                $this->addFlash(FlashMessageEnum::SUSCCESS->value, self::ALREADY_SIGNED_UP);

                return $this->redirectToRoute('signup');
            }

            // Saved first, so a mail provider refusing the confirmation never loses the signup.
            $confirmationSent = true;
            try {
                $this->emailService->sendNewsLetterSubscriptionEmail($newsletterSubscriber, [
                    'token' => $newsletterSubscriber->getConfirmationToken(),
                ]);
            } catch (\Throwable $throwable) {
                $confirmationSent = false;
                $this->logger->error('Signup confirmation email failed: {message}', [
                    'message' => $throwable->getMessage(),
                    'email' => $newsletterSubscriber->getEmail(),
                    'exception' => $throwable,
                ]);
            }

            // Tell the studio; the signup is already saved, so a failed send is only logged.
            try {
                $this->emailService->sendSignupNotificationEmail($newsletterSubscriber);
            } catch (\Throwable $throwable) {
                $this->logger->error('Signup notification email failed: {message}', [
                    'message' => $throwable->getMessage(),
                    'email' => $newsletterSubscriber->getEmail(),
                    'exception' => $throwable,
                ]);
            }

            $this->addFlash(FlashMessageEnum::SUSCCESS->value, $confirmationSent
                ? 'Thank you for subscribing to our newsletter, we have sent you an email to confirm your subscription'
                : "Thank you for subscribing to our newsletter. We couldn't send your confirmation email just now, but your details are saved and we'll be in touch.");

            return $this->redirectToRoute('signup');
        }

        return $this->render('app/signup.html.twig', [
            'newsletterSubscriberForm' => $newsletterSubscriberForm,
        ]);
    }
}

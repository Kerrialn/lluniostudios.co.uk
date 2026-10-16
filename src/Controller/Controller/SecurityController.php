<?php

declare(strict_types=1);

namespace App\Controller\Controller;

use App\Entity\User;
use App\Form\Type\PinCodeType;
use App\Repository\UserRepository;
use App\Service\LoginCodeService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
use Symfony\Component\Security\Http\LoginLink\Exception\InvalidLoginLinkAuthenticationException;
use Symfony\Contracts\Translation\TranslatorInterface;

class SecurityController extends AbstractController
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly LoginCodeService $loginCodeService,
        private readonly TranslatorInterface $translator,
    ) {
    }

    /**
     * Passwordless sign-in. Step 1 collects the email and sends the sign-in
     * email (a one-click link, and a 6-digit code as a backup); step 2
     * (email + code) is intercepted by EmailCodeAuthenticator.
     */
    #[Route(path: '/login', name: 'app_login', methods: ['GET', 'POST'])]
    public function login(Request $request, AuthenticationUtils $authenticationUtils): Response
    {
        if ($this->getUser() instanceof User) {
            return $this->redirectToRoute('account_orders');
        }

        $session = $request->getSession();

        // "Use a different email" resets back to the email step.
        if ($request->query->has('reset')) {
            $session->remove(\Symfony\Component\Security\Http\SecurityRequestAttributes::LAST_USERNAME);

            return $this->redirectToRoute('app_login');
        }

        $email = $authenticationUtils->getLastUsername();
        $step = 'email';

        // Step 1: an email-only POST requests a code (the code-submit POST is
        // handled by the authenticator and never reaches here).
        if ($request->isMethod('POST') && $request->request->get('pin_code', '') === '') {
            $email = strtolower(trim((string) $request->request->get('email', '')));

            if (! $this->isCsrfTokenValid('authenticate', (string) $request->request->get('_csrf_token', ''))) {
                $this->addFlash('error', 'Something went wrong. Please try again.');
            } elseif ($email === '') {
                $this->addFlash('error', 'Please enter your email address.');
            } else {
                $user = $this->userRepository->findOneBy([
                    'email' => $email,
                ]);

                // Only send if the account exists, but never reveal which is which.
                $sent = true;
                if ($user instanceof User) {
                    try {
                        $this->loginCodeService->request($user);
                    } catch (\Throwable $throwable) {
                        // Mail transport down (e.g. banned SMTP account). Don't 500 —
                        // let the customer retry. The mailer already logs the cause.
                        $sent = false;
                    }
                }

                if ($sent) {
                    $this->addFlash('message', 'If that email has an account, we\'ve sent a sign-in link and code.');
                    $step = 'code';
                } else {
                    $this->addFlash('error', 'We couldn\'t send your code right now. Please try again in a moment.');
                }
            }
        }

        // Surface authenticator failures as an error: an expired or changed
        // sign-in link starts again; a wrong or expired code stays on the code step.
        $error = $authenticationUtils->getLastAuthenticationError();
        if ($error instanceof InvalidLoginLinkAuthenticationException) {
            $this->addFlash('error', 'That sign-in link has expired. Please enter your email for a new one.');
        } elseif ($error instanceof \Symfony\Component\Security\Core\Exception\AuthenticationException) {
            $this->addFlash('error', $this->translator->trans($error->getMessageKey(), $error->getMessageData(), 'security'));
            $step = 'code';
        }

        return $this->render('security/login.html.twig', [
            'email' => $email,
            'step' => $step,
            // required:false so the hidden input isn't a non-focusable required
            // field; the code is validated server-side by EmailCodeAuthenticator.
            'pinForm' => $this->createForm(PinCodeType::class, null, [
                'required' => false,
            ])->createView(),
        ]);
    }

    /**
     * Where the sign-in email's button leads; Symfony's login link
     * authenticator signs the customer in here.
     */
    #[Route(path: '/login/check', name: 'login_check')]
    public function check(): never
    {
        throw new \LogicException('Handled by the login_link authenticator on the firewall.');
    }

    #[Route(path: '/logout', name: 'app_logout')]
    public function logout(): void
    {
        throw new \LogicException('This method can be blank - it will be intercepted by the logout key on your firewall.');
    }
}

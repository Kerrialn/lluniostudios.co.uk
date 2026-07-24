<?php

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
use Symfony\Contracts\Translation\TranslatorInterface;

class SecurityController extends AbstractController
{
    /**
     * Set by CheckoutController when a returning customer must verify a code to
     * continue checkout — jumps the login page straight to the code step.
     */
    public const SESSION_PENDING_EMAIL = 'login_pending_email';

    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly LoginCodeService $loginCodeService,
        private readonly TranslatorInterface $translator,
    ) {
    }

    /**
     * Passwordless login. Step 1 collects the email and emails a 6-digit code;
     * step 2 (email + code) is intercepted by EmailCodeAuthenticator.
     */
    #[Route(path: '/login', name: 'app_login', methods: ['GET', 'POST'])]
    public function login(Request $request, AuthenticationUtils $authenticationUtils): Response
    {
        if ($this->getUser() instanceof User) {
            $request->getSession()->remove(self::SESSION_PENDING_EMAIL);

            return $this->redirectToRoute('account_orders');
        }

        $session = $request->getSession();

        // "Use a different email" resets back to the email step.
        if ($request->query->has('reset')) {
            $session->remove(self::SESSION_PENDING_EMAIL);
            $session->remove(\Symfony\Component\Security\Http\SecurityRequestAttributes::LAST_USERNAME);

            return $this->redirectToRoute('app_login');
        }

        $pendingEmail = (string) $session->get(self::SESSION_PENDING_EMAIL, '');
        $email = $authenticationUtils->getLastUsername() ?: $pendingEmail;
        $step = ($pendingEmail !== '') ? 'code' : 'email';

        // Step 1: an email-only POST requests a code (the code-submit POST is
        // handled by the authenticator and never reaches here).
        if ($request->isMethod('POST') && $request->request->get('pin_code', '') === '') {
            $email = strtolower(trim((string) $request->request->get('email', '')));

            if (! $this->isCsrfTokenValid('authenticate', (string) $request->request->get('_csrf_token', ''))) {
                $this->addFlash('error', 'Invalid session. Please try again.');
            } elseif ($email === '') {
                $this->addFlash('error', 'Please enter your email address.');
            } else {
                $user = $this->userRepository->findOneBy([
                    'email' => $email,
                ]);
                // Only send if the account exists, but never reveal which is which.
                if ($user instanceof User) {
                    $this->loginCodeService->request($user);
                }
                $session->remove(self::SESSION_PENDING_EMAIL);
                $this->addFlash('message', 'If that email has an account, we\'ve sent a 6-digit sign-in code.');
                $step = 'code';
            }
        }

        // Surface authenticator failures (bad/expired code) as an error toast.
        $error = $authenticationUtils->getLastAuthenticationError();
        if ($error instanceof \Symfony\Component\Security\Core\Exception\AuthenticationException) {
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

    #[Route(path: '/logout', name: 'app_logout')]
    public function logout(): void
    {
        throw new \LogicException('This method can be blank - it will be intercepted by the logout key on your firewall.');
    }
}

<?php

declare(strict_types=1);

namespace App\Security;

use App\Repository\UserRepository;
use App\Service\LoginCodeService;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractLoginFormAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\CsrfTokenBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\RememberMeBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Symfony\Component\Security\Http\SecurityRequestAttributes;
use Symfony\Component\Security\Http\Util\TargetPathTrait;

/**
 * Authenticates a user from a 6-digit code emailed to them (passwordless).
 *
 * Only handles the code-submission POST (email + code). The email-only POST that
 * requests a code falls through to SecurityController.
 */
class EmailCodeAuthenticator extends AbstractLoginFormAuthenticator
{
    use TargetPathTrait;

    public const LOGIN_ROUTE = 'app_login';

    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly LoginCodeService $loginCodeService,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public function supports(Request $request): bool
    {
        return $request->isMethod('POST')
            && $request->getPathInfo() === $this->getLoginUrl($request)
            && $request->request->get('pin_code', '') !== '';
    }

    public function authenticate(Request $request): Passport
    {
        $email = strtolower(trim((string) $request->request->get('email', '')));
        $code = trim((string) $request->request->get('pin_code', ''));
        $csrfToken = (string) $request->request->get('_csrf_token', '');

        $request->getSession()->set(SecurityRequestAttributes::LAST_USERNAME, $email);

        $user = $email === '' ? null : $this->userRepository->findOneBy([
            'email' => $email,
        ]);

        if ($user === null || ! $this->loginCodeService->verify($user, $code)) {
            throw new CustomUserMessageAuthenticationException('That code is invalid or has expired. Please request a new one.');
        }

        return new SelfValidatingPassport(
            new UserBadge($email),
            [
                new CsrfTokenBadge('authenticate', $csrfToken),
                (new RememberMeBadge())->enable(),
            ],
        );
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        $targetPath = $this->getTargetPath($request->getSession(), $firewallName);
        if ($targetPath !== null) {
            return new RedirectResponse($targetPath);
        }

        return new RedirectResponse($this->urlGenerator->generate('account_orders'));
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): Response
    {
        $request->getSession()->set(SecurityRequestAttributes::AUTHENTICATION_ERROR, $exception);

        return new RedirectResponse($this->getLoginUrl($request));
    }

    protected function getLoginUrl(Request $request): string
    {
        return $this->urlGenerator->generate(self::LOGIN_ROUTE);
    }
}

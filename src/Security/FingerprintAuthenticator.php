<?php

declare(strict_types=1);

namespace App\Security;

use App\Entity\Fingerprint;
use App\Entity\UnregisteredUser;
use App\Entity\User;
use App\Repository\FingerprintRepository;
use App\Service\FingerPrintService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Symfony\Component\Security\Http\EntryPoint\AuthenticationEntryPointInterface;
use Symfony\Component\Security\Http\Util\TargetPathTrait;

class FingerprintAuthenticator extends AbstractAuthenticator implements AuthenticationEntryPointInterface
{
    use TargetPathTrait;

    public function __construct(
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly FingerprintRepository $fingerprintRepository,
        private readonly FingerPrintService $fingerPrintService,
        private readonly Security $security,
        private readonly EntityManagerInterface $entityManager
    )
    {
    }

    /**
     * Only supports fingerprint if no email is submitted in the request
     */
    public function supports(Request $request): bool
    {
        $path = $request->getPathInfo();

        // 1) Skip if user already logged in
        if ($this->security->getUser() instanceof UserInterface) {
            return false;
        }
        // 2) Don’t run on your login form or any non‐protected routes
        // 3) Otherwise run fingerprint logic
        return ! in_array($path, [
            $this->urlGenerator->generate('app_login'),
            '/_profiler', '/_wdt', // etc.
        ], true);
    }

    public function authenticate(Request $request): Passport
    {
        $token = $this->fingerPrintService->generate($request);
        return new SelfValidatingPassport(
            new UserBadge($token, function (string $t) {
                $fingerprint = $this->fingerprintRepository->findOneBy([
                    'fingerprint' => $t,
                ]);
                if ($fingerprint === null) {
                    $fingerprint = new Fingerprint($t);
                    $this->entityManager->persist($fingerprint);
                }

                if (! $fingerprint->getOwner()) {
                    $unregisteredUser = new UnregisteredUser();
                    $unregisteredUser->addFingerprint($fingerprint);
                    $this->entityManager->persist($unregisteredUser);
                    $this->entityManager->flush();
                    return $unregisteredUser;
                }
                return $fingerprint->getOwner();
            })
        );
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): null|RedirectResponse
    {
        $targetPath = $this->getTargetPath($request->getSession(), $firewallName);
        if ($targetPath) {
            return new RedirectResponse($targetPath);
        }

        return new RedirectResponse($this->urlGenerator->generate('landing'));
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $authenticationException): ?Response
    {
        return null;
    }

    public function start(Request $request, ?AuthenticationException $authenticationException = null): Response
    {
        return new RedirectResponse($this->urlGenerator->generate('app_login'));
    }
}

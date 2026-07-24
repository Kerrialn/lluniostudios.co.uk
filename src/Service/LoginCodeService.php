<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\LoginCode;
use App\Entity\User;
use App\Repository\LoginCodeRepository;
use Carbon\CarbonImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Issues and verifies one-time 6-digit email codes for passwordless login.
 *
 * Codes are stored hashed (HMAC-SHA256 with the app secret), expire after a few
 * minutes, allow a limited number of attempts and are single-use. Only the most
 * recently issued code for a user can be valid.
 */
final readonly class LoginCodeService
{
    private const TTL_MINUTES = 10;

    private const MAX_ATTEMPTS = 5;

    private const RESEND_THROTTLE_SECONDS = 30;

    public function __construct(
        private LoginCodeRepository $loginCodeRepository,
        private EmailService $emailService,
        private EntityManagerInterface $entityManager,
        #[Autowire('%kernel.secret%')]
        private string $appSecret,
    ) {
    }

    /**
     * Generate a fresh code for the user and email it. Throttled so rapid repeat
     * requests reuse the current code instead of spamming the inbox.
     */
    public function request(User $user): void
    {
        $existing = $this->loginCodeRepository->findLatestActiveForUser($user);
        if ($existing instanceof LoginCode
            && ! $existing->isExpired()
            && $existing->getCreatedAt()->diffInSeconds(CarbonImmutable::now()) < self::RESEND_THROTTLE_SECONDS) {
            return;
        }

        $this->loginCodeRepository->consumeAllForUser($user);

        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $loginCode = new LoginCode(
            $user,
            $this->hash($code),
            CarbonImmutable::now()->addMinutes(self::TTL_MINUTES),
        );

        $this->entityManager->persist($loginCode);
        $this->entityManager->flush();

        $this->emailService->sendLoginCodeEmail($user, $code);
    }

    /**
     * Verify a submitted code for the user. Consumes the code on success and
     * counts a failed attempt otherwise.
     */
    public function verify(User $user, string $code): bool
    {
        $code = trim($code);
        if (! preg_match('/^\d{6}$/', $code)) {
            return false;
        }

        $loginCode = $this->loginCodeRepository->findLatestActiveForUser($user);
        if (! $loginCode instanceof LoginCode
            || $loginCode->isExpired()
            || $loginCode->getAttempts() >= self::MAX_ATTEMPTS) {
            return false;
        }

        if (! hash_equals($loginCode->getCodeHash(), $this->hash($code))) {
            $loginCode->incrementAttempts();
            $this->entityManager->flush();

            return false;
        }

        $loginCode->consume();
        $this->entityManager->flush();

        return true;
    }

    private function hash(string $code): string
    {
        return hash_hmac('sha256', $code, $this->appSecret);
    }
}

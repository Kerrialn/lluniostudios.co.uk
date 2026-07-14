<?php

namespace App\Provider;

use App\Entity\Identity;
use App\Entity\UnregisteredUser;
use App\Repository\FingerprintRepository;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

/**
 * @template Identity of UserInterface
 * @implements UserProviderInterface<Identity>
 */
class FingerprintUserProvider implements UserProviderInterface
{
    public function __construct(
        private FingerprintRepository $fingerprintRepository
    ) {}

    /**
     * Symfony calls this when it needs to load the “user” by the string
     * identifier you passed into the UserBadge (i.e. your fingerprint token).
     */
    public function loadUserByIdentifier(string $identifier): UserInterface
    {
        $fingerprint = $this->fingerprintRepository->findOneBy([
            'fingerprint' => $identifier,
        ]);

        if (! $fingerprint || ! $fingerprint->getOwner()) {
            throw new UserNotFoundException('No guest for fingerprint ' . $identifier);
        }

        return $fingerprint->getOwner();
    }

    public function loadUserByUsername(string $username): UserInterface
    {
        return $this->loadUserByIdentifier($username);
    }

    public function refreshUser(UserInterface $user): UserInterface
    {
        // Only this provider's guests are our concern; registered users are
        // refreshed by the entity provider in the chain.
        if (! $user instanceof UnregisteredUser) {
            return $user;
        }

        // Reload a managed instance for the current request's EntityManager.
        // Returning the detached, session-deserialized $user causes Doctrine to
        // treat its cascaded Fingerprint as new on the next flush, producing a
        // duplicate-key violation.
        return $this->loadUserByIdentifier($user->getUserIdentifier());
    }

    public function supportsClass(string $class): bool
    {
        return is_a($class, UnregisteredUser::class, true);
    }
}

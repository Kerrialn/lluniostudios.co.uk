<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Every order belongs to the account for its email (decided 7 October 2026):
 * the existing account, or a new one created quietly (no password, nobody
 * signed in). Customers can sign in later to see their orders (Step 10.6).
 */
final readonly class OrderAccounts
{
    public function __construct(
        private UserRepository $userRepository,
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function accountFor(string $email, ?string $name = null): User
    {
        $email = strtolower(trim($email));
        $user = $this->userRepository->findOneBy([
            'email' => $email,
        ]);
        if ($user instanceof User) {
            return $user;
        }

        $user = new User($email);
        $parts = preg_split('/\s+/', trim((string) $name)) ?: [];
        $parts = array_values(array_filter($parts, static fn (string $part): bool => $part !== ''));
        if ($parts !== []) {
            $user->setFirstName(array_shift($parts));
            $user->setLastName($parts !== [] ? implode(' ', $parts) : null);
        }

        $this->entityManager->persist($user);

        return $user;
    }
}

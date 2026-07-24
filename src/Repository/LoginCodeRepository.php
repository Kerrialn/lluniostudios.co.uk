<?php

namespace App\Repository;

use App\Entity\LoginCode;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<LoginCode>
 */
class LoginCodeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $managerRegistry)
    {
        parent::__construct($managerRegistry, LoginCode::class);
    }

    /**
     * The most recent unconsumed code for a user, if any.
     */
    public function findLatestActiveForUser(User $user): ?LoginCode
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.user = :user')
            ->andWhere('c.consumedAt IS NULL')
            ->setParameter('user', $user)
            ->orderBy('c.createdAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Mark every outstanding code for a user as consumed, so only the newest
     * issued code can ever be valid.
     */
    public function consumeAllForUser(User $user): void
    {
        $this->createQueryBuilder('c')
            ->update()
            ->set('c.consumedAt', ':now')
            ->andWhere('c.user = :user')
            ->andWhere('c.consumedAt IS NULL')
            ->setParameter('now', new \Carbon\CarbonImmutable())
            ->setParameter('user', $user)
            ->getQuery()
            ->execute();
    }
}

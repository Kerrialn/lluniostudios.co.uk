<?php

namespace App\Repository;

use App\Entity\UnregisteredUser;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<UnregisteredUser>
 */
class UnregisteredUserRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $managerRegistry)
    {
        parent::__construct($managerRegistry, UnregisteredUser::class);
    }

    public function save(UnregisteredUser $unregisteredUser, bool $flush = false): void
    {
        $this->getEntityManager()->persist($unregisteredUser);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(UnregisteredUser $unregisteredUser, bool $flush = false): void
    {
        $this->getEntityManager()->remove($unregisteredUser);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}

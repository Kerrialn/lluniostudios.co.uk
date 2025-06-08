<?php

namespace App\Repository;

use App\Entity\PhoneNumber;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PhoneNumber>
 */
class PhoneNumberRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $managerRegistry)
    {
        parent::__construct($managerRegistry, PhoneNumber::class);
    }

    public function save(PhoneNumber $phoneNumber, bool $flush = false): void
    {
        $this->getEntityManager()->persist($phoneNumber);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(PhoneNumber $phoneNumber, bool $flush = false): void
    {
        $this->getEntityManager()->remove($phoneNumber);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}

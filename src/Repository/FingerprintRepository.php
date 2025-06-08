<?php

namespace App\Repository;

use App\Entity\Fingerprint;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Fingerprint>
 */
class FingerprintRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $managerRegistry)
    {
        parent::__construct($managerRegistry, Fingerprint::class);
    }

    public function save(Fingerprint $fingerprint, bool $flush = false): void
    {
        $this->getEntityManager()->persist($fingerprint);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Fingerprint $fingerprint, bool $flush = false): void
    {
        $this->getEntityManager()->remove($fingerprint);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}

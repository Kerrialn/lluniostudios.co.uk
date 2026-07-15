<?php

namespace App\Repository;

use App\Entity\Address;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Address>
 */
class AddressRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $managerRegistry)
    {
        parent::__construct($managerRegistry, Address::class);
    }

    public function save(Address $address, bool $flush = false): void
    {
        $this->getEntityManager()->persist($address);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Address $address, bool $flush = false): void
    {
        $this->getEntityManager()->remove($address);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}

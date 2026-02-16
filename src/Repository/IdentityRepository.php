<?php

// src/Repository/IdentityRepository.php

namespace App\Repository;

use App\Entity\Identity;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends \Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository<\App\Entity\Identity>
 */
class IdentityRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $managerRegistry)
    {
        parent::__construct($managerRegistry, Identity::class);
    }

    public function findOneByEmail(string $email): ?Identity
    {
        return $this->createQueryBuilder('identity')
            ->andWhere('identity.email = :email')
            ->setParameter('email', $email)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * @throws NonUniqueResultException
     */
    public function findOneByFullPhone(string $fullPhone): null|Identity
    {
        $queryBuilder = $this->createQueryBuilder('identity');
        $queryBuilder->join('identity.phoneNumbers', 'phoneNumber');

        $queryBuilder->andWhere("CONCAT(phoneNumber.dialCode, phoneNumber.number) = :fullPhoneNumber")
            ->setParameter('fullPhoneNumber', $fullPhone);

        return $queryBuilder->setMaxResults(1)->getQuery()->getOneOrNullResult();
    }

    /**
     * @return array<int, string>
     */
    public function findByInternetProtocolAddress(string $internetProtocolAddress): array
    {
        return $this->createQueryBuilder('identity')
            ->join('identity.internetProtocols', 'internet_protocols')
            ->andWhere('internet_protocols.address = :ip')
            ->setParameter('ip', $internetProtocolAddress)
            ->getQuery()
            ->getResult();
    }

    public function save(Identity $identity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($identity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Identity $identity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($identity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}

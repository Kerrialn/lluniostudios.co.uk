<?php

namespace App\Repository;

use App\Entity\ProductCollection;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ProductCollection>
 */
class ProductCollectionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $managerRegistry)
    {
        parent::__construct($managerRegistry, ProductCollection::class);
    }

    public function save(ProductCollection $collection, bool $flush = false): void
    {
        $this->getEntityManager()->persist($collection);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(ProductCollection $collection, bool $flush = false): void
    {
        $this->getEntityManager()->remove($collection);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * The collection to feature on the landing page (latest one that has a slug,
     * so it is linkable).
     */
    public function findFeatured(): ?ProductCollection
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.slug IS NOT NULL')
            ->orderBy('c.createdAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}

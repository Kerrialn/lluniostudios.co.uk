<?php

namespace App\Repository;

use App\Entity\ProductOptionValue;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;
use function Doctrine\ORM\QueryBuilder;

/**
 * @extends ServiceEntityRepository<ProductOptionValue>
 */
class ProductOptionValueRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $managerRegistry)
    {
        parent::__construct($managerRegistry, ProductOptionValue::class);
    }

    public function findOneWithOption(Uuid $id): null|ProductOptionValue
    {
        $qb = $this->createQueryBuilder('productOptionValue');
        $qb->leftJoin('productOptionValue.productOption', 'productOption')
            ->andWhere(
                $qb->expr()->eq('productOptionValue.id', ':id')
            )->setParameter('id', $id);

        return $qb->getQuery()->getOneOrNullResult();
    }
}

<?php

namespace App\Repository;

use App\Entity\CartItem;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

/**
 * @extends ServiceEntityRepository<CartItem>
 */
class CartItemRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $managerRegistry)
    {
        parent::__construct($managerRegistry, CartItem::class);
    }

    /**
     * @return array<int, CartItem>
     */
    public function findByCart(Uuid $id): array
    {
        $qb = $this->createQueryBuilder('cart_item');
        $qb->leftJoin('cart_item.cart', 'cart');
        $qb->andWhere(
            $qb->expr()->eq('cart.id', ':id')
        )->setParameter('id', $id, 'uuid');

        return $qb->getQuery()->getResult();
    }
}

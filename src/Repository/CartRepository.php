<?php

namespace App\Repository;

use App\Entity\Cart;
use App\Entity\Identity;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;
use function Doctrine\ORM\QueryBuilder;

/**
 * @extends ServiceEntityRepository<Cart>
 */
class CartRepository extends ServiceEntityRepository
{
    public function __construct(
        ManagerRegistry $managerRegistry,
    )
    {
        parent::__construct($managerRegistry, Cart::class);
    }

    public function save(Cart $cart, bool $flush = false): void
    {
        $this->getEntityManager()->persist($cart);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Cart $cart, bool $flush = false): void
    {
        $this->getEntityManager()->remove($cart);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function findByIdentity(Uuid $id): null|Cart
    {
        $qb = $this->createQueryBuilder('cart');
        $qb->leftJoin('cart.identity', 'identity');

        $qb->andWhere(
            $qb->expr()->eq('identity.id', ':id')
        )->setParameter('id', $id);

        return $qb->getQuery()->getOneOrNullResult();
    }

    public function findOrCreate(Identity $identity): Cart
    {


        $qb = $this->createQueryBuilder('cart');
        $qb->leftJoin('cart.identity', 'identity');


            $qb->andWhere(
                $qb->expr()->eq('identity.id', ':id')
            )->setParameter('id', $identity->getId());

        $cart = $qb->getQuery()->getOneOrNullResult();


        if (!$cart instanceof Cart) {
            $cart = new Cart();
            $identity->setCart($cart);
        }

        return $cart;
    }
}

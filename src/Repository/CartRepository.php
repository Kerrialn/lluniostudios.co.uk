<?php

namespace App\Repository;

use App\Entity\Cart;
use App\Entity\Identity;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

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

    public function findByIdentity(Identity $identity): null|Cart
    {
        // Identity owns the relationship (has cart_id FK), so use identity->getCart()
        return $identity->getCart();
    }

    public function findOrCreate(Identity $identity): Cart
    {
        // Identity owns the relationship (has cart_id FK), so use identity->getCart()
        $cart = $identity->getCart();

        if (!$cart instanceof Cart) {
            $cart = new Cart();
            $identity->setCart($cart);

            // Persist and flush to save the cart_id on the identity
            $this->getEntityManager()->persist($cart);
            $this->getEntityManager()->persist($identity);
            $this->getEntityManager()->flush();
        }

        return $cart;
    }
}

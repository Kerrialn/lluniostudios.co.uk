<?php

namespace App\Repository;

use App\Entity\Order;
use App\Entity\User;
use App\Enum\OrderStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Order>
 */
class OrderRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $managerRegistry)
    {
        parent::__construct($managerRegistry, Order::class);
    }

    public function save(Order $order, bool $flush = false): void
    {
        $this->getEntityManager()->persist($order);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Order $order, bool $flush = false): void
    {
        $this->getEntityManager()->remove($order);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function findByRevolutOrderId(string $revolutOrderId): ?Order
    {
        return $this->findOneBy([
            'revolutOrderId' => $revolutOrderId,
        ]);
    }

    public function findByStripePaymentIntentId(string $stripePaymentIntentId): ?Order
    {
        return $this->findOneBy([
            'stripePaymentIntentId' => $stripePaymentIntentId,
        ]);
    }

    /**
     * The order whose quote balance (Step 10b.4) this Stripe payment is for.
     */
    public function findByBalancePaymentIntentId(string $id): ?Order
    {
        return $this->findOneBy([
            'balancePaymentIntentId' => $id,
        ]);
    }

    public function findByOrderNumber(string $orderNumber): ?Order
    {
        return $this->findOneBy([
            'orderNumber' => $orderNumber,
        ]);
    }

    /**
     * The customer's placed orders, newest first (paid or later; unfinished
     * checkout attempts are left out).
     *
     * @return list<Order>
     */
    public function findPlacedByUser(User $user): array
    {
        return $this->findBy([
            'user' => $user,
            'status' => [OrderStatus::DEPOSIT_PAID, OrderStatus::PAID, OrderStatus::FULFILLED],
        ], [
            'createdAt' => 'DESC',
        ]);
    }

    /**
     * @return list<Order>
     */
    public function findByUser(User $user): array
    {
        return $this->findBy([
            'user' => $user,
        ], [
            'createdAt' => 'DESC',
        ]);
    }
}

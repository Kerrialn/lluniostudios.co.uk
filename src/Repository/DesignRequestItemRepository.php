<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\DesignRequestItem;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<DesignRequestItem>
 */
class DesignRequestItemRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $managerRegistry)
    {
        parent::__construct($managerRegistry, DesignRequestItem::class);
    }
}

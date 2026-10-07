<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\DesignRequest;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<DesignRequest>
 */
class DesignRequestRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $managerRegistry)
    {
        parent::__construct($managerRegistry, DesignRequest::class);
    }
}

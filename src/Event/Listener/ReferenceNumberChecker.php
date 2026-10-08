<?php

declare(strict_types=1);

namespace App\Event\Listener;

use App\Entity\HasReferenceNumber;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\PrePersistEventArgs;
use Doctrine\ORM\Events;

/**
 * Before a request, commission enquiry or order is first saved: if its
 * reference number is already taken (9,000 a day for each type), it gets
 * another. The database also refuses duplicates (unique columns).
 */
#[AsDoctrineListener(event: Events::prePersist)]
final class ReferenceNumberChecker
{
    private const TRIES = 50;

    public function prePersist(PrePersistEventArgs $args): void
    {
        $record = $args->getObject();
        if (! $record instanceof HasReferenceNumber) {
            return;
        }

        $repository = $args->getObjectManager()->getRepository($record::class);
        for ($i = 0; $i < self::TRIES && $repository->count([
            $record::referenceField() => $record->getReferenceNumber(),
        ]) > 0; $i++) {
            $record->renumber();
        }
    }
}

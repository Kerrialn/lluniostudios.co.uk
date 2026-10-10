<?php

declare(strict_types=1);

namespace App\Event\Listener;

use App\Entity\CommissionEnquiryFile;
use App\Entity\DesignRequestFile;
use App\Entity\StoredFile;
use App\Service\CommissionFileStorage;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\Events;

/**
 * Deletes the stored file when its record goes (e.g. an enquiry or request
 * deleted in admin), so customers' files don't outlive their enquiry or
 * request.
 */
#[AsEntityListener(event: Events::postRemove, entity: CommissionEnquiryFile::class)]
#[AsEntityListener(event: Events::postRemove, entity: DesignRequestFile::class)]
final class CommissionEnquiryFileRemover
{
    public function __construct(
        private readonly CommissionFileStorage $storage,
    ) {
    }

    public function postRemove(StoredFile $file): void
    {
        $this->storage->remove($file);
    }
}

<?php

declare(strict_types=1);

namespace App\Event\Listener;

use App\Entity\CommissionEnquiryFile;
use App\Service\CommissionFileStorage;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\Events;

/**
 * Deletes the stored file when its record goes (e.g. an enquiry deleted in
 * admin), so customers' files don't outlive their enquiry.
 */
#[AsEntityListener(event: Events::postRemove, entity: CommissionEnquiryFile::class)]
final class CommissionEnquiryFileRemover
{
    public function __construct(
        private readonly CommissionFileStorage $storage,
    ) {
    }

    public function postRemove(CommissionEnquiryFile $file): void
    {
        $this->storage->remove($file);
    }
}

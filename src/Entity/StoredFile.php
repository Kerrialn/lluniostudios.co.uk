<?php

declare(strict_types=1);

namespace App\Entity;

use App\Service\CommissionFileStorage;

/**
 * A customer's file kept privately by CommissionFileStorage: a Bespoke
 * Commissions enquiry's file or a request's reference photo.
 */
interface StoredFile
{
    /**
     * The name the customer's file had, e.g. "Paint chip.jpg".
     */
    public function getOriginalName(): string;

    /**
     * @see CommissionFileStorage
     */
    public function getStoredName(): string;

    public function getSize(): int;
}

<?php

declare(strict_types=1);

namespace App\Entity;

/**
 * A record with a reference number (requests, commission enquiries, orders):
 * see ReferenceNumber.
 */
interface HasReferenceNumber
{
    /**
     * The database field holding the number.
     */
    public static function referenceField(): string;

    public function getReferenceNumber(): string;

    /**
     * A new number, when the one made is already taken.
     */
    public function renumber(): void;
}

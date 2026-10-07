<?php

declare(strict_types=1);

namespace App\Entity;

use App\Service\CommissionFileStorage;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\IdGenerator\UuidGenerator;
use Symfony\Component\Uid\Uuid;

/**
 * A file attached to a Bespoke Commissions enquiry. The file itself is kept
 * privately by CommissionFileStorage (never in a public folder) under a
 * random name; the customer's own file name is kept for the download.
 */
#[ORM\Entity]
#[ORM\Table(name: 'commission_enquiry_file')]
class CommissionEnquiryFile
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[ORM\CustomIdGenerator(UuidGenerator::class)]
    private Uuid $id;

    #[ORM\ManyToOne(targetEntity: CommissionEnquiry::class, inversedBy: 'files')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private CommissionEnquiry $enquiry;

    /**
     * The name the customer's file had, e.g. "Ground floor plan.pdf".
     */
    #[ORM\Column(length: 255)]
    private string $originalName;

    /**
     * Where CommissionFileStorage keeps it (relative to its folder).
     */
    #[ORM\Column(length: 255, unique: true)]
    private string $storedName;

    #[ORM\Column(type: Types::BIGINT)]
    private string $size;

    #[ORM\Column]
    private int $position = 0;

    public function __construct(string $originalName, string $storedName, int $size)
    {
        $this->originalName = $originalName;
        $this->storedName = $storedName;
        $this->size = (string) $size;
    }

    public function getId(): ?Uuid
    {
        return $this->id ?? null;
    }

    public function getEnquiry(): CommissionEnquiry
    {
        return $this->enquiry;
    }

    public function setEnquiry(CommissionEnquiry $enquiry): void
    {
        $this->enquiry = $enquiry;
    }

    public function getOriginalName(): string
    {
        return $this->originalName;
    }

    /**
     * @see CommissionFileStorage
     */
    public function getStoredName(): string
    {
        return $this->storedName;
    }

    public function getSize(): int
    {
        return (int) $this->size;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function setPosition(int $position): void
    {
        $this->position = $position;
    }
}

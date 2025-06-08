<?php

namespace App\Entity;

use App\Repository\FingerprintRepository;
use Carbon\CarbonImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\IdGenerator\UuidGenerator;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: FingerprintRepository::class)]

class Fingerprint
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[ORM\CustomIdGenerator(UuidGenerator::class)]
    private ?Uuid $id = null;

    #[ORM\Column(length: 255, unique: true, nullable: true)]
    private ?string $fingerprint = null;

    #[ORM\ManyToOne(targetEntity: Identity::class, inversedBy: 'fingerprints')]
    #[ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
    private null|Identity $identity = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private CarbonImmutable $createdAt;

    public function __construct(
        ?string $fingerprint
    )
    {
        $this->fingerprint = $fingerprint;
        $this->createdAt = new CarbonImmutable();
    }

    public function getId(): ?Uuid
    {
        return $this->id;
    }

    public function getFingerprint(): ?string
    {
        return $this->fingerprint;
    }

    public function setFingerprint(?string $fingerprint): void
    {
        $this->fingerprint = $fingerprint;
    }

    public function getCreatedAt(): CarbonImmutable
    {
        return $this->createdAt;
    }

    public function getOwner(): ?Identity
    {
        return $this->identity;
    }

    public function setOwner(?Identity $identity): void
    {
        $this->identity = $identity;
    }
}

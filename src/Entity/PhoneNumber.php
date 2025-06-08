<?php

namespace App\Entity;

use App\Repository\PhoneNumberRepository;
use Carbon\CarbonImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\IdGenerator\UuidGenerator;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: PhoneNumberRepository::class)]
class PhoneNumber
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[ORM\CustomIdGenerator(UuidGenerator::class)]
    private Uuid $id;

    #[ORM\Column(length: 255)]
    private null|string $dialCode = null;

    #[ORM\Column(length: 255)]
    private null|string $number = null;

    #[ORM\ManyToOne(targetEntity: Identity::class, inversedBy: 'phoneNumbers')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Identity $identity;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    protected CarbonImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new CarbonImmutable();
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getDialCode(): null|string
    {
        return $this->dialCode;
    }

    public function setDialCode(?string $dialCode): void
    {
        $this->dialCode = $dialCode;
    }

    public function getNumber(): ?string
    {
        return $this->number;
    }

    public function setNumber(?string $number): void
    {
        $this->number = $number;
    }

    public function getCreatedAt(): CarbonImmutable
    {
        return $this->createdAt;
    }

    public function getOwner(): Identity
    {
        return $this->identity;
    }

    public function setOwner(Identity $identity): void
    {
        $this->identity = $identity;
    }

    public function getFullPhoneNumber(): string
    {
        return $this->getDialCode() . $this->getNumber();
    }

    public function __toString(): string
    {
        return $this->getFullPhoneNumber();
    }
}

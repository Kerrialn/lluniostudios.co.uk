<?php

namespace App\Entity;

use App\Repository\InternetProtocolRepository;
use Carbon\CarbonImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\IdGenerator\UuidGenerator;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints\Ip;

#[ORM\Entity(repositoryClass: InternetProtocolRepository::class)]
class InternetProtocol
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[ORM\CustomIdGenerator(UuidGenerator::class)]
    private ?Uuid $id = null;

    #[ORM\Column(length: 45)]
    #[Ip(
        version: Ip::ALL,
        message: 'Please enter a valid IP address.'
    )]
    private null|string $address = null;

    #[ORM\Column(length: 2, nullable: true)]
    protected null|string $countryCode = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    protected CarbonImmutable $createdAt;

    /**
     * @var Collection<int, Identity>
     */
    #[ORM\ManyToMany(targetEntity: Identity::class, inversedBy: 'internetProtocols')]
    #[ORM\JoinTable(name: 'identity_ip')]
    private Collection $owners;

    public function __construct(null|string $address, null|string $countryCode = null)
    {
        $this->address = $address;
        $this->countryCode = $countryCode;
        $this->createdAt = new CarbonImmutable();
        $this->owners = new ArrayCollection();
    }

    public function getId(): ?Uuid
    {
        return $this->id;
    }

    public function getAddress(): string
    {
        return $this->address;
    }

    public function setAddress(string $address): void
    {
        $this->address = $address;
    }

    public function getCountryCode(): ?string
    {
        return $this->countryCode;
    }

    public function setCountryCode(?string $countryCode): void
    {
        $this->countryCode = $countryCode;
    }

    public function getCreatedAt(): CarbonImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(CarbonImmutable $createdAt): void
    {
        $this->createdAt = $createdAt;
    }

    public function __toString(): string
    {
        return $this->getAddress();
    }

    /**
     * @return Collection<int, Identity>
     */
    public function getOwners(): Collection
    {
        return $this->owners;
    }

    public function addOwner(Identity $identity): self
    {
        if (! $this->owners->contains($identity)) {
            $this->owners->add($identity);
            $identity->getInternetProtocols()->add($this);
        }
        return $this;
    }

    public function removeIdentity(Identity $identity): self
    {
        if ($this->owners->removeElement($identity)) {
            $identity->removeInternetProtocol($this);
        }
        return $this;
    }
}

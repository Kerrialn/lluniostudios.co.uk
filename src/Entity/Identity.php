<?php

// src/Entity/Identity.php

namespace App\Entity;

use Carbon\CarbonImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use LogicException;
use Symfony\Bridge\Doctrine\IdGenerator\UuidGenerator;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity]
#[ORM\Table(name: 'identities')]
#[ORM\InheritanceType('SINGLE_TABLE')]
#[ORM\DiscriminatorColumn(name: 'type', type: 'string')]
#[ORM\DiscriminatorMap([
    'unregisteredUser' => UnregisteredUser::class,
    'user' => User::class,
])]
#[Assert\Expression(
    "this.getEmail() != null
     or this.getPhoneNumbers().count() > 0
     or this.getFingerprints().count() > 0",
    message: "You must provide at least an email, a phone number or a fingerprint."
)]
#[ORM\HasLifecycleCallbacks]
abstract class Identity implements UserInterface
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(UuidGenerator::class)]
    protected Uuid $id;

    /**
     * @var array|string[] $roles
     */
    #[ORM\Column]
    private array $roles = [];

    #[ORM\Column(length: 255, unique: true, nullable: true)]
    #[Assert\Email]
    protected string $email;

    /**
     * @var Collection<int, PhoneNumber> $phoneNumbers
     */
    #[ORM\OneToMany(targetEntity: PhoneNumber::class, mappedBy: 'owner', cascade: ['persist'], orphanRemoval: true)]
    private Collection $phoneNumbers;

    /**
     * @var Collection<int, InternetProtocol> $internetProtocols
     */
    #[ORM\ManyToMany(targetEntity: InternetProtocol::class, mappedBy: 'owners', cascade: ['persist'])]
    private Collection $internetProtocols;

    /**
     * @var Collection<int, Fingerprint> $fingerprints
     */
    #[ORM\OneToMany(targetEntity: Fingerprint::class, mappedBy: 'owner', cascade: ['persist'])]
    private Collection $fingerprints;

    #[ORM\ManyToOne(targetEntity: PhoneNumber::class, cascade: ['remove'])]
    #[ORM\JoinColumn(
        name: 'default_phone_id',
        referencedColumnName: 'id',
        nullable: true,
        onDelete: 'SET NULL'
    )]
    private null|PhoneNumber $defaultPhoneNumber = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    protected CarbonImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    protected CarbonImmutable|null $updatedAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private null|CarbonImmutable $verifiedAt = null;

    #[ORM\OneToOne(inversedBy: 'owner', cascade: ['persist', 'remove'])]
    #[ORM\JoinColumn(
        name: 'cart_id',
        referencedColumnName: 'id',
        nullable: true
    )]
    private ?Cart $cart = null;

    public function __construct()
    {
        $this->internetProtocols = new ArrayCollection();
        $this->fingerprints = new ArrayCollection();
        $this->phoneNumbers = new ArrayCollection();
        $this->createdAt = new CarbonImmutable();
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): void
    {
        $this->email = $email;
    }

    public function getCreatedAt(): CarbonImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(CarbonImmutable $createdAt): void
    {
        $this->createdAt = $createdAt;
    }

    public function getUpdatedAt(): ?CarbonImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?CarbonImmutable $updatedAt): void
    {
        $this->updatedAt = $updatedAt;
    }

    public function getVerifiedAt(): ?CarbonImmutable
    {
        return $this->verifiedAt;
    }

    public function setVerifiedAt(?CarbonImmutable $verifiedAt): void
    {
        $this->verifiedAt = $verifiedAt;
    }

    public function getDefaultPhoneNumber(): ?PhoneNumber
    {
        return $this->defaultPhoneNumber;
    }

    public function setDefaultPhoneNumber(?PhoneNumber $defaultPhoneNumber): void
    {
        $this->defaultPhoneNumber = $defaultPhoneNumber;
    }

    /**
     * @return Collection<int, PhoneNumber>
     */
    public function getPhoneNumbers(): Collection
    {
        return $this->phoneNumbers;
    }

    public function addPhoneNumber(PhoneNumber $phoneNumber): void
    {
        if (! $this->phoneNumbers->contains($phoneNumber)) {
            $this->phoneNumbers->add($phoneNumber);
            $phoneNumber->setOwner($this);
        }
    }

    public function removePhoneNumber(PhoneNumber $phoneNumber): void
    {
        if ($phoneNumber->getOwner() === $this) {
            $this->phoneNumbers->removeElement($phoneNumber);
        }
    }

    /**
     * @return Collection<int, InternetProtocol>
     */
    public function getInternetProtocols(): Collection
    {
        return $this->internetProtocols;
    }

    public function addInternetProtocol(InternetProtocol $internetProtocol): self
    {
        if (! $this->internetProtocols->contains($internetProtocol)) {
            $this->internetProtocols->add($internetProtocol);
        }

        return $this;
    }

    public function removeInternetProtocol(InternetProtocol $internetProtocol): self
    {
        $owner = $internetProtocol->getOwners()->findFirst(fn(int $key, Identity $identity) => $identity === $this);

        if ($owner === $this) {
            $this->internetProtocols->removeElement($internetProtocol);
        }

        return $this;
    }

    /**
     * @return Collection<int, Fingerprint>
     */
    public function getFingerprints(): Collection
    {
        return $this->fingerprints;
    }

    public function addFingerprint(Fingerprint $fingerprint): self
    {
        if (! $this->fingerprints->contains($fingerprint)) {
            $this->fingerprints->add($fingerprint);
            $fingerprint->setOwner($this);
        }

        return $this;
    }

    public function removeFingerprint(Fingerprint $fingerprint): self
    {
        if ($fingerprint->getOwner() === $this) {
            $this->fingerprints->removeElement($fingerprint);
        }

        return $this;
    }

    #[ORM\PreUpdate]
    public function onPreUpdate(): void
    {
        $this->updatedAt = new CarbonImmutable();
    }

    /**
     * @throws LogicException if neither is available
     */
    public function getIdentifier(): string
    {
        if (isset($this->email) && ($this->email !== '' && $this->email !== '0')) {
            return $this->email;
        }

        if ($this->defaultPhoneNumber instanceof \App\Entity\PhoneNumber) {
            return $this->defaultPhoneNumber->getFullPhoneNumber();
        }

        if (! empty($this->getFingerprints()->first())) {
            return $this->getFingerprints()->first()->getFingerprint();
        }

        throw new LogicException('No identifier (email or default phone) is set for this Identity.');
    }

    #[ORM\PreRemove]
    public function onPreRemove(): void
    {
        $this->defaultPhoneNumber = null;
    }

    /**
     * @see UserInterface
     */
    public function getUserIdentifier(): string
    {
        return $this->getIdentifier();
    }

    /**
     * @return array|string[]
     */
    public function getRoles(): array
    {
        $roles = $this->roles;
        // guarantee every user at least has ROLE_USER
        $roles[] = 'ROLE_USER';

        return array_unique($roles);
    }

    /**
     * @param string[] $roles
     * @return $this
     */
    public function setRoles(array $roles): self
    {
        $this->roles = $roles;

        return $this;
    }

    public function eraseCredentials(): void
    {

    }

    public function getCart(): ?Cart
    {
        return $this->cart;
    }

    public function setCart(Cart $cart): static
    {
        $this->cart = $cart;

        return $this;
    }
}

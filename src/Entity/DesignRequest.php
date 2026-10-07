<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\DesignRequestStatus;
use App\Repository\DesignRequestRepository;
use Carbon\CarbonImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\IdGenerator\UuidGenerator;
use Symfony\Component\Uid\Uuid;

/**
 * A "Reserve Your Design" request: the customer's details and the lamps
 * they want (snapshotted, as on an order), sent with no payment taken.
 * The studio quotes it by hand (Step 10b).
 */
#[ORM\Entity(repositoryClass: DesignRequestRepository::class)]
#[ORM\Table(name: 'design_request')]
class DesignRequest
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[ORM\CustomIdGenerator(UuidGenerator::class)]
    private Uuid $id;

    #[ORM\Column(length: 32, unique: true)]
    private string $requestNumber;

    #[ORM\Column(type: Types::STRING, length: 16, enumType: DesignRequestStatus::class)]
    private DesignRequestStatus $status = DesignRequestStatus::NEW;

    #[ORM\Column(length: 255)]
    private string $fullName;

    #[ORM\Column(length: 255)]
    private string $email;

    #[ORM\Column(length: 32)]
    private string $phone;

    #[ORM\Column(type: Types::TEXT)]
    private string $deliveryAddress;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $accessNotes = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $customDetails = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    /**
     * @var Collection<int, DesignRequestItem>
     */
    #[ORM\OneToMany(targetEntity: DesignRequestItem::class, mappedBy: 'designRequest', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy([
        'position' => 'ASC',
    ])]
    private Collection $items;

    public function __construct(string $fullName, string $email, string $phone, string $deliveryAddress, ?string $accessNotes = null, ?string $customDetails = null)
    {
        $this->fullName = $fullName;
        $this->email = $email;
        $this->phone = $phone;
        $this->deliveryAddress = $deliveryAddress;
        $this->accessNotes = $accessNotes;
        $this->customDetails = $customDetails;
        $this->createdAt = new CarbonImmutable();
        $this->items = new ArrayCollection();
        $this->requestNumber = 'LR-' . (new CarbonImmutable())->format('Ymd') . '-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));
    }

    public function getId(): ?Uuid
    {
        return $this->id ?? null;
    }

    public function getRequestNumber(): string
    {
        return $this->requestNumber;
    }

    public function getStatus(): DesignRequestStatus
    {
        return $this->status;
    }

    public function setStatus(DesignRequestStatus $status): void
    {
        $this->status = $status;
    }

    public function getFullName(): string
    {
        return $this->fullName;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function getPhone(): string
    {
        return $this->phone;
    }

    public function getDeliveryAddress(): string
    {
        return $this->deliveryAddress;
    }

    public function getAccessNotes(): ?string
    {
        return $this->accessNotes;
    }

    public function getCustomDetails(): ?string
    {
        return $this->customDetails;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    /**
     * @return Collection<int, DesignRequestItem>
     */
    public function getItems(): Collection
    {
        return $this->items;
    }

    public function addItem(DesignRequestItem $item): void
    {
        $item->setDesignRequest($this);
        $item->setPosition($this->items->count());
        $this->items->add($item);
    }

    public function getLampCount(): int
    {
        return array_sum(array_map(static fn (DesignRequestItem $item): int => $item->getQuantity(), $this->items->toArray()));
    }

    public function __toString(): string
    {
        return $this->requestNumber;
    }
}

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

    /**
     * The address as one block of text: requests received before the address
     * was split into fields (8 October 2026). Null for newer requests.
     */
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $deliveryAddress = null;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $addressLine1 = null;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $addressLine2 = null;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $town = null;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $county = null;

    #[ORM\Column(length: 16, nullable: true)]
    private ?string $postcode = null;

    /**
     * GB, IM (Isle of Man), GG (Guernsey) or JE (Jersey).
     */
    #[ORM\Column(length: 2, nullable: true)]
    private ?string $country = null;

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

    /**
     * Where a request can be delivered, as on the Reserve page.
     */
    /**
     * A UK postcode anywhere in an older request's address (e.g. "BH21 3EG").
     */
    public const POSTCODE_PATTERN = '/\b[A-Z]{1,2}\d[A-Z\d]?\s*\d[A-Z]{2}\b/i';

    public const COUNTRIES = [
        'GB' => 'United Kingdom',
        'IM' => 'Isle of Man',
        'GG' => 'Guernsey',
        'JE' => 'Jersey',
    ];

    public function __construct(string $fullName, string $email, string $phone, ?string $accessNotes = null, ?string $customDetails = null)
    {
        $this->fullName = $fullName;
        $this->email = $email;
        $this->phone = $phone;
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

    public function setAddress(string $line1, ?string $line2, string $town, ?string $county, string $postcode, string $country): void
    {
        $this->addressLine1 = $line1;
        $this->addressLine2 = $line2;
        $this->town = $town;
        $this->county = $county;
        $this->postcode = $postcode;
        $this->country = $country;
    }

    /**
     * The address, one line each (from the fields, or the older single box).
     *
     * @return list<string>
     */
    public function getAddressLines(): array
    {
        if ($this->addressLine1 === null) {
            return array_values(array_filter(array_map('trim', explode("\n", (string) $this->deliveryAddress)), static fn (string $line): bool => $line !== ''));
        }

        return array_values(array_filter([
            $this->addressLine1,
            $this->addressLine2,
            $this->town,
            $this->county,
            $this->postcode,
            self::COUNTRIES[$this->country ?? 'GB'] ?? $this->country,
        ], static fn (?string $line): bool => $line !== null && $line !== ''));
    }

    /**
     * The whole address as text (emails, admin search, older requests).
     */
    public function getDeliveryAddress(): string
    {
        return implode("\n", $this->getAddressLines());
    }

    public function getLegacyDeliveryAddress(): ?string
    {
        return $this->deliveryAddress;
    }

    public function getAddressLine1(): ?string
    {
        return $this->addressLine1;
    }

    public function getAddressLine2(): ?string
    {
        return $this->addressLine2;
    }

    public function getTown(): ?string
    {
        return $this->town;
    }

    public function getCounty(): ?string
    {
        return $this->county;
    }

    /**
     * The postcode field, or for an older request the postcode found in its
     * address text.
     */
    public function getPostcode(): ?string
    {
        if ($this->postcode !== null) {
            return $this->postcode;
        }

        return preg_match(self::POSTCODE_PATTERN, (string) $this->deliveryAddress, $m) ? strtoupper($m[0]) : null;
    }

    public function getCountry(): ?string
    {
        return $this->country;
    }

    public function getCountryName(): ?string
    {
        return $this->country === null ? null : (self::COUNTRIES[$this->country] ?? $this->country);
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

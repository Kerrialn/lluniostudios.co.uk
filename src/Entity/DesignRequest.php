<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\DesignRequestStatus;
use App\Enum\DesignRequestType;
use App\Enum\EnquiryTalk;
use App\Enum\GiftSendTo;
use App\Repository\DesignRequestRepository;
use App\Service\GreetingName;
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
 *
 * Or an enquiry from "Begin the Conversation" (Step 7c, DesignRequest::enquiry()):
 * a larger piece (size and quantity, the finishes to be chosen together),
 * how and when to talk, their space and what they have in mind, a board
 * link and files, and the postcode only (the full address is asked on the
 * quote page). Quoted and paid the same way.
 */
#[ORM\Entity(repositoryClass: DesignRequestRepository::class)]
#[ORM\Table(name: 'design_request')]
class DesignRequest implements HasReferenceNumber
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

    #[ORM\Column(type: Types::STRING, length: 16, enumType: DesignRequestType::class, options: [
        'default' => 'reservation',
    ])]
    private DesignRequestType $type = DesignRequestType::RESERVATION;

    #[ORM\Column(length: 255)]
    private string $fullName;

    #[ORM\Column(length: 255)]
    private string $email;

    /**
     * Required on Reserve Your Design; optional on an enquiry (unless they
     * chose a phone or video call).
     */
    #[ORM\Column(length: 32, nullable: true)]
    private ?string $phone;

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
    private ?string $accessNotes;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $customDetails;

    /**
     * Gift options (9 October 2026): "This is a gift" with a preferred
     * "Needed by" date (not a guarantee), "Send to me" or "Send directly to
     * the recipient"; for a direct gift, the recipient's name (the delivery
     * name), the message for the card and the recipient's mobile.
     */
    #[ORM\Column(options: [
        'default' => false,
    ])]
    private bool $gift = false;

    #[ORM\Column(type: 'date_immutable', nullable: true)]
    private ?\DateTimeImmutable $giftNeededBy = null;

    #[ORM\Column(length: 16, nullable: true, enumType: GiftSendTo::class)]
    private ?GiftSendTo $giftSendTo = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $giftRecipientName = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $giftMessage = null;

    #[ORM\Column(length: 32, nullable: true)]
    private ?string $giftRecipientPhone = null;

    /**
     * An enquiry's own details (Step 7c): how they'd like to talk, when, their
     * space and what they have in mind, and a board link (kept as typed).
     */
    #[ORM\Column(length: 16, nullable: true, enumType: EnquiryTalk::class)]
    private ?EnquiryTalk $talk = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $bestTime = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $boardLink = null;

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
     * Reference photos sent with a Custom choice (10 October 2026), or an
     * enquiry's files (Step 7c), kept privately (CommissionFileStorage) and
     * downloaded from admin.
     *
     * @var Collection<int, DesignRequestFile>
     */
    #[ORM\OneToMany(targetEntity: DesignRequestFile::class, mappedBy: 'designRequest', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy([
        'position' => 'ASC',
    ])]
    private Collection $files;

    /**
     * Where a request can be delivered, as on the Reserve page.
     */
    /**
     * A UK postcode anywhere in an older request's address (e.g. "SW1A 1AA").
     */
    public const POSTCODE_PATTERN = '/\b[A-Z]{1,2}\d[A-Z\d]?\s*\d[A-Z]{2}\b/i';

    public const COUNTRIES = [
        'GB' => 'United Kingdom',
        'IM' => 'Isle of Man',
        'GG' => 'Guernsey',
        'JE' => 'Jersey',
    ];

    public function __construct(string $fullName, string $email, ?string $phone, ?string $accessNotes = null, ?string $customDetails = null)
    {
        $this->fullName = $fullName;
        $this->email = $email;
        $this->phone = $phone;
        $this->accessNotes = $accessNotes;
        $this->customDetails = $customDetails;
        $this->createdAt = new CarbonImmutable();
        $this->items = new ArrayCollection();
        $this->files = new ArrayCollection();
        $this->requestNumber = ReferenceNumber::make('LR');
    }

    /**
     * An enquiry from "Begin the Conversation" (Step 7c): the postcode only,
     * its country worked out from it (GB, IM, GG or JE).
     */
    public static function enquiry(string $fullName, string $email, ?string $phone, EnquiryTalk $talk, ?string $bestTime, string $description, ?string $boardLink, string $postcode, string $country): self
    {
        $enquiry = new self($fullName, $email, $phone);
        $enquiry->type = DesignRequestType::ENQUIRY;
        $enquiry->talk = $talk;
        $enquiry->bestTime = $bestTime;
        $enquiry->description = $description;
        $enquiry->boardLink = $boardLink;
        $enquiry->postcode = $postcode;
        $enquiry->country = $country;

        return $enquiry;
    }

    public static function referenceField(): string
    {
        return 'requestNumber';
    }

    public function getReferenceNumber(): string
    {
        return $this->requestNumber;
    }

    public function renumber(): void
    {
        $this->requestNumber = ReferenceNumber::make('LR');
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

    public function getType(): DesignRequestType
    {
        return $this->type;
    }

    public function isEnquiry(): bool
    {
        return $this->type === DesignRequestType::ENQUIRY;
    }

    public function getTalk(): ?EnquiryTalk
    {
        return $this->talk;
    }

    /**
     * For admin: "Video call", or null for a reservation.
     */
    public function getTalkLabel(): ?string
    {
        return $this->talk?->label();
    }

    public function getBestTime(): ?string
    {
        return $this->bestTime;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function getBoardLink(): ?string
    {
        return $this->boardLink;
    }

    public function getFullName(): string
    {
        return $this->fullName;
    }

    /**
     * The first name for "Dear Ada," (GreetingName: titles and initials
     * skipped, capitals fixed), or null ("Dear Customer,").
     */
    public function getFirstName(): ?string
    {
        return GreetingName::firstName($this->fullName);
    }

    /**
     * A gift (Gift options): needed by this date (preferred), sent to the
     * customer or directly to the recipient; or not a gift (null date).
     */
    public function setGift(?\DateTimeImmutable $neededBy, ?GiftSendTo $sendTo = null, ?string $recipientName = null, ?string $message = null, ?string $recipientPhone = null): void
    {
        $this->gift = $neededBy instanceof \DateTimeImmutable;
        $this->giftNeededBy = $neededBy;
        $this->giftSendTo = $this->gift ? $sendTo : null;
        $direct = $this->giftSendTo?->isDirect() === true;
        $this->giftRecipientName = $direct ? $recipientName : null;
        $this->giftMessage = $direct ? $message : null;
        $this->giftRecipientPhone = $direct ? $recipientPhone : null;
    }

    public function isGift(): bool
    {
        return $this->gift;
    }

    public function getGiftNeededBy(): ?\DateTimeImmutable
    {
        return $this->giftNeededBy;
    }

    public function getGiftSendTo(): ?GiftSendTo
    {
        return $this->giftSendTo;
    }

    public function isDirectGift(): bool
    {
        return $this->gift && $this->giftSendTo?->isDirect() === true;
    }

    public function getGiftRecipientName(): ?string
    {
        return $this->giftRecipientName;
    }

    /**
     * For admin: a direct gift's prominent packing note (Gift options).
     */
    public function getGiftPackingNote(): string
    {
        return $this->isDirectGift() ? '<strong class="badge bg-warning text-dark fs-6">Direct to recipient: no prices in the box</strong>' : '—';
    }

    public function getGiftMessage(): ?string
    {
        return $this->giftMessage;
    }

    public function getGiftRecipientPhone(): ?string
    {
        return $this->giftRecipientPhone;
    }

    /**
     * For admin: "Needed by 23 October 2026 · Direct to recipient", or "No".
     */
    public function getGiftLabel(): string
    {
        if (! $this->giftNeededBy instanceof \DateTimeImmutable) {
            return 'No';
        }

        return 'Needed by ' . $this->giftNeededBy->format('j F Y') . ($this->giftSendTo instanceof \App\Enum\GiftSendTo ? ' · ' . ($this->isDirectGift() ? 'Direct to recipient' : 'To the customer') : '');
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function getPhone(): ?string
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
     * The address, one line each (from the fields, or the older single box;
     * an enquiry has only the postcode and its country).
     *
     * @return list<string>
     */
    public function getAddressLines(): array
    {
        if ($this->addressLine1 === null && $this->deliveryAddress === null && $this->postcode !== null) {
            return [$this->postcode, (string) $this->getCountryName()];
        }

        if ($this->addressLine1 === null) {
            return array_values(array_filter(array_map('trim', explode("\n", (string) $this->deliveryAddress)), static fn (string $line): bool => $line !== ''));
        }

        return array_values(array_filter([
            // A direct gift goes to the recipient by name.
            $this->isDirectGift() ? $this->giftRecipientName : null,
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

    /**
     * @return Collection<int, DesignRequestFile>
     */
    public function getFiles(): Collection
    {
        return $this->files;
    }

    public function addFile(DesignRequestFile $file): void
    {
        $file->setDesignRequest($this);
        $file->setPosition($this->files->count());
        $this->files->add($file);
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

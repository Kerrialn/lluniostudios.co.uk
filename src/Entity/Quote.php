<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\QuotePayment;
use Carbon\CarbonImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\IdGenerator\UuidGenerator;
use Symfony\Component\Uid\Uuid;

/**
 * The studio's quote for a "Reserve Your Design" request (Step 10b): a price
 * for each lamp design, any extra named lines, delivery for the whole order,
 * full payment or a deposit, an estimated casting date and a personal note.
 * Prepared in admin as a draft (10b.1); sending it comes with 10b.2. No VAT
 * (not VAT-registered; decided 8 October 2026).
 */
#[ORM\Entity]
#[ORM\Table(name: 'quote')]
#[ORM\HasLifecycleCallbacks]
class Quote
{
    public const DEFAULT_DEPOSIT_PERCENT = 50;

    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[ORM\CustomIdGenerator(UuidGenerator::class)]
    private Uuid $id;

    #[ORM\OneToOne(targetEntity: DesignRequest::class)]
    #[ORM\JoinColumn(nullable: false, unique: true, onDelete: 'CASCADE')]
    private DesignRequest $designRequest;

    /**
     * @var Collection<int, QuoteLine>
     */
    #[ORM\OneToMany(targetEntity: QuoteLine::class, mappedBy: 'quote', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy([
        'position' => 'ASC',
    ])]
    private Collection $lines;

    /**
     * Pence, for the whole order as one consignment.
     */
    #[ORM\Column]
    private int $deliveryPrice = 0;

    /**
     * e.g. "DPD Next Day Delivery", "Palletways · Half pallet".
     */
    #[ORM\Column(length: 128)]
    private string $deliveryLabel = '';

    /**
     * True while the delivery price is the carriers' mock estimate (not
     * changed by the studio, and not a live carrier price).
     */
    #[ORM\Column]
    private bool $deliveryEstimated = false;

    #[ORM\Column(type: Types::STRING, length: 16, enumType: QuotePayment::class)]
    private QuotePayment $payment = QuotePayment::FULL;

    #[ORM\Column]
    private int $depositPercent = self::DEFAULT_DEPOSIT_PERCENT;

    #[ORM\Column(type: 'date_immutable', nullable: true)]
    private ?\DateTimeImmutable $castingDate = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $note = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $updatedAt;

    public function __construct(DesignRequest $designRequest)
    {
        $this->designRequest = $designRequest;
        $this->lines = new ArrayCollection();
        $this->createdAt = new CarbonImmutable();
        $this->updatedAt = $this->createdAt;
    }

    #[ORM\PreUpdate]
    public function touch(): void
    {
        $this->updatedAt = new CarbonImmutable();
    }

    public function getId(): ?Uuid
    {
        return $this->id ?? null;
    }

    public function getDesignRequest(): DesignRequest
    {
        return $this->designRequest;
    }

    /**
     * @return Collection<int, QuoteLine>
     */
    public function getLines(): Collection
    {
        return $this->lines;
    }

    /**
     * @return list<QuoteLine>
     */
    public function getLampLines(): array
    {
        return array_values(array_filter($this->lines->toArray(), static fn (QuoteLine $line): bool => $line->isLamp()));
    }

    /**
     * @return list<QuoteLine>
     */
    public function getExtraLines(): array
    {
        return array_values(array_filter($this->lines->toArray(), static fn (QuoteLine $line): bool => ! $line->isLamp()));
    }

    public function addLine(QuoteLine $line): void
    {
        $line->setQuote($this);
        $line->setPosition($this->lines->count());
        $this->lines->add($line);
    }

    public function removeLine(QuoteLine $line): void
    {
        $this->lines->removeElement($line);
    }

    public function getDeliveryPrice(): int
    {
        return $this->deliveryPrice;
    }

    public function getDeliveryLabel(): string
    {
        return $this->deliveryLabel;
    }

    public function isDeliveryEstimated(): bool
    {
        return $this->deliveryEstimated;
    }

    public function setDelivery(int $price, string $label, bool $estimated): void
    {
        $this->deliveryPrice = $price;
        $this->deliveryLabel = $label;
        $this->deliveryEstimated = $estimated;
    }

    public function getPayment(): QuotePayment
    {
        return $this->payment;
    }

    public function setPayment(QuotePayment $payment): void
    {
        $this->payment = $payment;
    }

    public function getDepositPercent(): int
    {
        return $this->depositPercent;
    }

    public function setDepositPercent(int $depositPercent): void
    {
        $this->depositPercent = $depositPercent;
    }

    public function getCastingDate(): ?\DateTimeImmutable
    {
        return $this->castingDate;
    }

    public function setCastingDate(?\DateTimeImmutable $castingDate): void
    {
        $this->castingDate = $castingDate;
    }

    public function getNote(): ?string
    {
        return $this->note;
    }

    public function setNote(?string $note): void
    {
        $this->note = $note;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    /**
     * The lamps (each design's quantity × price for each lamp).
     */
    public function getLampsTotal(): int
    {
        return array_sum(array_map(static fn (QuoteLine $line): int => $line->getTotal(), $this->getLampLines()));
    }

    public function getExtrasTotal(): int
    {
        return array_sum(array_map(static fn (QuoteLine $line): int => $line->getTotal(), $this->getExtraLines()));
    }

    public function getTotal(): int
    {
        return $this->getLampsTotal() + $this->getExtrasTotal() + $this->deliveryPrice;
    }

    /**
     * What the customer pays now: the whole total, or the deposit (rounded
     * to the nearest penny).
     */
    public function getDueNow(): int
    {
        return $this->payment === QuotePayment::DEPOSIT
            ? (int) round($this->getTotal() * $this->depositPercent / 100)
            : $this->getTotal();
    }

    /**
     * What is left to pay after the deposit ("Request balance", 10b.4).
     */
    public function getBalance(): int
    {
        return $this->getTotal() - $this->getDueNow();
    }
}

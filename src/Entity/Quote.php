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
 * Prepared in admin as a draft (10b.1). Sending (10b.2) emails it with a
 * private link (token) and keeps a copy of what was sent (the customer's page
 * and payment use that copy, so later changes reach the customer only when it
 * is sent again); it is valid for 14 days from sending. No VAT (not
 * VAT-registered; decided 8 October 2026).
 */
#[ORM\Entity]
#[ORM\Table(name: 'quote')]
#[ORM\HasLifecycleCallbacks]
class Quote
{
    public const DEFAULT_DEPOSIT_PERCENT = 50;

    /**
     * Days a sent quote is valid for: until the end of the 14th day after
     * sending, UK time.
     */
    public const VALID_DAYS = 14;

    private const UK = 'Europe/London';

    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[ORM\CustomIdGenerator(UuidGenerator::class)]
    private Uuid $id;

    #[ORM\OneToOne(targetEntity: DesignRequest::class)]
    #[ORM\JoinColumn(unique: true, nullable: false, onDelete: 'CASCADE')]
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

    /**
     * The private link's token, made on first sending (kept when sent again).
     */
    #[ORM\Column(length: 64, unique: true, nullable: true)]
    private ?string $token = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $sentAt = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $expiresAt = null;

    /**
     * What was last sent (see snapshot()).
     *
     * @var array<string, mixed>|null
     */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $sent = null;

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

    /**
     * The quote as the customer sees it: every line, delivery, totals,
     * payment, casting date and note.
     *
     * @return array{lines: list<array{label: string, quantity: int, unitPrice: int, total: int, requestItem: ?string}>, deliveryPrice: int, deliveryLabel: string, lampsTotal: int, extrasTotal: int, total: int, payment: string, depositPercent: int, dueNow: int, balance: int, castingDate: ?string, note: ?string}
     */
    public function snapshot(): array
    {
        return [
            'lines' => array_map(static fn (QuoteLine $line): array => [
                'label' => $line->getLabel(),
                'quantity' => $line->getQuantity(),
                'unitPrice' => $line->getUnitPrice(),
                'total' => $line->getTotal(),
                'requestItem' => $line->getRequestItem()?->getId()?->toRfc4122(),
            ], $this->lines->getValues()),
            'deliveryPrice' => $this->deliveryPrice,
            'deliveryLabel' => $this->deliveryLabel,
            'lampsTotal' => $this->getLampsTotal(),
            'extrasTotal' => $this->getExtrasTotal(),
            'total' => $this->getTotal(),
            'payment' => $this->payment->value,
            'depositPercent' => $this->depositPercent,
            'dueNow' => $this->getDueNow(),
            'balance' => $this->getBalance(),
            'castingDate' => $this->castingDate?->format('Y-m-d'),
            'note' => $this->note,
        ];
    }

    /**
     * The end of the last day the quote is valid, if sent now.
     */
    public static function expiryFor(\DateTimeImmutable $sentAt): \DateTimeImmutable
    {
        // Worked out in UK time, kept in the server's time zone (times are stored without one).
        return CarbonImmutable::instance($sentAt)->setTimezone(self::UK)->addDays(self::VALID_DAYS)->endOfDay()
            ->setTimezone(date_default_timezone_get())->toDateTimeImmutable();
    }

    public function markSent(string $token, \DateTimeImmutable $sentAt): void
    {
        $this->token ??= $token;
        $this->sentAt = $sentAt;
        $this->expiresAt = self::expiryFor($sentAt);
        $this->sent = $this->snapshot();
    }

    public function getToken(): ?string
    {
        return $this->token;
    }

    public function isSent(): bool
    {
        return $this->sentAt instanceof \DateTimeImmutable;
    }

    public function getSentAt(): ?\DateTimeImmutable
    {
        return $this->sentAt;
    }

    public function getExpiresAt(): ?\DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function isExpired(?\DateTimeImmutable $now = null): bool
    {
        return $this->expiresAt instanceof \DateTimeImmutable && ($now ?? new CarbonImmutable()) > $this->expiresAt;
    }

    /**
     * What was last sent to the customer (null if never sent).
     *
     * @return array<string, mixed>|null
     */
    public function getSent(): ?array
    {
        return $this->sent;
    }

    /**
     * Saved changes the customer hasn't been sent yet.
     */
    public function isChangedSinceSent(): bool
    {
        return $this->sent !== null && $this->sent != $this->snapshot();
    }
}

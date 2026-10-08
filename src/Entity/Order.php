<?php

namespace App\Entity;

use App\Enum\OrderStatus;
use App\Enum\ShippingMethod;
use App\Repository\OrderRepository;
use Carbon\CarbonImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\IdGenerator\UuidGenerator;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: OrderRepository::class)]
#[ORM\Table(name: 'orders')]
#[ORM\HasLifecycleCallbacks]
class Order implements HasReferenceNumber
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[ORM\CustomIdGenerator(UuidGenerator::class)]
    private Uuid $id;

    #[ORM\Column(length: 32, unique: true)]
    private string $orderNumber;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'orders')]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?User $user = null;

    #[ORM\Column(length: 255)]
    private ?string $email = null;

    #[ORM\Column(type: Types::STRING, length: 32, enumType: OrderStatus::class)]
    private OrderStatus $status = OrderStatus::PENDING;

    #[ORM\Column(length: 3)]
    private string $currency = 'GBP';

    #[ORM\Column(type: Types::INTEGER)]
    private int $subtotal = 0;

    #[ORM\Column(type: Types::INTEGER)]
    private int $shippingCost = 0;

    #[ORM\Column(type: Types::INTEGER)]
    private int $total = 0;

    #[ORM\Column(type: Types::STRING, length: 16, enumType: ShippingMethod::class, nullable: true)]
    private ?ShippingMethod $shippingMethod = null;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $shippingCarrier = null;

    #[ORM\Column(length: 128, nullable: true)]
    private ?string $shippingServiceName = null;

    #[ORM\ManyToOne(targetEntity: Address::class, cascade: ['persist'])]
    #[ORM\JoinColumn(name: 'shipping_address_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?Address $shippingAddress = null;

    /**
     * Billing address when it differs from the delivery address (Card
     * Payment page, Step 10.2); null means "same as delivery".
     */
    #[ORM\ManyToOne(targetEntity: Address::class, cascade: ['persist'])]
    #[ORM\JoinColumn(name: 'billing_address_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?Address $billingAddress = null;

    /**
     * The customer's mobile ("Your Mobile (for order and delivery updates)"), also DPD's
     * delivery phone on the delivery address (gifts too).
     */
    #[ORM\Column(length: 32, nullable: true)]
    private ?string $contactPhone = null;

    /**
     * "Delivery instructions (optional)", passed to DPD (50 characters).
     */
    #[ORM\Column(length: 64, nullable: true)]
    private ?string $deliveryNotes = null;

    /**
     * "This is a gift" and its arrive-by date, from the basket (Step 10.5).
     */
    #[ORM\Column(options: [
        'default' => false,
    ])]
    private bool $gift = false;

    #[ORM\Column(type: 'date_immutable', nullable: true)]
    private ?\DateTimeImmutable $giftArriveBy = null;

    #[ORM\Column(length: 128, nullable: true)]
    private ?string $revolutOrderId = null;

    #[ORM\Column(length: 32, nullable: true)]
    private ?string $revolutState = null;

    #[ORM\Column(length: 128, nullable: true)]
    private ?string $stripePaymentIntentId = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?CarbonImmutable $paidAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private CarbonImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?CarbonImmutable $updatedAt = null;

    /**
     * @var Collection<int, OrderItem>
     */
    #[ORM\OneToMany(targetEntity: OrderItem::class, mappedBy: 'order', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $items;

    public function __construct()
    {
        $this->createdAt = new CarbonImmutable();
        $this->items = new ArrayCollection();
        $this->orderNumber = $this->generateOrderNumber();
    }

    private function generateOrderNumber(): string
    {
        return ReferenceNumber::make('LS');
    }

    public static function referenceField(): string
    {
        return 'orderNumber';
    }

    public function getReferenceNumber(): string
    {
        return $this->orderNumber;
    }

    public function renumber(): void
    {
        $this->orderNumber = ReferenceNumber::make('LS');
    }

    #[ORM\PreUpdate]
    public function onPreUpdate(): void
    {
        $this->updatedAt = new CarbonImmutable();
    }

    public function getId(): ?Uuid
    {
        return $this->id;
    }

    public function getOrderNumber(): string
    {
        return $this->orderNumber;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): void
    {
        $this->user = $user;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(?string $email): void
    {
        $this->email = $email;
    }

    public function getStatus(): OrderStatus
    {
        return $this->status;
    }

    public function setStatus(OrderStatus $status): void
    {
        $this->status = $status;
    }

    public function getCurrency(): string
    {
        return $this->currency;
    }

    public function setCurrency(string $currency): void
    {
        $this->currency = $currency;
    }

    public function getSubtotal(): int
    {
        return $this->subtotal;
    }

    public function setSubtotal(int $subtotal): void
    {
        $this->subtotal = $subtotal;
    }

    public function getShippingCost(): int
    {
        return $this->shippingCost;
    }

    public function setShippingCost(int $shippingCost): void
    {
        $this->shippingCost = $shippingCost;
    }

    public function getTotal(): int
    {
        return $this->total;
    }

    public function setTotal(int $total): void
    {
        $this->total = $total;
    }

    public function recalculateTotal(): void
    {
        $this->total = $this->subtotal + $this->shippingCost;
    }

    public function getShippingMethod(): ?ShippingMethod
    {
        return $this->shippingMethod;
    }

    public function setShippingMethod(?ShippingMethod $shippingMethod): void
    {
        $this->shippingMethod = $shippingMethod;
    }

    public function getShippingCarrier(): ?string
    {
        return $this->shippingCarrier;
    }

    public function setShippingCarrier(?string $shippingCarrier): void
    {
        $this->shippingCarrier = $shippingCarrier;
    }

    public function getShippingServiceName(): ?string
    {
        return $this->shippingServiceName;
    }

    public function setShippingServiceName(?string $shippingServiceName): void
    {
        $this->shippingServiceName = $shippingServiceName;
    }

    public function isGift(): bool
    {
        return $this->gift;
    }

    public function getGiftArriveBy(): ?\DateTimeImmutable
    {
        return $this->giftArriveBy;
    }

    /**
     * A gift arriving by this date, or not a gift (null).
     */
    public function setGiftArriveBy(?\DateTimeImmutable $arriveBy): void
    {
        $this->gift = $arriveBy instanceof \DateTimeImmutable;
        $this->giftArriveBy = $arriveBy;
    }

    /**
     * "Arrive by 23 October 2026" for a gift, or null.
     */
    public function getGiftLine(): ?string
    {
        return $this->giftArriveBy instanceof \DateTimeImmutable ? 'Arrive by ' . $this->giftArriveBy->format('j F Y') : null;
    }

    /**
     * For admin: "Arrive by [date]" for a gift, otherwise "No".
     */
    public function getGiftLabel(): string
    {
        return $this->getGiftLine() ?? 'No';
    }

    /**
     * For admin: the billing address, or "Same as delivery address".
     */
    public function getBillingAddressLabel(): string
    {
        return $this->billingAddress instanceof Address ? (string) $this->billingAddress : 'Same as delivery address';
    }

    public function getBillingAddress(): ?Address
    {
        return $this->billingAddress;
    }

    public function setBillingAddress(?Address $billingAddress): void
    {
        $this->billingAddress = $billingAddress;
    }

    public function getContactPhone(): ?string
    {
        return $this->contactPhone;
    }

    public function setContactPhone(?string $contactPhone): void
    {
        $this->contactPhone = $contactPhone;
    }

    public function getDeliveryNotes(): ?string
    {
        return $this->deliveryNotes;
    }

    public function setDeliveryNotes(?string $deliveryNotes): void
    {
        $this->deliveryNotes = $deliveryNotes;
    }

    public function getShippingAddress(): ?Address
    {
        return $this->shippingAddress;
    }

    public function setShippingAddress(?Address $shippingAddress): void
    {
        $this->shippingAddress = $shippingAddress;
    }

    public function getRevolutOrderId(): ?string
    {
        return $this->revolutOrderId;
    }

    public function setRevolutOrderId(?string $revolutOrderId): void
    {
        $this->revolutOrderId = $revolutOrderId;
    }

    public function getRevolutState(): ?string
    {
        return $this->revolutState;
    }

    public function setRevolutState(?string $revolutState): void
    {
        $this->revolutState = $revolutState;
    }

    public function getStripePaymentIntentId(): ?string
    {
        return $this->stripePaymentIntentId;
    }

    public function setStripePaymentIntentId(?string $stripePaymentIntentId): void
    {
        $this->stripePaymentIntentId = $stripePaymentIntentId;
    }

    public function getPaidAt(): ?CarbonImmutable
    {
        return $this->paidAt;
    }

    public function setPaidAt(?CarbonImmutable $paidAt): void
    {
        $this->paidAt = $paidAt;
    }

    public function getCreatedAt(): CarbonImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?CarbonImmutable
    {
        return $this->updatedAt;
    }

    /**
     * @return Collection<int, OrderItem>
     */
    public function getItems(): Collection
    {
        return $this->items;
    }

    public function addItem(OrderItem $item): void
    {
        if (! $this->items->contains($item)) {
            $this->items->add($item);
            $item->setOrder($this);
        }
    }

    public function removeItem(OrderItem $item): void
    {
        if ($this->items->removeElement($item) && $item->getOrder() === $this) {
            $item->setOrder(null);
        }
    }

    public function getTotalInGbp(): float
    {
        return $this->total / 100;
    }

    public function getSubtotalInGbp(): float
    {
        return $this->subtotal / 100;
    }

    public function getShippingCostInGbp(): float
    {
        return $this->shippingCost / 100;
    }

    public function isPaid(): bool
    {
        return $this->status === OrderStatus::PAID;
    }

    public function __toString(): string
    {
        return $this->orderNumber;
    }
}

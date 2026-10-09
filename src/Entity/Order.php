<?php

namespace App\Entity;

use App\Enum\Courier;
use App\Enum\DesignRequestStatus;
use App\Enum\GiftSendTo;
use App\Enum\OrderStage;
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

    /**
     * Gift options (9 October 2026): "Send to me" or "Send directly to the
     * recipient"; for a direct gift, the message for the card in the box and
     * the recipient's mobile (DPD's delivery phone when given).
     */
    #[ORM\Column(length: 16, nullable: true, enumType: GiftSendTo::class)]
    private ?GiftSendTo $giftSendTo = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $giftMessage = null;

    #[ORM\Column(length: 32, nullable: true)]
    private ?string $giftRecipientPhone = null;

    /**
     * The buyer's name (Greeting name, 9 October 2026): the billing name, the
     * name on card or the wallet's payer, or the request's name for a quote.
     * Used for greetings, never the recipient's.
     */
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $customerName = null;

    #[ORM\Column(length: 128, nullable: true)]
    private ?string $revolutOrderId = null;

    #[ORM\Column(length: 32, nullable: true)]
    private ?string $revolutState = null;

    #[ORM\Column(length: 128, nullable: true)]
    private ?string $stripePaymentIntentId = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?CarbonImmutable $paidAt = null;

    /**
     * The "Reserve Your Design" request this order was quoted for (Step
     * 10b.3); null for basket orders.
     */
    #[ORM\ManyToOne(targetEntity: DesignRequest::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?DesignRequest $designRequest = null;

    /**
     * What the payment being taken is for, in pence: a quote's deposit, or
     * null for the whole total.
     */
    #[ORM\Column(nullable: true)]
    private ?int $chargeNow = null;

    /**
     * Pence paid so far (the whole total for basket orders; a quote's deposit
     * until the balance is paid, 10b.4).
     */
    #[ORM\Column(options: [
        'default' => 0,
    ])]
    private int $amountPaid = 0;

    /**
     * When the studio last emailed the customer to pay the balance (10b.4).
     */
    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?CarbonImmutable $balanceRequestedAt = null;

    /**
     * The balance's own Stripe payment (the deposit's is stripePaymentIntentId).
     */
    #[ORM\Column(length: 128, nullable: true)]
    private ?string $balancePaymentIntentId = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?CarbonImmutable $balancePaidAt = null;

    /**
     * The order's private link ("View your order", Step 11.1): a long
     * random value; the page shows this one order without signing in.
     */
    #[ORM\Column(length: 64, unique: true)]
    private string $viewToken;

    /**
     * The studio's stage (Step 11.2): In Production, with when it was marked
     * and the expected dispatch date given to the customer (email 04).
     */
    #[ORM\Column(length: 32, nullable: true, enumType: OrderStage::class)]
    private ?OrderStage $stage = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?CarbonImmutable $inProductionAt = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $expectedDispatch = null;

    /**
     * Dispatched (Step 11.3): when, by which courier, its tracking number.
     */
    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?CarbonImmutable $dispatchedAt = null;

    #[ORM\Column(length: 32, nullable: true, enumType: Courier::class)]
    private ?Courier $courier = null;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $trackingNumber = null;

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
        $this->viewToken = rtrim(strtr(base64_encode(random_bytes(24)), '+/', '-_'), '=');
    }

    public function getStage(): ?OrderStage
    {
        return $this->stage;
    }

    /**
     * For admin: the stage's label, or empty before one is set.
     */
    public function getStageLabel(): string
    {
        return $this->stage?->label() ?? '';
    }

    public function getInProductionAt(): ?CarbonImmutable
    {
        return $this->inProductionAt;
    }

    public function getExpectedDispatch(): ?\DateTimeImmutable
    {
        return $this->expectedDispatch;
    }

    /**
     * "Mark In Production" (admin): a placed order not yet in production.
     */
    public function canStartProduction(): bool
    {
        return $this->isPlaced() && $this->stage === null;
    }

    public function markInProduction(\DateTimeImmutable $expectedDispatch, CarbonImmutable $now): void
    {
        $this->stage = OrderStage::IN_PRODUCTION;
        $this->inProductionAt = $now;
        $this->expectedDispatch = $expectedDispatch;
    }

    /**
     * "Mark Dispatched" (admin): a placed order not yet dispatched or later.
     * It can be dispatched only once paid in full (canDispatchNow).
     */
    public function canDispatch(): bool
    {
        return $this->isPlaced() && ($this->stage === null || $this->stage === OrderStage::IN_PRODUCTION);
    }

    /**
     * Paid in full (decided 9 October 2026: dispatch waits for the balance).
     */
    public function canDispatchNow(): bool
    {
        return $this->canDispatch() && $this->getAmountOwed() === 0;
    }

    public function markDispatched(Courier $courier, string $trackingNumber, CarbonImmutable $now): void
    {
        $this->stage = OrderStage::DISPATCHED;
        $this->dispatchedAt = $now;
        $this->courier = $courier;
        $this->trackingNumber = $trackingNumber;
    }

    public function getDispatchedAt(): ?CarbonImmutable
    {
        return $this->dispatchedAt;
    }

    public function getCourier(): ?Courier
    {
        return $this->courier;
    }

    /**
     * Email 05's opening (wording decided 9 October 2026): the lamps by size,
     * "Your Lumen Forma Gallery lamp has left the studio and is on its way
     * to you.", "Your Lumen Forma Gallery lamps have left …", "Your Lumen
     * Forma Gallery and Studio lamps have left …" (sizes listed naturally,
     * in order).
     */
    public function getOnItsWaySentence(): string
    {
        $sizes = [];
        $lamps = 0;
        foreach ($this->items as $item) {
            if (! $item->isExtra()) {
                $sizes[$item->getSizeName()] = true;
                $lamps += $item->getQuantity();
            }
        }

        $names = array_keys($sizes);
        $last = array_pop($names);
        $list = $names === [] ? (string) $last : implode(', ', $names) . ' and ' . $last;
        if ($lamps <= 1) {
            return 'Your Lumen Forma ' . $list . ' lamp has left the studio and is on its way to you.';
        }

        return 'Your Lumen Forma ' . $list . ' lamps have left the studio and are on their way to you.';
    }

    public function getCourierLabel(): string
    {
        return $this->courier?->label() ?? '';
    }

    public function getTrackingNumber(): ?string
    {
        return $this->trackingNumber;
    }

    /**
     * The courier's tracking page for this order (DPD), or null.
     */
    public function getTrackingUrl(): ?string
    {
        return $this->courier !== null && $this->trackingNumber !== null ? $this->courier->trackingUrl($this->trackingNumber) : null;
    }

    /**
     * The courier a dispatch most likely uses: Palletways when the order's
     * delivery was quoted by pallet, otherwise DPD.
     */
    public function getLikelyCourier(): Courier
    {
        return str_contains(strtolower((string) $this->shippingServiceName), 'palletways') ? Courier::PALLETWAYS : Courier::DPD;
    }

    /**
     * What the customer sees as the order's status: its stage once marked
     * (e.g. "In Production"), otherwise the payment status ("Paid").
     */
    public function getDisplayStatus(): string
    {
        return $this->stage?->label() ?? $this->status->label();
    }

    public function getViewToken(): string
    {
        return $this->viewToken;
    }

    /**
     * Placed: paid (in full or a deposit) or later. Only a placed order can
     * be seen by its private link.
     */
    public function isPlaced(): bool
    {
        return $this->isPaid() || $this->status === OrderStatus::FULFILLED;
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
     * The gift's details (Gift options): send to, message, recipient's mobile.
     */
    public function setGiftDetails(?GiftSendTo $sendTo, ?string $message, ?string $recipientPhone): void
    {
        $this->giftSendTo = $this->gift ? $sendTo : null;
        $direct = $this->giftSendTo?->isDirect() === true;
        $this->giftMessage = $direct ? $message : null;
        $this->giftRecipientPhone = $direct ? $recipientPhone : null;
    }

    public function getGiftSendTo(): ?GiftSendTo
    {
        return $this->giftSendTo;
    }

    public function isDirectGift(): bool
    {
        return $this->gift && $this->giftSendTo?->isDirect() === true;
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

    public function getCustomerName(): ?string
    {
        return $this->customerName;
    }

    public function setCustomerName(?string $customerName): void
    {
        $this->customerName = $customerName;
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
        $line = $this->getGiftLine();

        return $line === null ? 'No' : $line . ($this->giftSendTo === null ? '' : ' · ' . ($this->isDirectGift() ? 'Direct to recipient' : 'To the customer'));
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

    /**
     * A payment has been taken: paid in full, or a quote's deposit.
     */
    public function isPaid(): bool
    {
        return $this->status === OrderStatus::PAID || $this->status === OrderStatus::DEPOSIT_PAID;
    }

    public function getDesignRequest(): ?DesignRequest
    {
        return $this->designRequest;
    }

    public function setDesignRequest(?DesignRequest $designRequest): void
    {
        $this->designRequest = $designRequest;
    }

    /**
     * The amount the current payment is for.
     */
    public function getChargeNow(): int
    {
        return $this->chargeNow ?? $this->total;
    }

    public function setChargeNow(?int $chargeNow): void
    {
        $this->chargeNow = $chargeNow;
    }

    public function getAmountPaid(): int
    {
        return $this->amountPaid;
    }

    public function setAmountPaid(int $amountPaid): void
    {
        $this->amountPaid = $amountPaid;
    }

    public function getBalanceRequestedAt(): ?CarbonImmutable
    {
        return $this->balanceRequestedAt;
    }

    public function setBalanceRequestedAt(?CarbonImmutable $balanceRequestedAt): void
    {
        $this->balanceRequestedAt = $balanceRequestedAt;
    }

    public function getBalancePaymentIntentId(): ?string
    {
        return $this->balancePaymentIntentId;
    }

    public function setBalancePaymentIntentId(?string $balancePaymentIntentId): void
    {
        $this->balancePaymentIntentId = $balancePaymentIntentId;
    }

    public function getBalancePaidAt(): ?CarbonImmutable
    {
        return $this->balancePaidAt;
    }

    /**
     * The balance has been paid: the order is paid in full.
     */
    public function markBalancePaid(CarbonImmutable $paidAt): void
    {
        $this->balancePaidAt = $paidAt;
        $this->amountPaid = $this->total;
        $this->status = OrderStatus::PAID;
        $this->designRequest?->setStatus(DesignRequestStatus::PAID);
    }

    /**
     * A balance the studio can ask for: paid by deposit, the rest still owed.
     */
    public function canRequestBalance(): bool
    {
        return $this->isPaid() && $this->getAmountOwed() > 0;
    }

    /**
     * Still to pay once paid (a quote's balance); 0 before any payment.
     */
    public function getAmountOwed(): int
    {
        return $this->amountPaid > 0 ? max(0, $this->total - $this->amountPaid) : 0;
    }

    public function __toString(): string
    {
        return $this->orderNumber;
    }
}

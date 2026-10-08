<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\IdGenerator\UuidGenerator;
use Symfony\Component\Uid\Uuid;

/**
 * One line of a quote: a lamp design from the request (its quantity and the
 * price for each lamp), or an extra named line (e.g. "Custom pigment
 * development"), priced once.
 */
#[ORM\Entity]
#[ORM\Table(name: 'quote_line')]
class QuoteLine
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[ORM\CustomIdGenerator(UuidGenerator::class)]
    private Uuid $id;

    #[ORM\ManyToOne(targetEntity: Quote::class, inversedBy: 'lines')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Quote $quote;

    /**
     * The request's lamp design this line prices (null for an extra line).
     */
    #[ORM\ManyToOne(targetEntity: DesignRequestItem::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?DesignRequestItem $requestItem;

    #[ORM\Column(length: 255)]
    private string $label;

    #[ORM\Column]
    private int $quantity;

    /**
     * Pence, for each lamp (or for the extra line).
     */
    #[ORM\Column]
    private int $unitPrice;

    #[ORM\Column]
    private int $position = 0;

    private function __construct(?DesignRequestItem $requestItem, string $label, int $quantity, int $unitPrice)
    {
        $this->requestItem = $requestItem;
        $this->label = $label;
        $this->quantity = $quantity;
        $this->unitPrice = $unitPrice;
    }

    public static function forLamp(DesignRequestItem $item, int $unitPrice): self
    {
        return new self($item, $item->getProductTitle(), $item->getQuantity(), $unitPrice);
    }

    public static function extra(string $label, int $price): self
    {
        return new self(null, $label, 1, $price);
    }

    public function getId(): ?Uuid
    {
        return $this->id ?? null;
    }

    public function setQuote(Quote $quote): void
    {
        $this->quote = $quote;
    }

    public function getQuote(): Quote
    {
        return $this->quote;
    }

    public function getRequestItem(): ?DesignRequestItem
    {
        return $this->requestItem;
    }

    public function isLamp(): bool
    {
        return $this->requestItem instanceof DesignRequestItem;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function setLabel(string $label): void
    {
        $this->label = $label;
    }

    public function getQuantity(): int
    {
        return $this->quantity;
    }

    public function getUnitPrice(): int
    {
        return $this->unitPrice;
    }

    public function setUnitPrice(int $unitPrice): void
    {
        $this->unitPrice = $unitPrice;
    }

    public function getTotal(): int
    {
        return $this->quantity * $this->unitPrice;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function setPosition(int $position): void
    {
        $this->position = $position;
    }
}

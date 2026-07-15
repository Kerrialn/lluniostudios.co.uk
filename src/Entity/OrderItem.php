<?php

namespace App\Entity;

use App\Repository\OrderItemRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\IdGenerator\UuidGenerator;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: OrderItemRepository::class)]
class OrderItem
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[ORM\CustomIdGenerator(UuidGenerator::class)]
    private Uuid $id;

    #[ORM\ManyToOne(targetEntity: Order::class, inversedBy: 'items')]
    #[ORM\JoinColumn(name: 'order_id', referencedColumnName: 'id', nullable: false)]
    private ?Order $order = null;

    #[ORM\ManyToOne(targetEntity: Product::class)]
    #[ORM\JoinColumn(name: 'product_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?Product $product = null;

    #[ORM\Column(length: 255)]
    private string $productTitle;

    #[ORM\Column(type: Types::INTEGER)]
    private int $unitPrice;

    #[ORM\Column(type: Types::INTEGER)]
    private int $quantity;

    /**
     * Snapshot of selected options: list of {option, value} pairs.
     *
     * @var array<int, array{option: string, value: string}>
     */
    #[ORM\Column(type: Types::JSON)]
    private array $optionsSnapshot = [];

    /**
     * @param array<int, array{option: string, value: string}> $optionsSnapshot
     */
    public function __construct(
        Product $product,
        string $productTitle,
        int $unitPrice,
        int $quantity,
        array $optionsSnapshot = [],
    ) {
        $this->product = $product;
        $this->productTitle = $productTitle;
        $this->unitPrice = $unitPrice;
        $this->quantity = $quantity;
        $this->optionsSnapshot = $optionsSnapshot;
    }

    public function getId(): ?Uuid
    {
        return $this->id;
    }

    public function getOrder(): ?Order
    {
        return $this->order;
    }

    public function setOrder(?Order $order): void
    {
        $this->order = $order;
    }

    public function getProduct(): ?Product
    {
        return $this->product;
    }

    public function setProduct(?Product $product): void
    {
        $this->product = $product;
    }

    public function getProductTitle(): string
    {
        return $this->productTitle;
    }

    public function getUnitPrice(): int
    {
        return $this->unitPrice;
    }

    public function getQuantity(): int
    {
        return $this->quantity;
    }

    /**
     * @return array<int, array{option: string, value: string}>
     */
    public function getOptionsSnapshot(): array
    {
        return $this->optionsSnapshot;
    }

    public function getLineTotal(): int
    {
        return $this->unitPrice * $this->quantity;
    }

    public function getLineTotalInGbp(): float
    {
        return $this->getLineTotal() / 100;
    }

    public function getUnitPriceInGbp(): float
    {
        return $this->unitPrice / 100;
    }
}

<?php

namespace App\Entity;

use App\Catalogue\LumenFormaCatalogue;
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
    private ?Product $product;

    #[ORM\Column(length: 255)]
    private string $productTitle;

    #[ORM\Column(type: Types::INTEGER)]
    private int $unitPrice;

    #[ORM\Column(type: Types::INTEGER)]
    private int $quantity;

    /**
     * Snapshot of selected options: list of {option, value} pairs.
     *
     * @var array<int, array{option: string, value: string, lamp?: int}>
     */
    #[ORM\Column(type: Types::JSON)]
    private array $optionsSnapshot;

    /**
     * A product's line, or (no product) a quote's extra line, e.g. "Custom
     * pigment development".
     *
     * @param array<int, array{option: string, value: string, lamp?: int}> $optionsSnapshot lamp 0 (or absent) = every lamp, 1..n = that lamp
     */
    public function __construct(
        ?Product $product,
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
     * @return array<int, array{option: string, value: string, lamp?: int}>
     */
    public function getOptionsSnapshot(): array
    {
        return $this->optionsSnapshot;
    }

    /**
     * A larger piece reserved with its size only (Step 7b.4): no options, its
     * finishes to be chosen with the studio ("Finishes: to be chosen together").
     */
    public function isFinishesToBeChosen(): bool
    {
        return [] === $this->optionsSnapshot;
    }

    /**
     * The snapshot grouped for display: one group (label null), or one per
     * lamp ("Lamp 1", ...) when the lamps were configured individually. A
     * larger piece without options still has its one group (no options).
     *
     * @return list<array{label: ?string, options: list<array{option: string, value: string}>}>
     */
    public function getLampConfigurations(): array
    {
        if ($this->isFinishesToBeChosen()) {
            return [[
                'label' => null,
                'options' => [],
            ]];
        }

        $groups = [];
        foreach ($this->optionsSnapshot as $entry) {
            $lamp = (int) ($entry['lamp'] ?? 0);
            $groups[$lamp] ??= [
                'label' => $lamp === 0 ? null : 'Lamp ' . $lamp,
                'options' => [],
            ];
            $groups[$lamp]['options'][] = [
                'option' => $entry['option'],
                'value' => $entry['value'],
            ];
        }

        ksort($groups);

        return array_values($groups);
    }

    /**
     * "Gallery" from "Lumen Forma Gallery".
     */
    public function getSizeName(): string
    {
        return (string) preg_replace('/^' . preg_quote(LumenFormaCatalogue::COLLECTION_NAME, '/') . '\s+/', '', $this->productTitle);
    }

    /**
     * A quote's extra line (no lamp), e.g. "Custom pigment development".
     */
    public function isExtra(): bool
    {
        return ! $this->product instanceof \App\Entity\Product && $this->optionsSnapshot === [];
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

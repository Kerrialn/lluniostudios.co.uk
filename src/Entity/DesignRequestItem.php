<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\DesignRequestItemRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\IdGenerator\UuidGenerator;
use Symfony\Component\Uid\Uuid;

/**
 * One configuration on a request (a basket line or the lamp being
 * configured), snapshotted like an order line: the size, how many lamps,
 * the size's price at the time (a "from" price for Request sizes), and each
 * chosen option, for every lamp (lamp 0) or lamp by lamp (1..n).
 */
#[ORM\Entity(repositoryClass: DesignRequestItemRepository::class)]
class DesignRequestItem
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[ORM\CustomIdGenerator(UuidGenerator::class)]
    private Uuid $id;

    #[ORM\ManyToOne(targetEntity: DesignRequest::class, inversedBy: 'items')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?DesignRequest $designRequest = null;

    #[ORM\ManyToOne(targetEntity: Product::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Product $product;

    #[ORM\Column(length: 255)]
    private string $productTitle;

    #[ORM\Column(length: 64)]
    private string $sizeName;

    #[ORM\Column]
    private int $unitPrice;

    #[ORM\Column]
    private int $quantity;

    #[ORM\Column]
    private bool $customisedIndividually;

    #[ORM\Column]
    private int $position = 0;

    /**
     * @var array<int, array{option: string, value: string, lamp?: int}>
     */
    #[ORM\Column(type: Types::JSON)]
    private array $optionsSnapshot;

    /**
     * @param array<int, array{option: string, value: string, lamp?: int}> $optionsSnapshot lamp 0 (or absent) = every lamp, 1..n = that lamp
     */
    public function __construct(?Product $product, string $productTitle, string $sizeName, int $unitPrice, int $quantity, bool $customisedIndividually, array $optionsSnapshot)
    {
        $this->product = $product;
        $this->productTitle = $productTitle;
        $this->sizeName = $sizeName;
        $this->unitPrice = $unitPrice;
        $this->quantity = $quantity;
        $this->customisedIndividually = $customisedIndividually;
        $this->optionsSnapshot = $optionsSnapshot;
    }

    public function getId(): ?Uuid
    {
        return $this->id ?? null;
    }

    public function getDesignRequest(): ?DesignRequest
    {
        return $this->designRequest;
    }

    public function setDesignRequest(DesignRequest $designRequest): void
    {
        $this->designRequest = $designRequest;
    }

    public function getProduct(): ?Product
    {
        return $this->product;
    }

    public function getProductTitle(): string
    {
        return $this->productTitle;
    }

    public function getSizeName(): string
    {
        return $this->sizeName;
    }

    public function getUnitPrice(): int
    {
        return $this->unitPrice;
    }

    public function getQuantity(): int
    {
        return $this->quantity;
    }

    public function isCustomisedIndividually(): bool
    {
        return $this->customisedIndividually;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function setPosition(int $position): void
    {
        $this->position = $position;
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
                'label' => 0 === $lamp ? null : 'Lamp ' . $lamp,
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

    public function getValue(string $option, int $lamp = 0): ?string
    {
        foreach ($this->optionsSnapshot as $entry) {
            if ($entry['option'] === $option && (int) ($entry['lamp'] ?? 0) === $lamp) {
                return $entry['value'];
            }
        }

        return null;
    }
}

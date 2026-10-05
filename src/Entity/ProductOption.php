<?php

namespace App\Entity;

use App\Repository\ProductOptionRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\IdGenerator\UuidGenerator;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: ProductOptionRepository::class)]
#[ORM\UniqueConstraint(name: 'uniq_product_option_product_name', columns: ['product_id', 'name'])]
class ProductOption
{
    /**
     * Configurator stages (Site v4 design). Bulb Style and Bulb Count both
     * belong to "The Light".
     */
    public const STAGES = [
        'The Pigment' => 'pigment',
        'The Cable' => 'cable',
        'The Footing' => 'footing',
        'The Light' => 'light',
    ];

    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[ORM\CustomIdGenerator(UuidGenerator::class)]
    private null|Uuid $id = null;

    // Unique per product (see the class constraint): every size has its own options.
    // Defaults to '' so a new, unsaved option can be labelled (EasyAdmin's form prototype).
    #[ORM\Column(type: Types::STRING, length: 50)]
    private string $name = '';

    /**
     * Which configurator stage shows this option (one of STAGES).
     */
    #[ORM\Column(type: Types::STRING, length: 20, nullable: true)]
    private ?string $stage = null;

    /**
     * Display order within the product (lowest first).
     */
    #[ORM\Column(options: [
        'default' => 0,
    ])]
    private int $position = 0;

    /**
     * @var Collection<int, ProductOptionValue>
     */
    #[ORM\OneToMany(targetEntity: ProductOptionValue::class, mappedBy: 'productOption', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy([
        'position' => 'ASC',
    ])]
    private Collection $productOptionValues;

    #[ORM\ManyToOne(targetEntity: Product::class, inversedBy: 'productOptions')]
    private null|Product $product = null;

    public function __construct()
    {
        $this->productOptionValues = new ArrayCollection();
    }

    public function getId(): ?Uuid
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;
        return $this;
    }

    /**
     * @return Collection<int, ProductOptionValue>
     */
    public function getProductOptionValues(): Collection
    {
        return $this->productOptionValues;
    }

    public function addProductOptionValue(ProductOptionValue $productOptionValue): self
    {
        if (! $this->productOptionValues->contains($productOptionValue)) {
            $this->productOptionValues->add($productOptionValue);
            $productOptionValue->setProductOption($this);
        }
        return $this;
    }

    public function removeProductOptionValue(ProductOptionValue $productOptionValue): self
    {
        if ($this->productOptionValues->contains($productOptionValue)) {
            $this->productOptionValues->removeElement($productOptionValue);
        }

        return $this;
    }

    public function getStage(): ?string
    {
        return $this->stage;
    }

    public function setStage(?string $stage): void
    {
        $this->stage = $stage;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function setPosition(int $position): void
    {
        $this->position = $position;
    }

    public function __toString(): string
    {
        return $this->getName();
    }

    public function getProduct(): ?Product
    {
        return $this->product;
    }

    public function setProduct(?Product $product): void
    {
        $this->product = $product;
    }
}

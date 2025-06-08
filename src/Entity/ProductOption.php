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
class ProductOption
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[ORM\CustomIdGenerator(UuidGenerator::class)]
    private null|Uuid $id = null;

    #[ORM\Column(type: Types::STRING, length: 50, unique: true)]
    private string $name;

    /**
     * @var Collection<int, ProductOptionValue>
     */
    #[ORM\OneToMany(targetEntity: ProductOptionValue::class, mappedBy: 'productOption', cascade: ['persist', 'remove'], orphanRemoval: true)]
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

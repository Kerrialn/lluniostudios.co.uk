<?php

namespace App\Entity;

use App\Repository\ProductRepository;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\IdGenerator\UuidGenerator;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: ProductRepository::class)]
class Product
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[ORM\CustomIdGenerator(UuidGenerator::class)]
    private Uuid $id;

    #[ORM\Column(length: 255)]
    private ?string $title = null;

    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    private ?string $price = null;

    #[ORM\Column(length: 255)]
    private ?string $slug = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    /**
     * @var Collection<int, Image>
     */
    #[ORM\OneToMany(targetEntity: Image::class, mappedBy: 'product', cascade: ['persist', 'remove'])]
    private Collection $images;

    /**
     * @var Collection<int, ProductOption>
     */
    #[ORM\OneToMany(targetEntity: ProductOption::class, mappedBy: 'product', cascade: ['persist', 'remove'])]
    private Collection $productOptions;

    #[ORM\Column]
    private DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->images = new ArrayCollection();
        $this->productOptions = new ArrayCollection();
        $this->createdAt = new DateTimeImmutable();
    }

    public function getId(): null|Uuid
    {
        return $this->id;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(string $title): static
    {
        $this->title = $title;

        return $this;
    }

    public function getSlug(): ?string
    {
        return $this->slug;
    }

    public function setSlug(?string $slug): void
    {
        $this->slug = $slug;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function getCreatedAt(): ?DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    /**
     * @return Collection<int, Image>
     */
    public function getImages(): Collection
    {
        return $this->images;
    }

    public function addImage(Image $image): self
    {
        if (! $this->images->contains($image)) {
            $this->images->add($image);
            $image->setProduct($this);
        }

        return $this;
    }

    public function removeImage(Image $image): self
    {
        if ($this->images->removeElement($image) && $image->getProduct() === $this) {
            $image->setProduct(null);
        }

        return $this;
    }

    /**
     * @return Collection<int, ProductOption>
     */
    public function getProductOptions(): Collection
    {
        return $this->productOptions;
    }

    public function addProductOption(ProductOption $productOption): self
    {
        if (! $this->productOptions->contains($productOption)) {
            $this->productOptions->add($productOption);
            $productOption->setProduct($this);
        }

        return $this;
    }

    public function removeProductOption(ProductOption $productOption): self
    {
        if ($this->productOptions->removeElement($productOption) && $productOption->getProduct() === $this) {
            $productOption->setProduct(null);
        }

        return $this;
    }

    public function getMainImage(): ?Image
    {
        $main = $this->images->findFirst(
            fn(int $key, Image $image): bool => $image->getPosition() === 0
        );

        return $main ?? $this->images->first();
    }

    public function __toString(): string
    {
        return $this->getTitle();
    }

    public function getPrice(): ?string
    {
        return $this->price;
    }

    public function getPriceInGbp(): ?string
    {
        return (string) ((int) $this->price / 100);
    }

    public function setPrice(?string $price): void
    {
        $this->price = $price;
    }
}

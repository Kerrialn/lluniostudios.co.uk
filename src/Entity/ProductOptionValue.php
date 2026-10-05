<?php

namespace App\Entity;

use App\Repository\ProductOptionValueRepository;
use Carbon\CarbonImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\IdGenerator\UuidGenerator;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\Uid\Uuid;
use Vich\UploaderBundle\Mapping\Attribute as Vich;

#[ORM\Entity(repositoryClass: ProductOptionValueRepository::class)]
#[Vich\Uploadable]
class ProductOptionValue
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[ORM\CustomIdGenerator(UuidGenerator::class)]
    private null|Uuid $id = null;

    #[ORM\ManyToOne(targetEntity: ProductOption::class, inversedBy: 'productOptionValues')]
    private ProductOption $productOption;

    // Defaults to '' so a new, unsaved value can be labelled (EasyAdmin's form prototype).
    #[ORM\Column(type: Types::STRING)]
    private string $value = '';

    /**
     * Choosing this value sends the order to Request instead of checkout
     * (e.g. every "Custom…" option).
     */
    #[ORM\Column(options: [
        'default' => false,
    ])]
    private bool $goesToRequest = false;

    /**
     * Display order within its option (lowest first).
     */
    #[ORM\Column(options: [
        'default' => 0,
    ])]
    private int $position = 0;

    /**
     * Price difference in pence for choosing this value. Not used yet: the
     * price is set by size only. Kept so option prices can be added later.
     */
    #[ORM\Column(options: [
        'default' => 0,
    ])]
    private int $priceDifference = 0;

    /**
     * Optional real photograph of this option (e.g. a cable colour), stored
     * via Vich in the `images` mapping. Never a generated or stand-in image.
     */
    #[Vich\UploadableField(mapping: 'images', fileNameProperty: 'imageFilename')]
    private ?File $imageFile = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $imageFilename = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?CarbonImmutable $updatedAt = null;

    public function getId(): null|Uuid
    {
        return $this->id;
    }

    public function getProductOption(): ProductOption
    {
        return $this->productOption;
    }

    public function setProductOption(ProductOption $productOption): void
    {
        $this->productOption = $productOption;
    }

    public function getValue(): string
    {
        return $this->value;
    }

    public function setValue(string $value): void
    {
        $this->value = $value;
    }

    public function isGoesToRequest(): bool
    {
        return $this->goesToRequest;
    }

    public function setGoesToRequest(bool $goesToRequest): void
    {
        $this->goesToRequest = $goesToRequest;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function setPosition(int $position): void
    {
        $this->position = $position;
    }

    public function getPriceDifference(): int
    {
        return $this->priceDifference;
    }

    public function setPriceDifference(int $priceDifference): void
    {
        $this->priceDifference = $priceDifference;
    }

    public function setImageFile(?File $imageFile): void
    {
        $this->imageFile = $imageFile;

        // Vich needs a mapped field to change so the file is persisted.
        if ($imageFile instanceof File) {
            $this->updatedAt = new CarbonImmutable();
        }
    }

    public function getImageFile(): ?File
    {
        return $this->imageFile;
    }

    public function getImageFilename(): ?string
    {
        return $this->imageFilename;
    }

    public function setImageFilename(?string $imageFilename): void
    {
        $this->imageFilename = $imageFilename;
    }

    public function getUpdatedAt(): ?CarbonImmutable
    {
        return $this->updatedAt;
    }

    public function __toString(): string
    {
        return $this->getValue();
    }
}

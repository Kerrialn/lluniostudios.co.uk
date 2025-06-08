<?php

namespace App\Entity;

use Carbon\CarbonImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\IdGenerator\UuidGenerator;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\Uid\Uuid;
use Vich\UploaderBundle\Mapping\Annotation as Vich;

#[ORM\Entity]
#[Vich\Uploadable]
class Image
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[ORM\CustomIdGenerator(UuidGenerator::class)]
    private ?Uuid $id = null;

    #[ORM\ManyToOne(targetEntity: Product::class, inversedBy: 'images')]
    #[ORM\JoinColumn(onDelete: 'CASCADE')]
    private null|Product $product = null;

    #[Vich\UploadableField(mapping: 'images', fileNameProperty: 'filename')]
    private ?File $imageFile = null;

    #[ORM\Column(length: 255)]
    private ?string $filename = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $caption = null;

    #[ORM\Column(type: \Doctrine\DBAL\Types\Types::INTEGER)]
    private int $position = 0;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private CarbonImmutable $updatedAt;

    public function __construct()
    {
        $this->updatedAt = new CarbonImmutable();
    }

    public function getId(): ?Uuid
    {
        return $this->id;
    }

    public function setImageFile(?File $file): self
    {
        $this->imageFile = $file;

        if ($file instanceof \Symfony\Component\HttpFoundation\File\File) {
            $this->updatedAt = new CarbonImmutable();
        }

        return $this;
    }

    public function getImageFile(): ?File
    {
        return $this->imageFile;
    }

    public function setFilename(?string $name): self
    {
        $this->filename = $name;
        return $this;
    }

    public function getFilename(): ?string
    {
        return $this->filename;
    }

    public function setCaption(?string $cap): self
    {
        $this->caption = $cap;
        return $this;
    }

    public function getCaption(): ?string
    {
        return $this->caption;
    }

    public function setPosition(int $pos): self
    {
        $this->position = $pos;
        return $this;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function getUpdatedAt(): CarbonImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(CarbonImmutable $updatedAt): void
    {
        $this->updatedAt = $updatedAt;
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

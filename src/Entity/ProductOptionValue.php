<?php

namespace App\Entity;

use App\Repository\ProductOptionValueRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\IdGenerator\UuidGenerator;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: ProductOptionValueRepository::class)]
class ProductOptionValue
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[ORM\CustomIdGenerator(UuidGenerator::class)]
    private null|Uuid $id = null;

    #[ORM\ManyToOne(targetEntity: ProductOption::class, inversedBy: 'productOptionValue')]
    private ProductOption $productOption;

    #[ORM\Column(type: Types::STRING)]
    private string $value;

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

    public function __toString(): string
    {
        return $this->getValue();
    }
}

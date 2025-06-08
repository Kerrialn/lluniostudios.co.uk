<?php

namespace App\Entity;

use App\Repository\CartItemOptionRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\IdGenerator\UuidGenerator;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: CartItemOptionRepository::class)]
#[ORM\Table(name: 'cart_item_options', uniqueConstraints: [
    new ORM\UniqueConstraint(columns: ['cart_item_id', 'option_id']),
])]
class CartItemOption
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[ORM\CustomIdGenerator(UuidGenerator::class)]
    private Uuid $id;

    #[ORM\ManyToOne(targetEntity: CartItem::class, inversedBy: 'cartItemOptions')]
    #[ORM\JoinColumn(nullable: false)]
    private ?CartItem $cartItem = null;

    #[ORM\ManyToOne(targetEntity: ProductOption::class)]
    #[ORM\JoinColumn(name: 'product_option_id', referencedColumnName: 'id')]
    private ProductOption $productOption;

    #[ORM\ManyToOne(targetEntity: ProductOptionValue::class)]
    #[ORM\JoinColumn(name: 'product_option_value_id', referencedColumnName: 'id')]
    private ProductOptionValue $productOptionValue;

    public function __construct(
        CartItem $cartItem,
        ProductOption $productOption,
        ProductOptionValue $productOptionValue
    )
    {
        $this->cartItem = $cartItem;
        $this->productOption = $productOption;
        $this->productOptionValue = $productOptionValue;
        $cartItem->addCartItemOption($this);
    }

    public function getId(): null|Uuid
    {
        return $this->id;
    }

    public function getCartItem(): ?CartItem
    {
        return $this->cartItem;
    }

    public function setCartItem(?CartItem $cartItem): void
    {
        $this->cartItem = $cartItem;
    }

    public function getProductOption(): ProductOption
    {
        return $this->productOption;
    }

    public function setProductOption(ProductOption $productOption): void
    {
        $this->productOption = $productOption;
    }

    public function getProductOptionValue(): ProductOptionValue
    {
        return $this->productOptionValue;
    }

    public function setProductOptionValue(ProductOptionValue $productOptionValue): void
    {
        $this->productOptionValue = $productOptionValue;
    }
}

<?php

namespace App\Entity;

use App\Repository\CartItemOptionRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\IdGenerator\UuidGenerator;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: CartItemOptionRepository::class)]
// One value per option per lamp (lamp 0 = every lamp on the line).
#[ORM\UniqueConstraint(
    name: 'cart_item_option_lamp_unique',
    columns: ['cart_item_id', 'product_option_id', 'lamp']
)]
class CartItemOption
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[ORM\CustomIdGenerator(UuidGenerator::class)]
    private Uuid $id;

    #[ORM\ManyToOne(targetEntity: CartItem::class, inversedBy: 'cartItemOptions')]
    #[ORM\JoinColumn(nullable: false)]
    private ?CartItem $cartItem;

    #[ORM\ManyToOne(targetEntity: ProductOption::class)]
    #[ORM\JoinColumn(name: 'product_option_id', referencedColumnName: 'id')]
    private ProductOption $productOption;

    #[ORM\ManyToOne(targetEntity: ProductOptionValue::class)]
    #[ORM\JoinColumn(name: 'product_option_value_id', referencedColumnName: 'id')]
    private ProductOptionValue $productOptionValue;

    /**
     * Which lamp on the line this choice is for: 0 = every lamp, or 1..n when
     * the line is customised individually.
     */
    #[ORM\Column(options: [
        'default' => 0,
    ])]
    private int $lamp;

    public function __construct(
        CartItem $cartItem,
        ProductOption $productOption,
        ProductOptionValue $productOptionValue,
        int $lamp = 0,
    )
    {
        $this->cartItem = $cartItem;
        $this->productOption = $productOption;
        $this->productOptionValue = $productOptionValue;
        $this->lamp = $lamp;
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

    public function getLamp(): int
    {
        return $this->lamp;
    }

    public function setProductOptionValue(ProductOptionValue $productOptionValue): void
    {
        $this->productOptionValue = $productOptionValue;
    }
}

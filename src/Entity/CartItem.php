<?php

namespace App\Entity;

use App\Repository\CartItemRepository;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\IdGenerator\UuidGenerator;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: CartItemRepository::class)]
class CartItem
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[ORM\CustomIdGenerator(UuidGenerator::class)]
    private Uuid $id;

    #[ORM\Column(type: Types::INTEGER)]
    private int|null $quantity = null;

    #[ORM\Column(type: Types::INTEGER)]
    private int|null $unitPrice = null;

    #[ORM\Column]
    private DateTimeImmutable $createdAt;

    #[ORM\ManyToOne(targetEntity: Cart::class, cascade: ['persist'], inversedBy: 'cartItems')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Cart $cart = null;

    /**
     * @var Collection<int, CartItemOption>
     */
    #[ORM\OneToMany(targetEntity: CartItemOption::class, mappedBy: 'cartItem', cascade: ['persist'])]
    private Collection $cartItemOptions;

    #[ORM\ManyToOne(targetEntity: Product::class)]
    #[ORM\JoinColumn(name: 'product_id', referencedColumnName: 'id')]
    private null|Product $product = null;

    public function __construct(?int $quantity, ?Product $product)
    {
        $this->quantity = $quantity;
        $this->product = $product;
        $this->cartItemOptions = new ArrayCollection();
        $this->createdAt = new DateTimeImmutable();
    }

    public function getId(): null|Uuid
    {
        return $this->id;
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

    public function getCart(): ?Cart
    {
        return $this->cart;
    }

    public function setCart(?Cart $cart): static
    {
        $this->cart = $cart;

        return $this;
    }

    /**
     * @return Collection<int, CartItemOption>
     */
    public function getCartItemOptions(): Collection
    {
        return $this->cartItemOptions;
    }

    public function addCartItemOption(CartItemOption $cartItemOption): static
    {
        if (! $this->cartItemOptions->contains($cartItemOption)) {
            $this->cartItemOptions->add($cartItemOption);
            $cartItemOption->setCartItem($this);
        }

        return $this;
    }

    public function removeCartItemOption(CartItemOption $cartItemOption): static
    {
        // set the owning side to null (unless already changed)
        if ($this->cartItemOptions->removeElement($cartItemOption) && $cartItemOption->getCartItem() === $this) {
            $cartItemOption->setCartItem(null);
        }

        return $this;
    }

    public function getProduct(): ?Product
    {
        return $this->product;
    }

    public function setProduct(?Product $product): void
    {
        $this->product = $product;
    }

    public function getQuantity(): ?int
    {
        return $this->quantity;
    }

    public function setQuantity(?int $quantity): void
    {
        $this->quantity = $quantity;
    }

    public function getUnitPrice(): ?int
    {
        return $this->unitPrice;
    }

    public function setUnitPrice(?int $unitPrice): void
    {
        $this->unitPrice = $unitPrice;
    }
}

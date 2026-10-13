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
    private int|null $quantity;

    #[ORM\Column(type: Types::STRING, nullable: true)]
    private null|string $hash = null;

    /**
     * "Customise Individually": each lamp on this line has its own options
     * (CartItemOption::$lamp 1..quantity). Otherwise every option applies to
     * all lamps on the line (lamp 0).
     */
    #[ORM\Column(options: [
        'default' => false,
    ])]
    private bool $customisedIndividually = false;

    #[ORM\Column(type: Types::STRING, length: 255)]
    private string|null $unitPrice = null;

    #[ORM\Column]
    private DateTimeImmutable $createdAt;

    #[ORM\ManyToOne(targetEntity: Cart::class, cascade: ['persist'], inversedBy: 'cartItems')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Cart $cart = null;

    /**
     * @var Collection<int, CartItemOption>
     */
    #[ORM\OneToMany(targetEntity: CartItemOption::class, mappedBy: 'cartItem', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $cartItemOptions;

    #[ORM\ManyToOne(targetEntity: Product::class)]
    #[ORM\JoinColumn(name: 'product_id', referencedColumnName: 'id')]
    private null|Product $product;

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

    public function getUnitPrice(): ?string
    {
        return $this->unitPrice;
    }

    public function setUnitPrice(?string $unitPrice): void
    {
        $this->unitPrice = $unitPrice;
    }

    public function getHash(): ?string
    {
        return $this->hash;
    }

    public function setHash(?string $hash): void
    {
        $this->hash = $hash;
    }

    public function isCustomisedIndividually(): bool
    {
        return $this->customisedIndividually;
    }

    public function setCustomisedIndividually(bool $customisedIndividually): void
    {
        $this->customisedIndividually = $customisedIndividually;
    }

    /**
     * The configuration for display: one group for the whole line (label
     * null), or one group per lamp ("Lamp 1", "Lamp 2", ...) when customised
     * individually. Options are in configurator order.
     *
     * @return list<array{label: ?string, options: list<CartItemOption>}>
     */
    public function getLampConfigurations(): array
    {
        $options = $this->cartItemOptions->toArray();
        usort($options, static fn (CartItemOption $a, CartItemOption $b): int => $a->getProductOption()->getPosition() <=> $b->getProductOption()->getPosition());

        if (! $this->customisedIndividually) {
            return [[
                'label' => null,
                'options' => $options,
            ]];
        }

        $groups = [];
        for ($lamp = 1; $lamp <= ($this->quantity ?? 1); $lamp++) {
            $groups[] = [
                'label' => 'Lamp ' . $lamp,
                'options' => array_values(array_filter($options, static fn (CartItemOption $option): bool => $option->getLamp() === $lamp)),
            ];
        }

        return $groups;
    }

    public function getLineTotal(): int
    {
        return (int) $this->unitPrice * $this->quantity;
    }

    public function getLineTotalInGbp(): float
    {
        return $this->getLineTotal() / 100;
    }

    public function getUnitPriceInGbp(): float
    {
        return (int) $this->unitPrice / 100;
    }
}

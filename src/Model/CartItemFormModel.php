<?php

namespace App\Model;

class CartItemFormModel
{
    private ?int $quantity = null;

    /**
     * @var int[]
     */
    private array $options = [];

    public function getQuantity(): ?int
    {
        return $this->quantity;
    }

    public function setQuantity(?int $quantity): self
    {
        $this->quantity = $quantity;
        return $this;
    }

    /**
     * @return int[]  key = ProductOption ID, value = ProductOptionValue ID
     */
    public function getOptions(): array
    {
        return $this->options;
    }

    /**
     * We’ll rely on Symfony to populate $options via “options[<id>]” field names.
     *
     * @param int[] $options
     */
    public function setOptions(array $options): self
    {
        $this->options = $options;
        return $this;
    }
}

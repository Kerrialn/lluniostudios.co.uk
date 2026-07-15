<?php

declare(strict_types=1);

namespace App\Payment;

final readonly class RevolutOrder
{
    public function __construct(
        public string $id,
        public string $token,
        public string $state,
        public ?string $checkoutUrl = null,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            id: (string) $data['id'],
            token: (string) ($data['token'] ?? ''),
            state: (string) ($data['state'] ?? 'pending'),
            checkoutUrl: isset($data['checkout_url']) ? (string) $data['checkout_url'] : null,
        );
    }
}

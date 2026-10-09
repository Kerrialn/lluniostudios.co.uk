<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Where a gift goes (Gift options, decided 9 October 2026): to the buyer,
 * or directly to the recipient (whose name and address are the delivery's;
 * no prices in the box).
 */
enum GiftSendTo: string
{
    case ME = 'me';
    case RECIPIENT = 'recipient';

    public function label(): string
    {
        return match ($this) {
            self::ME => 'Send to me',
            self::RECIPIENT => 'Send directly to the recipient',
        };
    }

    public function isDirect(): bool
    {
        return $this === self::RECIPIENT;
    }
}

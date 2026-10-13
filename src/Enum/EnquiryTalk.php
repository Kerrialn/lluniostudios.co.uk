<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * "How would you like to talk?" on "Begin the Conversation" (Step 7c). A
 * phone or video call needs the phone number.
 *
 * The labels are DRAFT wording until the wording pass (11.5).
 */
enum EnquiryTalk: string
{
    case EMAIL = 'email';
    case PHONE = 'phone';
    case VIDEO = 'video';

    public function label(): string
    {
        return match ($this) {
            self::EMAIL => 'Email',
            self::PHONE => 'Phone',
            self::VIDEO => 'Video call',
        };
    }

    /**
     * How the studio will be in touch, for the thank-you page and the
     * customer's email: "by email", "by phone", "to arrange a video call".
     */
    public function reachPhrase(): string
    {
        return match ($this) {
            self::EMAIL => 'by email',
            self::PHONE => 'by phone',
            self::VIDEO => 'to arrange a video call',
        };
    }

    public function needsPhone(): bool
    {
        return $this !== self::EMAIL;
    }
}

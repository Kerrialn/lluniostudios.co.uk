<?php

declare(strict_types=1);

namespace App\Enum;

enum NewsletterSubscriberStatus: string
{
    case Pending = 'pending';           // signed up, not confirmed yet (double opt-in)
    case Subscribed = 'subscribed';     // confirmed
    case Unsubscribed = 'unsubscribed'; // user opted out
    case Bounced = 'bounced';           // hard bounce from provider
    case Complained = 'complained';     // spam complaint
}


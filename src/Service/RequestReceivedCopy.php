<?php

declare(strict_types=1);

namespace App\Service;

use App\Catalogue\LumenFormaCatalogue;
use App\Entity\DesignRequest;

/**
 * Email 02's opening sentence, one per combination of the reasons a design
 * is reserved rather than bought directly: a Custom choice, more than the
 * checkout limit of lamps, a larger (Request) size. Wording approved
 * 7 October 2026. The rest of the email is the same in every version.
 */
final class RequestReceivedCopy
{
    public const CUSTOM = "Your design includes a custom option, so we'll be in touch to develop it with you and confirm the details before anything is cast.";

    public const MORE_THAN_TWO = "Your order contains more than two lamps, so we'll be in touch to confirm lead time according to our casting schedule.";

    public const LARGER_SIZE = "Your design is one of our larger sizes, so we'll be in touch to confirm lead time according to our casting schedule, including a bespoke delivery quote.";

    public const CUSTOM_AND_MORE_THAN_TWO = "Your order is for more than two lamps and includes a custom option, so we'll be in touch to develop them with you and confirm the details before anything is cast.";

    public const CUSTOM_AND_LARGER_SIZE = "Your design is one of our larger sizes and includes a custom option, so we'll be in touch to develop it with you and to confirm lead time according to our casting schedule, including a bespoke delivery quote.";

    public const MORE_THAN_TWO_AND_LARGER_SIZE = "Your order is for more than two lamps and includes one of our larger sizes, so we'll be in touch to confirm lead time according to our casting schedule, including a bespoke delivery quote.";

    public const ALL_THREE = "Your order is for more than two lamps, includes one of our larger sizes and has a custom option, so we'll be in touch to develop them and confirm lead time according to our casting schedule, including a bespoke delivery quote.";

    /**
     * None of the reasons (only possible by opening the Reserve page directly).
     */
    public const NONE = 'We review every request by hand before confirming.';

    /**
     * The paragraph after the opening, in every version.
     */
    public const SECURED = 'Your casting slot is now secured. No payment has been taken.';

    public static function opening(DesignRequest $designRequest): string
    {
        $custom = false;
        $larger = false;
        foreach ($designRequest->getItems() as $item) {
            foreach ($item->getOptionsSnapshot() as $entry) {
                $custom = $custom || LumenFormaCatalogue::goesToRequest($entry['value']);
            }

            $larger = $larger || ($item->getProduct()?->isDirectOrder() === false);
        }

        $moreThanTwo = $designRequest->getLampCount() > BasketRules::MAX_CHECKOUT_LAMPS;

        return match (true) {
            $custom && $moreThanTwo && $larger => self::ALL_THREE,
            $custom && $moreThanTwo => self::CUSTOM_AND_MORE_THAN_TWO,
            $custom && $larger => self::CUSTOM_AND_LARGER_SIZE,
            $moreThanTwo && $larger => self::MORE_THAN_TWO_AND_LARGER_SIZE,
            $custom => self::CUSTOM,
            $moreThanTwo => self::MORE_THAN_TWO,
            $larger => self::LARGER_SIZE,
            default => self::NONE,
        };
    }
}

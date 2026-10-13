<?php

declare(strict_types=1);

namespace App\Seo;

/**
 * Which pages search engines may show (Step 11b, technical groundwork,
 * 13 October 2026). Only these are indexable, carry a canonical link and are
 * listed in sitemap.xml; every other page (admin, the basket, checkout,
 * quotes, orders, the account, sign-in, the forms and every thank-you page)
 * is sent with "noindex, nofollow" (SearchIndexingListener), and the private
 * areas are also kept out of robots.txt.
 */
final class SearchPages
{
    /**
     * Route names of the indexable pages, in sitemap order.
     */
    public const INDEXABLE = ['landing', 'lighting_designs', 'commissions', 'workshop', 'contact', 'terms'];

    /**
     * Paths robots.txt asks crawlers not to visit: the private and buying
     * areas, the forms and the thank-you pages. (They are also "noindex".)
     */
    public const DISALLOWED = [
        '/admin',
        '/basket',
        '/cart',
        '/checkout',
        '/quote/',
        '/order/',
        '/account',
        '/login',
        '/reserve',
        '/begin-the-conversation',
        '/bespoke-commissions/received',
    ];

    public static function isIndexable(?string $route): bool
    {
        return \in_array($route, self::INDEXABLE, true);
    }
}

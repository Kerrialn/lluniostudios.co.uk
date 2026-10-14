<?php

declare(strict_types=1);

namespace App\Seo;

/**
 * Which pages search engines may show (Step 11b, technical groundwork,
 * 13 October 2026). Only these are indexable, carry a canonical link and are
 * listed in sitemap.xml; every other page (admin, the basket, checkout,
 * quotes, orders, the account, sign-in, the forms and every thank-you page)
 * is sent with "noindex, nofollow" (SearchIndexingListener). robots.txt blocks
 * admin only (decided 13 October 2026): the other private pages rely on
 * "noindex", which search engines can only see if they may fetch the page.
 */
final class SearchPages
{
    /**
     * Route names of the indexable pages, in sitemap order.
     */
    public const INDEXABLE = ['landing', 'lighting_designs', 'commissions', 'workshop', 'contact', 'terms', 'privacy'];

    /**
     * Paths robots.txt asks crawlers not to visit: admin only (also "noindex").
     */
    public const DISALLOWED = ['/admin'];

    public static function isIndexable(?string $route): bool
    {
        return \in_array($route, self::INDEXABLE, true);
    }
}

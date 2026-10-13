<?php

declare(strict_types=1);

namespace App\Event\Listener;

use App\Seo\SearchPages;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Keeps every page except the indexable ones (SearchPages) out of search
 * results: an "X-Robots-Tag: noindex, nofollow" header, unless the page set
 * its own (Step 11b). robots.txt and sitemap.xml are left alone.
 */
#[AsEventListener(event: KernelEvents::RESPONSE)]
final class SearchIndexingListener
{
    private const UNTOUCHED = ['seo_robots', 'seo_sitemap'];

    public function __invoke(ResponseEvent $event): void
    {
        if (! $event->isMainRequest()) {
            return;
        }

        $route = $event->getRequest()->attributes->get('_route');
        $route = \is_string($route) ? $route : null;
        $response = $event->getResponse();
        if (SearchPages::isIndexable($route) && $response->isSuccessful()) {
            return;
        }

        if (\in_array($route, self::UNTOUCHED, true) || $response->headers->has('X-Robots-Tag')) {
            return;
        }

        $response->headers->set('X-Robots-Tag', 'noindex, nofollow');
    }
}

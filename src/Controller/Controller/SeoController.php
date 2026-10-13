<?php

declare(strict_types=1);

namespace App\Controller\Controller;

use App\Seo\SearchPages;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * robots.txt and sitemap.xml (Step 11b), built from SearchPages so they
 * always match which pages are indexable, with absolute addresses on
 * whichever domain the site is served from.
 */
final class SeoController extends AbstractController
{
    #[Route(path: '/robots.txt', name: 'seo_robots', methods: ['GET'])]
    public function robots(): Response
    {
        $lines = ['User-agent: *'];
        foreach (SearchPages::DISALLOWED as $path) {
            $lines[] = 'Disallow: ' . $path;
        }

        $lines[] = '';
        $lines[] = 'Sitemap: ' . $this->generateUrl('seo_sitemap', [], UrlGeneratorInterface::ABSOLUTE_URL);

        return new Response(implode("\n", $lines) . "\n", Response::HTTP_OK, [
            'Content-Type' => 'text/plain; charset=UTF-8',
        ]);
    }

    #[Route(path: '/sitemap.xml', name: 'seo_sitemap', methods: ['GET'])]
    public function sitemap(): Response
    {
        $urls = array_map(fn (string $route): string => $this->generateUrl($route, [], UrlGeneratorInterface::ABSOLUTE_URL), SearchPages::INDEXABLE);

        return $this->render('seo/sitemap.xml.twig', [
            'urls' => $urls,
        ], new Response(headers: [
            'Content-Type' => 'application/xml; charset=UTF-8',
        ]));
    }
}

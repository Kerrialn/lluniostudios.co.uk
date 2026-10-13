<?php

declare(strict_types=1);

namespace App\Twig;

use App\Catalogue\LumenFormaConfigurator;
use App\Catalogue\LumenFormaPhotos;
use App\Seo\SearchPages;
use Symfony\Component\Asset\Packages;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Search and link-preview groundwork (Step 11b, 13 October 2026), used by
 * base.html.twig: whether the page is indexable, its canonical address, a
 * preview photo with its size, and the structured data (Llunio Studios as a
 * LocalBusiness; the Lumen Forma sizes as Products with their prices).
 */
final class SeoExtension extends AbstractExtension
{
    public const INSTAGRAM = 'https://www.instagram.com/llunio_studios/';

    public const YOUTUBE = 'https://www.youtube.com/@LlunioStudios';

    public function __construct(
        private readonly RequestStack $requestStack,
        private readonly UrlGeneratorInterface $urls,
        private readonly Packages $packages,
        private readonly LumenFormaConfigurator $configurator,
        #[Autowire('%kernel.project_dir%')]
        private readonly string $projectDir,
        #[Autowire('%env(STUDIO_EMAIL)%')]
        private readonly string $studioEmail = '',
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('seo_indexable', $this->indexable(...)),
            new TwigFunction('seo_canonical', $this->canonical(...)),
            new TwigFunction('seo_image', $this->image(...)),
            new TwigFunction('seo_business', $this->business(...)),
            new TwigFunction('seo_products', $this->products(...)),
        ];
    }

    public function indexable(): bool
    {
        return SearchPages::isIndexable($this->route());
    }

    /**
     * The page's own address, without any query, for an indexable page.
     */
    public function canonical(): ?string
    {
        $route = $this->route();
        if (! SearchPages::isIndexable($route)) {
            return null;
        }

        $parameters = $this->requestStack->getMainRequest()?->attributes->get('_route_params');

        return $this->urls->generate((string) $route, \is_array($parameters) ? $parameters : [], UrlGeneratorInterface::ABSOLUTE_URL);
    }

    /**
     * A real photo for link previews: its absolute address and size.
     *
     * @return array{url: string, width: ?int, height: ?int}
     */
    public function image(string $path): array
    {
        $size = @getimagesize($this->projectDir . '/public/' . ltrim($path, '/'));

        return [
            'url' => $this->absolute($this->packages->getUrl($path)),
            'width' => $size === false ? null : $size[0],
            'height' => $size === false ? null : $size[1],
        ];
    }

    /**
     * Llunio Studios as a LocalBusiness (Organization), with the studio's full
     * address as on the Contact page (decided 13 October 2026). Facebook is
     * left out until the page exists.
     *
     * @return array<string, mixed>
     */
    public function business(): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'LocalBusiness',
            '@id' => $this->urls->generate('landing', [], UrlGeneratorInterface::ABSOLUTE_URL) . '#business',
            'name' => 'Llunio Studios',
            'description' => 'Sculptural lamps hand cast in liquid stone in our Dorset Studio.',
            'url' => $this->urls->generate('landing', [], UrlGeneratorInterface::ABSOLUTE_URL),
            'logo' => $this->absolute($this->packages->getUrl('images/brand/logo-full-dark.png')),
            'image' => $this->absolute($this->packages->getUrl('images/home/hero-bright-2560.jpg')),
            'email' => $this->studioEmail,
            'address' => [
                '@type' => 'PostalAddress',
                'streetAddress' => 'Hogsmeade, Higher Merley Ln',
                'addressLocality' => 'Wimborne',
                'postalCode' => 'BH21 3EG',
                'addressRegion' => 'Dorset',
                'addressCountry' => 'GB',
            ],
            'sameAs' => [self::INSTAGRAM, self::YOUTUBE],
        ];
    }

    /**
     * The Lumen Forma sizes as Products: Studio and Gallery at their price,
     * Atelier, Estate and Monument "from" their price (an AggregateOffer with
     * the lowest price). Made to order. A photo only where a real one exists
     * (Gallery), never another size's.
     *
     * @return array<string, mixed>
     */
    public function products(): array
    {
        $url = $this->urls->generate('lighting_designs', [], UrlGeneratorInterface::ABSOLUTE_URL);
        $graph = [];
        foreach ($this->configurator->sizeProducts() as $product) {
            $name = LumenFormaConfigurator::sizeName($product);
            $price = number_format((int) $product->getPrice() / 100, 2, '.', '');
            $dimensions = array_map(static fn (?int $mm): string => (string) round((int) $mm / 10), [$product->getLengthMm(), $product->getWidthMm(), $product->getHeightMm()]);
            $photos = LumenFormaPhotos::forSize((string) $product->getSlug());
            $node = [
                '@type' => 'Product',
                'name' => 'Lumen Forma ' . $name,
                'description' => 'A Lumen Forma ' . $name . ' lamp, ' . implode(' × ', $dimensions) . ' cm, hand cast in liquid stone to order in our Dorset Studio.',
                'brand' => [
                    '@type' => 'Brand',
                    'name' => 'Llunio Studios',
                ],
                'url' => $url,
                'offers' => $product->isDirectOrder()
                    ? [
                        '@type' => 'Offer',
                        'price' => $price,
                        'priceCurrency' => 'GBP',
                        'availability' => 'https://schema.org/MadeToOrder',
                        'url' => $url,
                    ]
                    : [
                        '@type' => 'AggregateOffer',
                        'lowPrice' => $price,
                        'priceCurrency' => 'GBP',
                        'offerCount' => 1,
                        'availability' => 'https://schema.org/MadeToOrder',
                        'url' => $url,
                    ],
            ];
            if ($photos !== []) {
                $node['image'] = $this->absolute($this->packages->getUrl('images/lumen-forma/' . $photos[0]['file'] . '-2000.jpg'));
            }

            $graph[] = $node;
        }

        return [
            '@context' => 'https://schema.org',
            '@graph' => $graph,
        ];
    }

    private function route(): ?string
    {
        $route = $this->requestStack->getMainRequest()?->attributes->get('_route');

        return \is_string($route) ? $route : null;
    }

    private function absolute(string $path): string
    {
        $request = $this->requestStack->getMainRequest();

        return ! $request instanceof \Symfony\Component\HttpFoundation\Request || str_starts_with($path, 'http') ? $path : $request->getSchemeAndHttpHost() . $path;
    }
}

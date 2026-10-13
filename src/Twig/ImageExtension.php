<?php

declare(strict_types=1);

namespace App\Twig;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * The speed pass (13 October 2026): every photo also exists as WebP (the
 * same size, re-encoded; see docs/merge-plan.md), offered to browsers that
 * support it through <picture><source type="image/webp">, with the JPEG
 * kept as the fallback. webp_srcset() turns an image list ("a-1000.jpg
 * 1000w, a-2000.jpg 2000w") into its WebP twin, or null when any WebP file
 * is missing (so nothing is ever offered that doesn't exist).
 */
final class ImageExtension extends AbstractExtension
{
    public function __construct(
        #[Autowire('%kernel.project_dir%')]
        private readonly string $projectDir,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('webp_srcset', $this->webpSrcset(...)),
        ];
    }

    public function webpSrcset(string $srcset): ?string
    {
        $candidates = [];
        foreach (array_filter(array_map('trim', explode(',', $srcset))) as $candidate) {
            [$url, $descriptor] = array_pad(preg_split('/\s+/', $candidate, 2) ?: [], 2, '');
            $webp = preg_replace('/\.(jpe?g|png)(\?.*)?$/i', '.webp$2', (string) $url);
            $path = parse_url((string) $webp, \PHP_URL_PATH);
            if ($webp === $url || ! \is_string($path) || ! is_file($this->projectDir . '/public' . $path)) {
                return null;
            }

            $candidates[] = trim($webp . ' ' . $descriptor);
        }

        return $candidates === [] ? null : implode(', ', $candidates);
    }
}

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
 *
 * photo_srcset() (14 October 2026): a photo's versions ("name-1000",
 * "name-2000", "name-3000", "name-4000", by longest edge, as far as the
 * original allows) described by their true width, so a large or high-density
 * screen is offered a version wide enough and a phone a small one. The
 * WebP versions go up to 4000; the JPEG fallbacks to 2000.
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
            new TwigFunction('photo_srcset', $this->photoSrcset(...)),
        ];
    }

    /**
     * @var array<string, string>
     */
    private array $photoSrcsets = [];

    /**
     * "/images/x-1000.webp 749w, /images/x-2000.webp 1499w, …" for the
     * versions of $base (e.g. "images/lumen-forma/gallery-01") that exist.
     */
    public function photoSrcset(string $base, string $extension = 'jpg'): string
    {
        $key = $base . '.' . $extension;
        if (isset($this->photoSrcsets[$key])) {
            return $this->photoSrcsets[$key];
        }

        $candidates = [];
        foreach ([1000, 2000, 3000, 4000] as $edge) {
            $path = '/' . ltrim($base, '/') . '-' . $edge . '.' . $extension;
            $size = is_file($this->projectDir . '/public' . $path) ? @getimagesize($this->projectDir . '/public' . $path) : false;
            if ($size !== false) {
                $candidates[] = $path . ' ' . $size[0] . 'w';
            }
        }

        return $this->photoSrcsets[$key] = implode(', ', $candidates);
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

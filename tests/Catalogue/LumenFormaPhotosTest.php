<?php

declare(strict_types=1);

namespace App\Tests\Catalogue;

use App\Catalogue\LumenFormaCatalogue;
use App\Catalogue\LumenFormaPhotos;
use PHPUnit\Framework\TestCase;

final class LumenFormaPhotosTest extends TestCase
{
    public function testEveryListedPhotoExistsInBothSizes(): void
    {
        $files = array_merge(
            ...array_map(static fn (array $rows): array => array_column($rows, 0), array_values(LumenFormaPhotos::BY_SIZE)),
        );
        foreach (LumenFormaPhotos::forOptions() as $item) {
            $files[] = $item['photo']['file'];
        }

        self::assertNotEmpty($files);
        foreach ($files as $file) {
            foreach (['2000', '1000'] as $edge) {
                self::assertFileExists(\dirname(__DIR__, 2) . "/public/images/lumen-forma/{$file}-{$edge}.jpg");
            }
        }
    }

    public function testOnlyRealSizesHavePhotos(): void
    {
        $slugs = array_column(LumenFormaCatalogue::SIZES, 'slug');

        self::assertSame(['lumen-forma-gallery'], array_keys(LumenFormaPhotos::BY_SIZE));
        self::assertContains(LumenFormaPhotos::IDLE_SIZE, $slugs);
        self::assertCount(17, LumenFormaPhotos::forSize('lumen-forma-gallery'));
        self::assertSame([], LumenFormaPhotos::forSize('lumen-forma-studio'));
    }

    public function testOptionPhotosMatchRealOptionValues(): void
    {
        $real = [];
        foreach (LumenFormaCatalogue::OPTIONS as $option) {
            foreach ($option['values'] as $value) {
                $real[] = $option['name'] . ' / ' . $value;
            }
        }

        $withPhotos = array_map(static fn (array $item): string => $item['option'] . ' / ' . $item['value'], LumenFormaPhotos::forOptions());

        self::assertSame([], array_values(array_diff($withPhotos, $real)));
    }

    public function testFraming(): void
    {
        $photo = LumenFormaPhotos::forSize('lumen-forma-gallery')[0];

        self::assertSame([
            'fit' => 'cover',
            'position' => '0% 50%',
        ], $photo['upright']);
        self::assertSame([
            'fit' => 'contain',
            'position' => '100% 50%',
        ], $photo['sideways']);
        self::assertSame([
            'fit' => 'contain',
            'position' => '50% 50%',
        ], LumenFormaPhotos::forSize('lumen-forma-gallery')[2]['upright']);
    }
}

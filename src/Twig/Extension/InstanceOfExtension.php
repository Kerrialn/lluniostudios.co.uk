<?php

declare(strict_types=1);

namespace App\Twig\Extension;

use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

class InstanceOfExtension extends AbstractExtension
{
    public function getFilters(): array
    {
        return [new TwigFilter('instanceof', $this->isInstanceOf(...))];
    }

    public function isInstanceOf(object $object, string $className): bool
    {
        return $object instanceof $className;
    }
}

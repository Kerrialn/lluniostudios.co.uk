<?php

declare(strict_types=1);

namespace App\Twig\Extension;

use Nette\Utils\Strings;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

class WebalizeExtension extends AbstractExtension
{
    public function getFilters(): array
    {
        return [new TwigFilter('webalize', $this->webalize(...))];
    }

    public function webalize(string $string): string
    {
        return Strings::webalize($string);
    }
}

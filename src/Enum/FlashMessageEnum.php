<?php

declare(strict_types=1);

namespace App\Enum;

enum FlashMessageEnum: string
{
    case SUSCCESS = 'success';
    case WARNING = 'warning';
    case ERROR = 'error';
}

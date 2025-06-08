<?php

declare(strict_types=1);

namespace App\Service;

use Symfony\Component\HttpFoundation\Request;

class FingerPrintService
{
    public function generate(Request $request): string
    {
        $data = [
            $request->getClientIp(),
            $request->headers->get('User-Agent'),
            $request->headers->get('Accept-Language'),
        ];

        return hash('sha256', implode('|', array_filter($data)));
    }
}

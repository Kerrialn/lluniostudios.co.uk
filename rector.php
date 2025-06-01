<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;

return RectorConfig::configure()
    ->withPaths([
        __DIR__ . '/config',
        __DIR__ . '/public',
        __DIR__ . '/src',
        __DIR__ . '/tests',
        __DIR__ . '/translations',
    ])
    ->withPreparedSets(deadCode: true, codeQuality: true, naming: true, doctrineCodeQuality: true);

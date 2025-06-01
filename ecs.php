<?php

declare(strict_types=1);

use PhpCsFixer\Fixer\Import\NoUnusedImportsFixer;
use Symplify\EasyCodingStandard\Config\ECSConfig;

return ECSConfig::configure()
    ->withPaths([
        __DIR__ . '/config',
        __DIR__ . '/public',
        __DIR__ . '/src',
        __DIR__ . '/tests',
        __DIR__ . '/translations',
    ])

    ->withPreparedSets(
         arrays: true,
         namespaces: true,
         spaces: true,
         docblocks: true,
         comments: true,
     )
     
     ;

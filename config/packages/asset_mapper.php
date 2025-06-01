<?php

declare(strict_types=1);

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $containerConfigurator): void {

    $containerConfigurator->extension('framework', [
        'asset_mapper' => [
            // @phpstan-ignore-next-line
            'server' => '%env(APP_ENV)%' !== 'prod',
            'paths' => [
                'assets/',
            ],
        ],
    ]);
};

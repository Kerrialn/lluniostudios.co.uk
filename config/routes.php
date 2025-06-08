<?php

declare(strict_types=1);

use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

return static function (RoutingConfigurator $routingConfigurator): void {
    $routingConfigurator->import([
        'path' => __DIR__ . '/../src/Controller/Controller',
        'namespace' => 'App\Controller\Controller',
    ], 'attribute');

    $routingConfigurator->import([
        'path' => __DIR__ . '/../src/Controller/Admin',
        'namespace' => 'App\Controller\Admin',
    ], 'attribute');
};

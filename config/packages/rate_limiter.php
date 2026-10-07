<?php

declare(strict_types=1);

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $containerConfigurator): void {
    $containerConfigurator->extension('framework', [
        'rate_limiter' => [
            // Bespoke Commissions: at most 5 enquiries an hour from one
            // connection (only complete enquiries count). The browser checks
            // clear it with: php bin/console cache:pool:clear cache.rate_limiter
            'commission_enquiry' => [
                'policy' => 'sliding_window',
                'limit' => 5,
                'interval' => '1 hour',
            ],
        ],
    ]);
};

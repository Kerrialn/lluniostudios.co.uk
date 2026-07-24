<?php

declare(strict_types=1);

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $containerConfigurator): void {
    $containerConfigurator->extension('twig', [
        'file_name_pattern' => '*.twig',
        'globals' => [
            'is_under_maintenance' => '%env(APP_UNDER_MAINTENANCE)%',
        ],
        'form_themes' => [
            'form/theme/tailwind_layout.html.twig',
            'form/type/integer_type.html.twig',
            'form/type/pin_code.html.twig',
        ],
    ]);
    if ($containerConfigurator->env() === 'test') {
        $containerConfigurator->extension('twig', [
            'strict_variables' => true,
        ]);
    }
};

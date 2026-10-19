<?php

declare(strict_types=1);

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $containerConfigurator): void {
    $containerConfigurator->extension('twig', [
        'file_name_pattern' => '*.twig',
        'globals' => [
            'lead_time' => '@App\Catalogue\LeadTime',
            // The studio's public email (STUDIO_EMAIL in .env).
            'studio_email' => '%env(STUDIO_EMAIL)%',
            // The studio's Facebook page (FACEBOOK_URL in .env; a placeholder until the page exists).
            'facebook_url' => '%env(FACEBOOK_URL)%',
        ],
        'form_themes' => [
            'form/theme/tailwind_layout.html.twig',
            'form/type/pin_code.html.twig',
        ],
    ]);
    if ($containerConfigurator->env() === 'test') {
        $containerConfigurator->extension('twig', [
            'strict_variables' => true,
        ]);
    }
};

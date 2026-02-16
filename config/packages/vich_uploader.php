<?php

declare(strict_types=1);

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Vich\UploaderBundle\Naming\SmartUniqueNamer;

return static function (ContainerConfigurator $containerConfigurator): void {
    $containerConfigurator->extension('vich_uploader', [
        'db_driver' => 'orm',
        'mappings' => [
            'images' => [
                'uri_prefix' => '/uploads/images',
                'upload_destination' => '%kernel.project_dir%/public/uploads/images',
                'namer' => SmartUniqueNamer::class,
            ],
        ],
    ]);
};

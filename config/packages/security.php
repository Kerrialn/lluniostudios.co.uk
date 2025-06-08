<?php

declare(strict_types=1);

use App\Entity\User;
use App\Provider\FingerprintUserProvider;
use App\Security\EmailAuthenticator;
use App\Security\FingerprintAuthenticator;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;

return static function (ContainerConfigurator $containerConfigurator): void {
    $containerConfigurator->extension('security', [
        'password_hashers' => [
            PasswordAuthenticatedUserInterface::class => 'auto',
        ],

        // === Define your providers, including a chain ===
        'providers' => [
            'app_user_provider' => [
                'entity' => [
                    'class' => User::class,
                    'property' => 'email',
                ],
            ],
            'guest_user_provider' => [
                'id' => FingerprintUserProvider::class,
            ],
            'chain_provider' => [
                'chain' => [
                    'providers' => [
                        'app_user_provider',
                        'guest_user_provider',
                    ],
                ],
            ],
        ],

        'firewalls' => [
            'dev' => [
                'pattern' => '^/(_(profiler|wdt)|css|images|js)/',
                'security' => false,
            ],

            'main' => [
                'lazy' => true,
                'provider' => 'chain_provider',
                'custom_authenticators' => [
                    EmailAuthenticator::class,
                    FingerprintAuthenticator::class,
                ],
                'entry_point' => FingerprintAuthenticator::class,
                'form_login' => [
                    'login_path' => 'app_login',
                    'check_path' => 'app_login',
                    'enable_csrf' => true,
                ],
                'logout' => [
                    'path' => 'app_logout',
                    'invalidate_session' => true,
                    'delete_cookies' => ['REMEMBERME'],
                ],
                'remember_me' => [
                    'secret' => '%kernel.secret%',
                    'lifetime' => 604800,
                    'path' => '/',
                ],
            ],
        ],

        'access_control' => [
            [
                'path' => '^/login',
                'roles' => ['PUBLIC_ACCESS'],
            ],
            [
                'path' => '^/register',
                'roles' => ['PUBLIC_ACCESS'],
            ],
            [
                'path' => '^/',
                'roles' => ['ROLE_USER'],
            ],
        ],
    ]);
};

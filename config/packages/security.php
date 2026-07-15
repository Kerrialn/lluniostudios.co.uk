<?php

declare(strict_types=1);

use App\Entity\User;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;

return static function (ContainerConfigurator $containerConfigurator): void {
    $containerConfigurator->extension('security', [
        'password_hashers' => [
            PasswordAuthenticatedUserInterface::class => 'auto',
        ],

        'providers' => [
            'app_user_provider' => [
                'entity' => [
                    'class' => User::class,
                    'property' => 'email',
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
                'provider' => 'app_user_provider',
                'form_login' => [
                    'login_path' => 'app_login',
                    'check_path' => 'app_login',
                    'username_parameter' => 'email',
                    'password_parameter' => 'password',
                    'enable_csrf' => true,
                    'default_target_path' => 'account_orders',
                ],
                'logout' => [
                    'path' => 'app_logout',
                    'target' => 'landing',
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

        // Everything is public except the customer account area. Checkout is
        // public so guests can start it; an account is created mid-checkout.
        'access_control' => [
            [
                'path' => '^/account',
                'roles' => ['ROLE_USER'],
            ],
            [
                'path' => '^/admin',
                'roles' => ['ROLE_ADMIN'],
            ],
        ],
    ]);
};

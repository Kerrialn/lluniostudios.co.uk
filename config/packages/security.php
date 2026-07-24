<?php

declare(strict_types=1);

use App\Entity\User;
use App\Security\EmailCodeAuthenticator;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $containerConfigurator): void {
    $containerConfigurator->extension('security', [
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
                'custom_authenticators' => [
                    EmailCodeAuthenticator::class,
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
                    // No password to sign against (passwordless). Sign the cookie
                    // on the email so changing it invalidates old remember-me cookies.
                    'signature_properties' => ['email'],
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

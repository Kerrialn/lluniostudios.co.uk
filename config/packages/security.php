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
                // The sign-in email's one-click "Sign in" button (Step 10.6):
                // a signed link, valid for 10 minutes. It may be opened more
                // than once in that time, because some email services open
                // links to check them before the customer clicks; it stops
                // working if the account's email changes.
                'login_link' => [
                    'check_route' => 'login_check',
                    'signature_properties' => ['id', 'email'],
                    'lifetime' => 600,
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

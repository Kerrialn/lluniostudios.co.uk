<?php

// src/Event/Listener/MaintenanceListener.php

namespace App\Event\Listener;

use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\Routing\RouterInterface;

#[AsEventListener(
    event: 'kernel.request',
    method: 'onKernelRequest',
    priority: 30  // anything less than 32 so the RouterListener runs first
)]
class MaintenanceListener
{
    public function __construct(
        private ParameterBagInterface $parameterBag,
        private RouterInterface $router,
        private LoggerInterface $logger,
        #[Autowire('%kernel.environment%')]
        private readonly string $env
    ) {}

    public function onKernelRequest(RequestEvent $requestEvent): void
    {
        $request = $requestEvent->getRequest();

        // 1) Only run on the "main" HTTP request
        if (! $requestEvent->isMainRequest()) {
            return;
        }

        // 2) Ensure routing has already set "_route"
        if (! $request->attributes->has('_route')) {
            return;
        }

        $currentRoute = $request->attributes->get('_route');

        // 3) Skip any Symfony-internal route (which all start with "_")
        if ($this->env === 'dev' && str_starts_with($currentRoute, '_')) {
            return;
        }

        // 4) Only redirect true HTML page‐loads (skip CSS/JS/Ajax/etc.)
        if ($request->getRequestFormat() !== 'html') {
            return;
        }

        // 5) If not in maintenance mode, bail out
        $isUnderMaintenance = (bool) $this->parameterBag->get('app.is_under_maintenance');
        if (! $isUnderMaintenance) {
            return;
        }

        // 6) Never redirect if we’re already on the maintenance route/path
        $maintenanceRoute = 'maintenance';
        $maintenancePath = $this->router->generate($maintenanceRoute); // e.g. "/maintenance"

        if ($currentRoute === $maintenanceRoute || $request->getPathInfo() === $maintenancePath) {
            return;
        }

        // 7) Finally: do the redirect once
        $this->logger->info(sprintf(
            '[MaintenanceListener] Redirecting "%s" (route="%s", format="%s") → "%s"',
            $request->getPathInfo(),
            $currentRoute,
            $request->getRequestFormat(),
            $maintenancePath
        ));

        $requestEvent->setResponse(new RedirectResponse($maintenancePath, 302));
    }
}

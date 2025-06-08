<?php

declare(strict_types=1);

namespace App\Event\Listener;

use App\Entity\Fingerprint;
use App\Repository\FingerprintRepository;
use App\Service\FingerPrintService;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\ControllerEvent;

#[AsEventListener(event: ControllerEvent::class, method: 'generateFingerprint', priority: 90)]
readonly class FingerprintSubscriber
{
    public function __construct(
        private FingerPrintService $fingerPrintService,
        private FingerprintRepository $fingerprintRepository
    )
    {
    }

    public function generateFingerprint(ControllerEvent $controllerEvent): void
    {
        if (! $controllerEvent->isMainRequest()) {
            return;
        }

        $request = $controllerEvent->getRequest();
        $fingerprintToken = $this->fingerPrintService->generate(request: $request);
        $fingerprint = $this->fingerprintRepository->findOneBy([
            'fingerprint' => $fingerprintToken,
        ]);

        if (! $fingerprint instanceof Fingerprint) {
            $fingerprint = new Fingerprint($fingerprintToken);
            $this->fingerprintRepository->save($fingerprint, true);
        }

    }
}

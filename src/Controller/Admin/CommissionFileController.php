<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\CommissionEnquiryFile;
use App\Service\CommissionFileStorage;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Downloads a file attached to a Bespoke Commissions enquiry, for the studio
 * only. Always as a download under the customer's file name, never opened in
 * the browser.
 */
#[IsGranted('ROLE_ADMIN')]
final class CommissionFileController extends AbstractController
{
    public function __construct(
        private readonly CommissionFileStorage $storage,
    ) {
    }

    #[Route(path: '/admin/commission-file/{id}', name: 'admin_commission_file', methods: ['GET'])]
    public function download(CommissionEnquiryFile $file): BinaryFileResponse
    {
        $path = $this->storage->path($file);
        if (! is_file($path)) {
            throw $this->createNotFoundException('This file is no longer stored.');
        }

        $response = new BinaryFileResponse($path);
        $response->headers->set('Content-Type', 'application/octet-stream');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $fallback = preg_replace('/[^\x20-\x7e]|[\/\\\\%"]/', '_', $file->getOriginalName()) ?? 'file';
        $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, $file->getOriginalName(), $fallback);

        return $response;
    }
}

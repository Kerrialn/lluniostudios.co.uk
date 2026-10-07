<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\CommissionEnquiryFile;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Keeps the files attached to Bespoke Commissions enquiries privately:
 * outside the public folder, under random names, downloaded only from admin
 * (CommissionFileController). On the live server this folder must persist
 * between deploys and be backed up.
 */
final class CommissionFileStorage
{
    private readonly Filesystem $filesystem;

    public function __construct(
        #[Autowire('%kernel.project_dir%/var/commission-uploads')]
        private readonly string $directory,
    ) {
        $this->filesystem = new Filesystem();
    }

    /**
     * Move an uploaded file into the private folder and describe it for the
     * enquiry. The customer's file name is kept only for the download.
     */
    public function store(UploadedFile $upload): CommissionEnquiryFile
    {
        $extension = strtolower(pathinfo($upload->getClientOriginalName(), \PATHINFO_EXTENSION));
        $storedName = bin2hex(random_bytes(16)) . ($extension === '' ? '' : '.' . $extension);
        $size = (int) $upload->getSize();
        $this->filesystem->mkdir($this->directory, 0o700);
        $upload->move($this->directory, $storedName);

        return new CommissionEnquiryFile(
            originalName: mb_substr(basename(str_replace('\\', '/', $upload->getClientOriginalName())), 0, 255),
            storedName: $storedName,
            size: $size,
        );
    }

    public function path(CommissionEnquiryFile $file): string
    {
        return $this->directory . '/' . basename($file->getStoredName());
    }

    public function remove(CommissionEnquiryFile $file): void
    {
        $this->filesystem->remove($this->path($file));
    }
}

<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\CommissionEnquiryFile;
use App\Entity\DesignRequestFile;
use App\Entity\StoredFile;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Keeps the files attached to Bespoke Commissions enquiries, and the
 * reference photos sent with requests (10 October 2026), privately: outside
 * the public folder, under random names, downloaded only from admin
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
        return new CommissionEnquiryFile(...$this->move($upload));
    }

    /**
     * The same for a request's reference photo.
     */
    public function storeForRequest(UploadedFile $upload): DesignRequestFile
    {
        return new DesignRequestFile(...$this->move($upload));
    }

    public function path(StoredFile $file): string
    {
        return $this->directory . '/' . basename($file->getStoredName());
    }

    public function remove(StoredFile $file): void
    {
        $this->filesystem->remove($this->path($file));
    }

    /**
     * @return array{originalName: string, storedName: string, size: int}
     */
    private function move(UploadedFile $upload): array
    {
        $extension = strtolower(pathinfo($upload->getClientOriginalName(), \PATHINFO_EXTENSION));
        $storedName = bin2hex(random_bytes(16)) . ($extension === '' ? '' : '.' . $extension);
        $size = (int) $upload->getSize();
        $this->filesystem->mkdir($this->directory, 0o700);
        $upload->move($this->directory, $storedName);

        return [
            'originalName' => mb_substr(basename(str_replace('\\', '/', $upload->getClientOriginalName())), 0, 255),
            'storedName' => $storedName,
            'size' => $size,
        ];
    }
}

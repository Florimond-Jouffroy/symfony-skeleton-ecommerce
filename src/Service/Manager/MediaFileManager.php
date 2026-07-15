<?php

declare(strict_types=1);

namespace App\Service\Manager;

use App\Entity\MediaFile;
use App\Entity\User;
use App\Repository\MediaFileRepository;
use Doctrine\ORM\EntityManagerInterface;
use Florimond\LogBundle\Service\Manager\ApplicationLogManager;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class MediaFileManager
{
    private const ALLOWED_MIME_TYPES = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    private const MAX_SIZE = 5 * 1024 * 1024; // 5 Mo

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ApplicationLogManager $logManager,
        private readonly MediaFileRepository $mediaFileRepository,
    ) {
    }

    /**
     * @return array{media: MediaFile, isDuplicate: bool}|null null si validation échoue
     */
    public function upload(UploadedFile $file, User $uploadedBy, string $uploadDir): ?array
    {
        $realPath = $file->getRealPath();
        if (false === $realPath) {
            return null;
        }

        $mimeType = (string) $file->getMimeType();
        $size = (int) $file->getSize();

        if (!in_array($mimeType, self::ALLOWED_MIME_TYPES, true)) {
            return null;
        }

        if ($size > self::MAX_SIZE) {
            return null;
        }

        $hash = (string) md5_file($realPath);

        $existing = $this->mediaFileRepository->findByHash($hash);
        if (null !== $existing) {
            return ['media' => $existing, 'isDuplicate' => true];
        }

        $extension = $file->guessExtension() ?? 'jpg';
        $filename = bin2hex(random_bytes(16)).'.'.$extension;

        $file->move($uploadDir, $filename);

        $mediaFile = new MediaFile();
        $mediaFile->setFilename($filename);
        $mediaFile->setOriginalName($file->getClientOriginalName());
        $mediaFile->setMimeType($mimeType);
        $mediaFile->setSize($size);
        $mediaFile->setHash($hash);
        $mediaFile->setUploadedBy($uploadedBy);

        if (!$this->insert($mediaFile)) {
            @unlink($uploadDir.'/'.$filename);

            return null;
        }

        return ['media' => $mediaFile, 'isDuplicate' => false];
    }

    public function delete(MediaFile $mediaFile, string $uploadDir, bool $flush = true): bool
    {
        $path = $uploadDir.'/'.$mediaFile->getFilename();

        $this->em->remove($mediaFile);

        if ($flush) {
            try {
                $this->em->flush();
            } catch (\Throwable $e) {
                $this->logManager->reportException($e);

                return false;
            }
        }

        if (file_exists($path)) {
            @unlink($path);
        }

        return true;
    }

    public function insert(MediaFile $mediaFile, bool $flush = true): bool
    {
        $this->em->persist($mediaFile);

        if ($flush) {
            try {
                $this->em->flush();
            } catch (\Throwable $e) {
                $this->logManager->reportException($e);

                return false;
            }
        }

        return true;
    }
}

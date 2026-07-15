<?php

declare(strict_types=1);

namespace App\Controller\Api\Admin;

use App\Entity\MediaFile;
use App\Repository\MediaFileRepository;
use App\Security\Voter\MediaVoter;
use App\Service\Manager\MediaFileManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/admin/medias')]
class MediaController extends AbstractController
{
    public function __construct(
        private readonly MediaFileManager $mediaFileManager,
    ) {
    }

    #[Route('', name: 'api_admin_media_list', methods: ['GET'])]
    public function list(Request $request, MediaFileRepository $mediaFileRepository): JsonResponse
    {
        $this->denyAccessUnlessGranted(MediaVoter::VIEW);

        $page = max(1, $request->query->getInt('page', 1));
        $pageSize = min(100, max(1, $request->query->getInt('pageSize', 24)));
        $query = $request->query->getString('q');

        $result = $mediaFileRepository->searchPaginated('' !== $query ? $query : null, $page, $pageSize);

        return $this->json([
            'items' => array_map($this->serialize(...), $result['items']),
            'total' => $result['total'],
            'page' => $page,
            'pageSize' => $pageSize,
        ]);
    }

    #[Route('', name: 'api_admin_media_upload', methods: ['POST'])]
    public function upload(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(MediaVoter::UPLOAD);

        $file = $request->files->get('file');

        if (!$file instanceof UploadedFile) {
            return $this->json(['message' => 'Aucun fichier reçu.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        /** @var \App\Entity\User $user */
        $user = $this->getUser();
        $uploadDir = $this->getParameter('kernel.project_dir').'/public/'.MediaFile::UPLOAD_SUBDIR;

        $result = $this->mediaFileManager->upload($file, $user, $uploadDir);

        if (null === $result) {
            return $this->json(
                ['message' => 'Format non supporté ou fichier trop lourd (max 5 Mo, jpg/png/gif/webp).'],
                Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        }

        $data = $this->serialize($result['media']);
        $data['isDuplicate'] = $result['isDuplicate'];

        return $this->json($data, $result['isDuplicate'] ? Response::HTTP_OK : Response::HTTP_CREATED);
    }

    #[Route('/{id}', name: 'api_admin_media_delete', methods: ['DELETE'])]
    public function delete(MediaFile $mediaFile): JsonResponse
    {
        $this->denyAccessUnlessGranted(MediaVoter::DELETE, $mediaFile);

        $uploadDir = $this->getParameter('kernel.project_dir').'/public/'.MediaFile::UPLOAD_SUBDIR;

        if (!$this->mediaFileManager->delete($mediaFile, $uploadDir)) {
            return $this->json(['message' => 'Une erreur est survenue.'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return $this->json(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * @return array{id: int|null, url: string, originalName: string, mimeType: string, size: int, uploadedByEmail: string|null, createdAt: string}
     */
    private function serialize(MediaFile $mediaFile): array
    {
        return [
            'id' => $mediaFile->getId(),
            'url' => $mediaFile->getUrl(),
            'originalName' => $mediaFile->getOriginalName(),
            'mimeType' => $mediaFile->getMimeType(),
            'size' => $mediaFile->getSize(),
            'uploadedByEmail' => $mediaFile->getUploadedBy()->getEmail(),
            'createdAt' => $mediaFile->getCreatedAt()->format(\DateTimeInterface::ATOM),
        ];
    }
}

<?php

declare(strict_types=1);

namespace App\Controller\Api\Admin;

use App\Dto\Admin\StaticPageDto;
use App\Entity\StaticPage;
use App\Repository\StaticPageRepository;
use App\Security\Voter\StaticPageVoter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\String\Slugger\AsciiSlugger;

#[Route('/api/admin/pages')]
class StaticPageController extends AbstractController
{
    private function serialize(StaticPage $p): array
    {
        return [
            'id'        => $p->getId(),
            'title'     => $p->getTitle(),
            'slug'      => $p->getSlug(),
            'isActive'  => $p->isActive(),
            'updatedAt' => $p->getUpdatedAt()->format('Y-m-d H:i'),
        ];
    }

    private function serializeFull(StaticPage $p): array
    {
        return [
            ...$this->serialize($p),
            'content' => $p->getContent(),
        ];
    }

    private function generateSlug(string $title): string
    {
        return (new AsciiSlugger('fr'))->slug($title)->lower()->toString();
    }

    #[Route('', name: 'api_admin_pages_list', methods: ['GET'])]
    public function list(StaticPageRepository $repo): JsonResponse
    {
        $this->denyAccessUnlessGranted(StaticPageVoter::VIEW);

        return $this->json(array_map($this->serialize(...), $repo->findAllOrdered()));
    }

    #[Route('/{id}', name: 'api_admin_pages_get', methods: ['GET'])]
    public function get(int $id, StaticPageRepository $repo): JsonResponse
    {
        $this->denyAccessUnlessGranted(StaticPageVoter::VIEW);

        $page = $repo->find($id);
        if (!$page) {
            return $this->json(['message' => 'Page introuvable.'], Response::HTTP_NOT_FOUND);
        }

        return $this->json($this->serializeFull($page));
    }

    #[Route('', name: 'api_admin_pages_create', methods: ['POST'])]
    public function create(#[MapRequestPayload] StaticPageDto $dto, StaticPageRepository $repo, EntityManagerInterface $em): JsonResponse
    {
        $this->denyAccessUnlessGranted(StaticPageVoter::CREATE);

        $title = trim($dto->title);
        $slug  = '' !== trim($dto->slug) ? trim($dto->slug) : $this->generateSlug($title);

        if ($repo->findBySlug($slug)) {
            return $this->json(['message' => 'Ce slug est déjà utilisé.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $page = new StaticPage();
        $page->setTitle($title);
        $page->setSlug($slug);
        $page->setContent($dto->content);
        $page->setIsActive($dto->isActive);

        $em->persist($page);
        $em->flush();

        return $this->json($this->serializeFull($page), Response::HTTP_CREATED);
    }

    #[Route('/{id}', name: 'api_admin_pages_update', methods: ['PATCH'])]
    public function update(int $id, #[MapRequestPayload] StaticPageDto $dto, StaticPageRepository $repo, EntityManagerInterface $em): JsonResponse
    {
        $this->denyAccessUnlessGranted(StaticPageVoter::EDIT);

        $page = $repo->find($id);
        if (!$page) {
            return $this->json(['message' => 'Page introuvable.'], Response::HTTP_NOT_FOUND);
        }

        $slug = trim($dto->slug);
        if ('' === $slug) {
            return $this->json(['message' => 'Le slug ne peut pas être vide.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $existing = $repo->findBySlug($slug);
        if ($existing && $existing->getId() !== $page->getId()) {
            return $this->json(['message' => 'Ce slug est déjà utilisé.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $page->setTitle(trim($dto->title));
        $page->setSlug($slug);
        $page->setContent($dto->content);
        $page->setIsActive($dto->isActive);

        $em->flush();

        return $this->json($this->serializeFull($page));
    }

    #[Route('/{id}', name: 'api_admin_pages_delete', methods: ['DELETE'])]
    public function delete(int $id, StaticPageRepository $repo, EntityManagerInterface $em): JsonResponse
    {
        $this->denyAccessUnlessGranted(StaticPageVoter::DELETE);

        $page = $repo->find($id);
        if (!$page) {
            return $this->json(['message' => 'Page introuvable.'], Response::HTTP_NOT_FOUND);
        }

        $em->remove($page);
        $em->flush();

        return $this->json(null, Response::HTTP_NO_CONTENT);
    }
}

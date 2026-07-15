<?php

declare(strict_types=1);

namespace App\Controller\Api\Admin;

use App\Entity\StaticPage;
use App\Repository\StaticPageRepository;
use App\Security\Voter\StaticPageVoter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
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
    public function create(Request $request, StaticPageRepository $repo, EntityManagerInterface $em): JsonResponse
    {
        $this->denyAccessUnlessGranted(StaticPageVoter::CREATE);

        $data  = $request->toArray();
        $title = trim((string) ($data['title'] ?? ''));

        if ('' === $title) {
            return $this->json(['message' => 'Le titre est requis.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $slug = '' !== trim((string) ($data['slug'] ?? ''))
            ? trim((string) $data['slug'])
            : $this->generateSlug($title);

        if ($repo->findBySlug($slug)) {
            return $this->json(['message' => 'Ce slug est déjà utilisé.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $page = new StaticPage();
        $page->setTitle($title);
        $page->setSlug($slug);
        $page->setContent(is_array($data['content'] ?? null) ? $data['content'] : []);
        $page->setIsActive((bool) ($data['isActive'] ?? true));

        $em->persist($page);
        $em->flush();

        return $this->json($this->serializeFull($page), Response::HTTP_CREATED);
    }

    #[Route('/{id}', name: 'api_admin_pages_update', methods: ['PATCH'])]
    public function update(int $id, Request $request, StaticPageRepository $repo, EntityManagerInterface $em): JsonResponse
    {
        $this->denyAccessUnlessGranted(StaticPageVoter::EDIT);

        $page = $repo->find($id);
        if (!$page) {
            return $this->json(['message' => 'Page introuvable.'], Response::HTTP_NOT_FOUND);
        }

        $data = $request->toArray();

        if (array_key_exists('title', $data)) {
            $title = trim((string) $data['title']);
            if ('' === $title) {
                return $this->json(['message' => 'Le titre ne peut pas être vide.'], Response::HTTP_UNPROCESSABLE_ENTITY);
            }
            $page->setTitle($title);
        }

        if (array_key_exists('slug', $data)) {
            $slug = trim((string) $data['slug']);
            if ('' === $slug) {
                return $this->json(['message' => 'Le slug ne peut pas être vide.'], Response::HTTP_UNPROCESSABLE_ENTITY);
            }
            $existing = $repo->findBySlug($slug);
            if ($existing && $existing->getId() !== $page->getId()) {
                return $this->json(['message' => 'Ce slug est déjà utilisé.'], Response::HTTP_UNPROCESSABLE_ENTITY);
            }
            $page->setSlug($slug);
        }

        if (array_key_exists('content', $data) && is_array($data['content'])) {
            $page->setContent($data['content']);
        }

        if (array_key_exists('isActive', $data)) {
            $page->setIsActive((bool) $data['isActive']);
        }

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

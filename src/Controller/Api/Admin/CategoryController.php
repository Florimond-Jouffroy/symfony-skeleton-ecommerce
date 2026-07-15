<?php

declare(strict_types=1);

namespace App\Controller\Api\Admin;

use App\Entity\Category;
use App\Repository\CategoryRepository;
use App\Security\Voter\CategoryVoter;
use App\Service\Manager\CategoryManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/admin/categories')]
class CategoryController extends AbstractController
{
    public function __construct(
        private readonly CategoryManager $categoryManager,
    ) {
    }

    #[Route('', name: 'api_admin_categories_list', methods: ['GET'])]
    public function list(CategoryRepository $categoryRepository): JsonResponse
    {
        $this->denyAccessUnlessGranted(CategoryVoter::VIEW);

        $categories = $categoryRepository->findAllOrderedByName();

        return $this->json(array_map($this->serialize(...), $categories));
    }

    #[Route('', name: 'api_admin_categories_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(CategoryVoter::CREATE);

        /** @var array{name?: mixed} $payload */
        $payload = $request->toArray();
        $name    = is_string($payload['name'] ?? null) ? trim((string) $payload['name']) : '';

        if ('' === $name) {
            return $this->json(['message' => 'Le nom est obligatoire.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $category = $this->categoryManager->create($name);
        if (null === $category) {
            return $this->json(['message' => 'Une erreur est survenue.'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return $this->json($this->serialize($category), Response::HTTP_CREATED);
    }

    #[Route('/{id}', name: 'api_admin_categories_delete', methods: ['DELETE'])]
    public function delete(Category $category): JsonResponse
    {
        $this->denyAccessUnlessGranted(CategoryVoter::DELETE);

        if (!$this->categoryManager->delete($category)) {
            return $this->json(['message' => 'Une erreur est survenue.'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return $this->json(null, Response::HTTP_NO_CONTENT);
    }

    /** @return array{id: int|null, name: string, slug: string, articleCount: int} */
    private function serialize(Category $category): array
    {
        return [
            'id'           => $category->getId(),
            'name'         => $category->getName(),
            'slug'         => $category->getSlug(),
            'articleCount' => $category->getArticles()->count(),
        ];
    }
}

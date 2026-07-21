<?php

declare(strict_types=1);

namespace App\Controller\Api\Admin;

use App\Dto\Admin\ProductCategoryDto;
use App\Entity\ProductCategory;
use App\Repository\ProductCategoryRepository;
use App\Security\Voter\ProductCategoryVoter;
use App\Service\Manager\ProductCategoryManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

/**
 * API REST pour la gestion des catégories de produits en back-office.
 *
 * Toutes les routes sont protégées par ProductCategoryVoter (VIEW, CREATE, EDIT, DELETE).
 * Les droits sont configurés dans config/permissions.yaml.
 */
#[Route('/api/admin/categories-produits')]
class ProductCategoryController extends AbstractController
{
    public function __construct(private readonly ProductCategoryManager $manager) {}

    #[Route('', name: 'api_admin_product_categories_list', methods: ['GET'])]
    public function list(ProductCategoryRepository $repository): JsonResponse
    {
        $this->denyAccessUnlessGranted(ProductCategoryVoter::VIEW);

        return $this->json(array_map($this->serialize(...), $repository->findAllOrdered()));
    }

    #[Route('', name: 'api_admin_product_categories_create', methods: ['POST'])]
    public function create(#[MapRequestPayload] ProductCategoryDto $dto): JsonResponse
    {
        $this->denyAccessUnlessGranted(ProductCategoryVoter::CREATE);

        $category = $this->manager->create(trim($dto->name), $dto->taxRate);
        if (null === $category) {
            return $this->json(['message' => 'Une erreur est survenue.'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return $this->json($this->serialize($category), Response::HTTP_CREATED);
    }

    #[Route('/{id}', name: 'api_admin_product_categories_update', methods: ['PUT'])]
    public function update(ProductCategory $category, #[MapRequestPayload] ProductCategoryDto $dto): JsonResponse
    {
        $this->denyAccessUnlessGranted(ProductCategoryVoter::EDIT);

        if (!$this->manager->update($category, trim($dto->name), $dto->taxRate)) {
            return $this->json(['message' => 'Une erreur est survenue.'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return $this->json($this->serialize($category));
    }

    #[Route('/{id}', name: 'api_admin_product_categories_delete', methods: ['DELETE'])]
    public function delete(ProductCategory $category): JsonResponse
    {
        $this->denyAccessUnlessGranted(ProductCategoryVoter::DELETE);

        if (!$this->manager->delete($category)) {
            return $this->json(['message' => 'Une erreur est survenue.'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return $this->json(null, Response::HTTP_NO_CONTENT);
    }

    /** @return array<string, mixed> */
    private function serialize(ProductCategory $category): array
    {
        return [
            'id'           => $category->getId(),
            'name'         => $category->getName(),
            'slug'         => $category->getSlug(),
            'position'     => $category->getPosition(),
            'isActive'     => $category->isActive(),
            'taxRate'      => $category->getTaxRate(),
            'productCount' => $category->getProducts()->count(),
        ];
    }
}

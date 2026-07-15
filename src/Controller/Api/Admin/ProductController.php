<?php

declare(strict_types=1);

namespace App\Controller\Api\Admin;

use App\Entity\Product;
use App\Repository\ProductCategoryRepository;
use App\Repository\ProductRepository;
use App\Security\Voter\ProductVoter;
use App\Service\ActivityLogger;
use App\Service\Manager\ProductManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * API REST pour la gestion des produits en back-office.
 *
 * Toutes les routes sont protégées par ProductVoter (VIEW, CREATE, EDIT, DELETE, PUBLISH).
 * Les droits sont configurés dans config/permissions.yaml.
 *
 * Conventions de sérialisation :
 * - serializeList() : données compactes pour le tableau (cover, stock, statut)
 * - serializeFull() : données complètes pour l'éditeur produit (images, variantes, description)
 */
#[Route('/api/admin/produits')]
class ProductController extends AbstractController
{
    public function __construct(
        private readonly ProductManager $manager,
        private readonly ActivityLogger $activityLogger,
    ) {
    }

    #[Route('', name: 'api_admin_products_list', methods: ['GET'])]
    public function list(Request $request, ProductRepository $repository): JsonResponse
    {
        $this->denyAccessUnlessGranted(ProductVoter::VIEW);

        $page       = max(1, $request->query->getInt('page', 1));
        $pageSize   = min(100, max(1, $request->query->getInt('pageSize', 20)));
        $query      = $request->query->getString('q');
        $status     = $request->query->getString('status') ?: null;
        $categoryId = $request->query->getInt('categoryId') ?: null;

        $result = $repository->searchPaginated('' !== $query ? $query : null, $page, $pageSize, $status, $categoryId);

        return $this->json([
            'items'    => array_map($this->serializeList(...), $result['items']),
            'total'    => $result['total'],
            'page'     => $page,
            'pageSize' => $pageSize,
        ]);
    }

    #[Route('', name: 'api_admin_products_create', methods: ['POST'])]
    public function create(Request $request, ProductCategoryRepository $categoryRepository): JsonResponse
    {
        $this->denyAccessUnlessGranted(ProductVoter::CREATE);

        $payload = $request->toArray();
        $name    = trim((string) ($payload['name'] ?? ''));

        if ('' === $name) {
            return $this->json(['message' => 'Le nom est obligatoire.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $categoryIds = array_filter(array_map('intval', (array) ($payload['categoryIds'] ?? [])));
        $categories  = $categoryIds ? $categoryRepository->findBy(['id' => $categoryIds]) : [];

        $product = $this->manager->create($name, $categories);
        if (null === $product) {
            return $this->json(['message' => 'Une erreur est survenue.'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return $this->json($this->serializeFull($product), Response::HTTP_CREATED);
    }

    #[Route('/{id}', name: 'api_admin_products_get', methods: ['GET'])]
    public function get(Product $product): JsonResponse
    {
        $this->denyAccessUnlessGranted(ProductVoter::VIEW);

        return $this->json($this->serializeFull($product));
    }

    #[Route('/{id}', name: 'api_admin_products_update', methods: ['PUT'])]
    public function update(Product $product, Request $request, ProductCategoryRepository $categoryRepository): JsonResponse
    {
        $this->denyAccessUnlessGranted(ProductVoter::EDIT, $product);

        $payload = $request->toArray();
        $name    = trim((string) ($payload['name'] ?? ''));

        if ('' === $name) {
            return $this->json(['message' => 'Le nom est obligatoire.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $categoryIds = array_filter(array_map('intval', (array) ($payload['categoryIds'] ?? [])));
        $categories  = $categoryIds ? $categoryRepository->findBy(['id' => $categoryIds]) : [];

        $price          = max(0, (int) ($payload['price'] ?? 0));
        $compareAtPrice = isset($payload['compareAtPrice']) && $payload['compareAtPrice'] !== null
            ? max(0, (int) $payload['compareAtPrice'])
            : null;
        $description       = is_array($payload['description'] ?? null) ? $payload['description'] : null;
        $stock             = max(0, (int) ($payload['stock'] ?? 0));
        $lowStockThreshold = max(0, (int) ($payload['lowStockThreshold'] ?? 5));
        $hasVariants       = (bool) ($payload['hasVariants'] ?? false);
        $images            = is_array($payload['images'] ?? null) ? $payload['images'] : [];
        $variants          = is_array($payload['variants'] ?? null) ? $payload['variants'] : [];

        if (!$this->manager->update(
            $product, $name, $description, $price, $compareAtPrice,
            $stock, $lowStockThreshold, $hasVariants,
            $categories, $images, $variants,
        )) {
            return $this->json(['message' => 'Une erreur est survenue.'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return $this->json($this->serializeFull($product));
    }

    #[Route('/{id}', name: 'api_admin_products_delete', methods: ['DELETE'])]
    public function delete(Product $product): JsonResponse
    {
        $this->denyAccessUnlessGranted(ProductVoter::DELETE, $product);

        $id   = $product->getId();
        $name = $product->getName();

        if (!$this->manager->delete($product)) {
            return $this->json(['message' => 'Une erreur est survenue.'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        $this->activityLogger->log('product.deleted', 'product', $id, $name);

        return $this->json(null, Response::HTTP_NO_CONTENT);
    }

    #[Route('/{id}/publier', name: 'api_admin_products_publish', methods: ['POST'])]
    public function publish(Product $product): JsonResponse
    {
        $this->denyAccessUnlessGranted(ProductVoter::PUBLISH, $product);

        if ($product->isPublished()) {
            return $this->json(['message' => 'Ce produit est déjà publié.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if (!$this->manager->publish($product)) {
            return $this->json(['message' => 'Une erreur est survenue.'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        $this->activityLogger->log('product.published', 'product', $product->getId(), $product->getName());

        return $this->json($this->serializeList($product));
    }

    #[Route('/{id}/depublier', name: 'api_admin_products_unpublish', methods: ['POST'])]
    public function unpublish(Product $product): JsonResponse
    {
        $this->denyAccessUnlessGranted(ProductVoter::PUBLISH, $product);

        if ($product->isDraft()) {
            return $this->json(['message' => 'Ce produit est déjà en brouillon.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if (!$this->manager->unpublish($product)) {
            return $this->json(['message' => 'Une erreur est survenue.'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        $this->activityLogger->log('product.unpublished', 'product', $product->getId(), $product->getName());

        return $this->json($this->serializeList($product));
    }

    /**
     * Sérialisation légère pour le tableau des produits.
     * Le stock est calculé dynamiquement : somme des variantes actives si hasVariants,
     * sinon valeur directe du produit.
     * La coverImage est la première image (position 0) via une simple boucle break.
     *
     * @return array<string, mixed>
     */
    private function serializeList(Product $product): array
    {
        $categories = array_map(
            static fn ($c) => ['id' => $c->getId(), 'name' => $c->getName(), 'slug' => $c->getSlug()],
            $product->getCategories()->toArray(),
        );

        // La première image dans la collection triée par position est la photo principale.
        $coverUrl = null;
        foreach ($product->getImages() as $img) {
            $coverUrl = $img->getUrl();
            break;
        }

        return [
            'id'           => $product->getId(),
            'name'         => $product->getName(),
            'slug'         => $product->getSlug(),
            'price'        => $product->getPrice(),
            'compareAtPrice' => $product->getCompareAtPrice(),
            'status'       => $product->getStatus(),
            'stock'        => $product->hasVariants()
                ? $product->getVariants()->filter(fn ($v) => $v->isActive())->reduce(fn ($carry, $v) => $carry + $v->getStock(), 0)
                : $product->getStock(),
            'hasVariants'  => $product->hasVariants(),
            'coverImage'   => $coverUrl,
            'categories'   => array_values($categories),
            'createdAt'    => $product->getCreatedAt()->format(\DateTimeInterface::ATOM),
            'updatedAt'    => $product->getUpdatedAt()->format(\DateTimeInterface::ATOM),
        ];
    }

    /** @return array<string, mixed> */
    private function serializeFull(Product $product): array
    {
        $images = array_map(
            static fn ($img) => ['id' => $img->getId(), 'url' => $img->getUrl(), 'alt' => $img->getAlt(), 'position' => $img->getPosition()],
            $product->getImages()->toArray(),
        );

        $variants = array_map(
            static fn ($v) => [
                'id'               => $v->getId(),
                'name'             => $v->getName(),
                'sku'              => $v->getSku(),
                'priceOverride'    => $v->getPriceOverride(),
                'stock'            => $v->getStock(),
                'lowStockThreshold' => $v->getLowStockThreshold(),
                'position'         => $v->getPosition(),
                'attributes'       => $v->getAttributes(),
                'isActive'         => $v->isActive(),
            ],
            $product->getVariants()->toArray(),
        );

        return array_merge($this->serializeList($product), [
            'description' => $product->getDescription(),
            'lowStockThreshold' => $product->getLowStockThreshold(),
            'images'      => array_values($images),
            'variants'    => array_values($variants),
        ]);
    }
}

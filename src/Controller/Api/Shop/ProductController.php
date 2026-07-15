<?php

declare(strict_types=1);

namespace App\Controller\Api\Shop;

use App\Entity\Product;
use App\Repository\ProductCategoryRepository;
use App\Repository\ProductRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/boutique/produits')]
class ProductController extends AbstractController
{
    #[Route('', name: 'api_shop_products_list', methods: ['GET'])]
    public function list(
        Request $request,
        ProductRepository $repository,
        ProductCategoryRepository $categoryRepository,
    ): JsonResponse {
        $page     = max(1, $request->query->getInt('page', 1));
        $pageSize = min(48, max(1, $request->query->getInt('pageSize', 12)));
        $query    = $request->query->getString('q');
        $catSlug  = $request->query->getString('categorie') ?: null;

        $categoryId = null;
        if (null !== $catSlug) {
            $cat        = $categoryRepository->findOneBy(['slug' => $catSlug]);
            $categoryId = $cat?->getId();
        }

        $result = $repository->searchPaginated(
            '' !== $query ? $query : null,
            $page,
            $pageSize,
            Product::STATUS_PUBLISHED,
            $categoryId,
        );

        return $this->json([
            'items'    => array_map($this->serialize(...), $result['items']),
            'total'    => $result['total'],
            'page'     => $page,
            'pageSize' => $pageSize,
        ]);
    }

    #[Route('/{slug}', name: 'api_shop_products_get', methods: ['GET'])]
    public function get(string $slug, ProductRepository $repository): JsonResponse
    {
        $product = $repository->findOneBy(['slug' => $slug, 'status' => Product::STATUS_PUBLISHED]);

        if (null === $product) {
            return $this->json(['message' => 'Produit introuvable.'], Response::HTTP_NOT_FOUND);
        }

        return $this->json($this->serializeFull($product));
    }

    private function serialize(Product $product): array
    {
        $coverUrl = null;
        foreach ($product->getImages() as $img) {
            $coverUrl = $img->getUrl();
            break;
        }

        $stock = $product->hasVariants()
            ? $product->getVariants()->filter(fn ($v) => $v->isActive())->reduce(fn ($carry, $v) => $carry + $v->getStock(), 0)
            : $product->getStock();

        return [
            'id'             => $product->getId(),
            'name'           => $product->getName(),
            'slug'           => $product->getSlug(),
            'price'          => $product->getPrice(),
            'compareAtPrice' => $product->getCompareAtPrice(),
            'stock'          => $stock,
            'hasVariants'    => $product->hasVariants(),
            'coverImage'     => $coverUrl,
            'categories'     => array_values(array_map(
                static fn ($c) => ['id' => $c->getId(), 'name' => $c->getName(), 'slug' => $c->getSlug()],
                $product->getCategories()->toArray(),
            )),
        ];
    }

    private function serializeFull(Product $product): array
    {
        $images = array_values(array_map(
            static fn ($img) => ['id' => $img->getId(), 'url' => $img->getUrl(), 'alt' => $img->getAlt()],
            $product->getImages()->toArray(),
        ));

        $variants = array_values(array_map(
            static fn ($v) => [
                'id'         => $v->getId(),
                'name'       => $v->getName(),
                'price'      => $v->getEffectivePrice(),
                'stock'      => $v->getStock(),
                'attributes' => $v->getAttributes(),
                'isActive'   => $v->isActive(),
            ],
            $product->getVariants()->filter(fn ($v) => $v->isActive())->toArray(),
        ));

        return array_merge($this->serialize($product), [
            'description' => $product->getDescription(),
            'images'      => $images,
            'variants'    => $variants,
        ]);
    }
}

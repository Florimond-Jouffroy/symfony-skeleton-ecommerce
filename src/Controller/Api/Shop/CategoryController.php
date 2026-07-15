<?php

declare(strict_types=1);

namespace App\Controller\Api\Shop;

use App\Repository\ProductCategoryRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/boutique/categories')]
class CategoryController extends AbstractController
{
    #[Route('', name: 'api_shop_categories_list', methods: ['GET'])]
    public function list(ProductCategoryRepository $repository): JsonResponse
    {
        $categories = $repository->findBy(['isActive' => true], ['position' => 'ASC']);

        return $this->json(array_map(
            static fn ($c) => ['id' => $c->getId(), 'name' => $c->getName(), 'slug' => $c->getSlug()],
            $categories,
        ));
    }
}

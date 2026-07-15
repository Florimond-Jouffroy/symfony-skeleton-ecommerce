<?php

declare(strict_types=1);

namespace App\Controller\Api\Blog;

use App\Repository\CategoryRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/blog/categories')]
class CategoryController extends AbstractController
{
    #[Route('', name: 'api_blog_categories_list', methods: ['GET'])]
    public function list(CategoryRepository $repository): JsonResponse
    {
        $categories = $repository->findBy([], ['name' => 'ASC']);

        return $this->json(array_map(
            static fn ($c) => ['id' => $c->getId(), 'name' => $c->getName(), 'slug' => $c->getSlug()],
            $categories,
        ));
    }
}

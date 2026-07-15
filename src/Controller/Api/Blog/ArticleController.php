<?php

declare(strict_types=1);

namespace App\Controller\Api\Blog;

use App\Entity\Article;
use App\Repository\ArticleRepository;
use App\Repository\CategoryRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/blog/articles')]
class ArticleController extends AbstractController
{
    #[Route('', name: 'api_blog_articles_list', methods: ['GET'])]
    public function list(
        Request $request,
        ArticleRepository $repository,
        CategoryRepository $categoryRepository,
    ): JsonResponse {
        $page     = max(1, $request->query->getInt('page', 1));
        $pageSize = min(24, max(1, $request->query->getInt('pageSize', 9)));
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
            $categoryId,
            Article::STATUS_PUBLISHED,
        );

        return $this->json([
            'items'    => array_map($this->serialize(...), $result['items']),
            'total'    => $result['total'],
            'page'     => $page,
            'pageSize' => $pageSize,
        ]);
    }

    #[Route('/{slug}', name: 'api_blog_articles_get', methods: ['GET'])]
    public function get(string $slug, ArticleRepository $repository): JsonResponse
    {
        $article = $repository->findOneBy(['slug' => $slug, 'status' => Article::STATUS_PUBLISHED]);

        if (null === $article) {
            return $this->json(['message' => 'Article introuvable.'], Response::HTTP_NOT_FOUND);
        }

        return $this->json($this->serializeFull($article));
    }

    private function serialize(Article $article): array
    {
        return [
            'id'          => $article->getId(),
            'title'       => $article->getTitle(),
            'slug'        => $article->getSlug(),
            'excerpt'     => $article->getExcerpt(),
            'coverImage'  => $article->getCoverImage(),
            'categories'  => array_values(array_map(
                static fn ($c) => ['id' => $c->getId(), 'name' => $c->getName(), 'slug' => $c->getSlug()],
                $article->getCategories()->toArray(),
            )),
            'authorEmail' => $article->getAuthor()->getEmail(),
            'publishedAt' => $article->getPublishedAt()?->format(\DateTimeInterface::ATOM),
        ];
    }

    private function serializeFull(Article $article): array
    {
        return array_merge($this->serialize($article), [
            'content' => $article->getContent(),
        ]);
    }
}

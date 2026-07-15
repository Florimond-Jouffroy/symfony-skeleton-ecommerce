<?php

declare(strict_types=1);

namespace App\Controller\Api\Admin;

use App\Entity\Article;
use App\Repository\ArticleRepository;
use App\Repository\CategoryRepository;
use App\Security\Voter\ArticleVoter;
use App\Service\Manager\ArticleManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/admin/articles')]
class ArticleController extends AbstractController
{
    public function __construct(
        private readonly ArticleManager $articleManager,
    ) {
    }

    #[Route('', name: 'api_admin_articles_list', methods: ['GET'])]
    public function list(Request $request, ArticleRepository $articleRepository): JsonResponse
    {
        $this->denyAccessUnlessGranted(ArticleVoter::VIEW);

        $page = max(1, $request->query->getInt('page', 1));
        $pageSize = min(100, max(1, $request->query->getInt('pageSize', 20)));
        $query      = $request->query->getString('q');
        $categoryId = $request->query->getInt('categoryId') ?: null;

        $result = $articleRepository->searchPaginated('' !== $query ? $query : null, $page, $pageSize, $categoryId);

        return $this->json([
            'items' => array_map($this->serializeArticle(...), $result['items']),
            'total' => $result['total'],
            'page' => $page,
            'pageSize' => $pageSize,
        ]);
    }

    #[Route('', name: 'api_admin_articles_create', methods: ['POST'])]
    public function create(Request $request, CategoryRepository $categoryRepository): JsonResponse
    {
        $this->denyAccessUnlessGranted(ArticleVoter::CREATE);

        /** @var array{title?: mixed, content?: mixed, excerpt?: mixed, coverImage?: mixed} $payload */
        $payload = $request->toArray();

        $title = is_string($payload['title'] ?? null) ? trim((string) $payload['title']) : '';
        if ('' === $title) {
            return $this->json(['message' => 'Le titre est obligatoire.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $content = is_array($payload['content'] ?? null) ? (array) $payload['content'] : [];
        $excerpt = is_string($payload['excerpt'] ?? null) && '' !== trim((string) $payload['excerpt'])
            ? trim((string) $payload['excerpt'])
            : null;
        $coverImage  = is_string($payload['coverImage'] ?? null) && '' !== trim((string) $payload['coverImage'])
            ? trim((string) $payload['coverImage'])
            : null;
        $categoryIds = array_filter(array_map('intval', (array) ($payload['categoryIds'] ?? [])));

        /** @var \App\Entity\User $author */
        $author = $this->getUser();

        $categories = $categoryIds ? $categoryRepository->findBy(['id' => $categoryIds]) : [];
        $article    = $this->articleManager->create($title, $content, $author, $excerpt, $coverImage, $categories);
        if (null === $article) {
            return $this->json(['message' => 'Une erreur est survenue.'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return $this->json($this->serializeArticle($article), Response::HTTP_CREATED);
    }

    #[Route('/{id}', name: 'api_admin_articles_get', methods: ['GET'])]
    public function get(Article $article): JsonResponse
    {
        $this->denyAccessUnlessGranted(ArticleVoter::VIEW);

        return $this->json($this->serializeArticleFull($article));
    }

    #[Route('/{id}', name: 'api_admin_articles_update', methods: ['PUT'])]
    public function update(Article $article, Request $request, CategoryRepository $categoryRepository): JsonResponse
    {
        $this->denyAccessUnlessGranted(ArticleVoter::EDIT, $article);

        /** @var array{title?: mixed, content?: mixed, excerpt?: mixed, coverImage?: mixed} $payload */
        $payload = $request->toArray();

        $title = is_string($payload['title'] ?? null) ? trim((string) $payload['title']) : '';
        if ('' === $title) {
            return $this->json(['message' => 'Le titre est obligatoire.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $content = is_array($payload['content'] ?? null) ? (array) $payload['content'] : [];
        $excerpt = is_string($payload['excerpt'] ?? null) && '' !== trim((string) $payload['excerpt'])
            ? trim((string) $payload['excerpt'])
            : null;
        $coverImage  = is_string($payload['coverImage'] ?? null) && '' !== trim((string) $payload['coverImage'])
            ? trim((string) $payload['coverImage'])
            : null;
        $categoryIds = array_filter(array_map('intval', (array) ($payload['categoryIds'] ?? [])));
        $categories  = $categoryIds ? $categoryRepository->findBy(['id' => $categoryIds]) : [];

        if (!$this->articleManager->update($article, $title, $content, $excerpt, $coverImage, $categories)) {
            return $this->json(['message' => 'Une erreur est survenue.'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return $this->json($this->serializeArticleFull($article));
    }

    #[Route('/{id}', name: 'api_admin_articles_delete', methods: ['DELETE'])]
    public function delete(Article $article): JsonResponse
    {
        $this->denyAccessUnlessGranted(ArticleVoter::DELETE, $article);

        if (!$this->articleManager->delete($article)) {
            return $this->json(['message' => 'Une erreur est survenue.'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return $this->json(null, Response::HTTP_NO_CONTENT);
    }

    #[Route('/{id}/publier', name: 'api_admin_articles_publish', methods: ['POST'])]
    public function publish(Article $article): JsonResponse
    {
        $this->denyAccessUnlessGranted(ArticleVoter::PUBLISH, $article);

        if ($article->isPublished()) {
            return $this->json(['message' => 'Cet article est déjà publié.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if (!$this->articleManager->publish($article)) {
            return $this->json(['message' => 'Une erreur est survenue.'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return $this->json($this->serializeArticle($article));
    }

    #[Route('/{id}/depublier', name: 'api_admin_articles_unpublish', methods: ['POST'])]
    public function unpublish(Article $article): JsonResponse
    {
        $this->denyAccessUnlessGranted(ArticleVoter::PUBLISH, $article);

        if ($article->isDraft()) {
            return $this->json(['message' => 'Cet article est déjà en brouillon.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if (!$this->articleManager->unpublish($article)) {
            return $this->json(['message' => 'Une erreur est survenue.'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return $this->json($this->serializeArticle($article));
    }

    /**
     * @return array{id: int|null, title: string, slug: string, excerpt: string|null, coverImage: string|null, categories: array<array{id: int|null, name: string, slug: string}>, status: string, authorEmail: string|null, createdAt: string, updatedAt: string, publishedAt: string|null}
     */
    private function serializeArticle(Article $article): array
    {
        $categories = array_map(
            static fn ($c) => ['id' => $c->getId(), 'name' => $c->getName(), 'slug' => $c->getSlug()],
            $article->getCategories()->toArray(),
        );

        return [
            'id'          => $article->getId(),
            'title'       => $article->getTitle(),
            'slug'        => $article->getSlug(),
            'excerpt'     => $article->getExcerpt(),
            'coverImage'  => $article->getCoverImage(),
            'categories'  => array_values($categories),
            'status'      => $article->getStatus(),
            'authorEmail' => $article->getAuthor()->getEmail(),
            'createdAt'   => $article->getCreatedAt()->format(\DateTimeInterface::ATOM),
            'updatedAt'   => $article->getUpdatedAt()->format(\DateTimeInterface::ATOM),
            'publishedAt' => $article->getPublishedAt()?->format(\DateTimeInterface::ATOM),
        ];
    }

    /**
     * @return array{id: int|null, title: string, slug: string, excerpt: string|null, coverImage: string|null, content: array<mixed>, status: string, authorEmail: string|null, createdAt: string, updatedAt: string, publishedAt: string|null}
     */
    private function serializeArticleFull(Article $article): array
    {
        return array_merge($this->serializeArticle($article), ['content' => $article->getContent()]);
    }
}

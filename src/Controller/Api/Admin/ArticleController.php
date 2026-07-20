<?php

declare(strict_types=1);

namespace App\Controller\Api\Admin;

use App\Dto\ArticleDto;
use App\Entity\Article;
use App\Repository\ArticleRepository;
use App\Repository\CategoryRepository;
use App\Security\Voter\ArticleVoter;
use App\Service\Manager\ArticleManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/admin/articles')]
class ArticleController extends AbstractController
{
    public function __construct(
        private readonly ArticleManager $articleManager,
    ) {}

    #[Route('', name: 'api_admin_articles_list', methods: ['GET'])]
    public function list(Request $request, ArticleRepository $articleRepository): JsonResponse
    {
        $this->denyAccessUnlessGranted(ArticleVoter::VIEW);

        $page       = max(1, $request->query->getInt('page', 1));
        $pageSize   = min(100, max(1, $request->query->getInt('pageSize', 20)));
        $query      = $request->query->getString('q');
        $categoryId = $request->query->getInt('categoryId') ?: null;

        $result = $articleRepository->searchPaginated('' !== $query ? $query : null, $page, $pageSize, $categoryId);

        return $this->json([
            'items'    => array_map($this->serializeArticle(...), $result['items']),
            'total'    => $result['total'],
            'page'     => $page,
            'pageSize' => $pageSize,
        ]);
    }

    #[Route('', name: 'api_admin_articles_create', methods: ['POST'])]
    public function create(#[MapRequestPayload] ArticleDto $dto, CategoryRepository $categoryRepository): JsonResponse
    {
        $this->denyAccessUnlessGranted(ArticleVoter::CREATE);

        /** @var \App\Entity\User $author */
        $author = $this->getUser();

        $article = $this->articleManager->create(
            trim($dto->title),
            $dto->content,
            $author,
            $this->normalizeText($dto->excerpt),
            $this->normalizeText($dto->coverImage),
            $this->resolveCategories($dto->categoryIds, $categoryRepository),
        );
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
    public function update(Article $article, #[MapRequestPayload] ArticleDto $dto, CategoryRepository $categoryRepository): JsonResponse
    {
        $this->denyAccessUnlessGranted(ArticleVoter::EDIT, $article);

        $updated = $this->articleManager->update(
            $article,
            trim($dto->title),
            $dto->content,
            $this->normalizeText($dto->excerpt),
            $this->normalizeText($dto->coverImage),
            $this->resolveCategories($dto->categoryIds, $categoryRepository),
        );
        if (!$updated) {
            return $this->json(['message' => 'Une erreur est survenue.'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return $this->json($this->serializeArticleFull($article));
    }

    /** Normalise une chaîne optionnelle : trim, et '' → null. */
    private function normalizeText(?string $value): ?string
    {
        $value = null !== $value ? trim($value) : '';

        return '' !== $value ? $value : null;
    }

    /**
     * @param list<int> $categoryIds
     *
     * @return list<\App\Entity\Category>
     */
    private function resolveCategories(array $categoryIds, CategoryRepository $categoryRepository): array
    {
        $ids = array_values(array_filter($categoryIds));

        return $ids ? $categoryRepository->findBy(['id' => $ids]) : [];
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

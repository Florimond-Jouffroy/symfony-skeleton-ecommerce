<?php

declare(strict_types=1);

namespace App\Service\Manager;

use App\Entity\Article;
use App\Entity\User;
use App\Repository\ArticleRepository;
use Doctrine\ORM\EntityManagerInterface;
use Florimond\LogBundle\Service\Manager\ApplicationLogManager;

class ArticleManager
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ApplicationLogManager $logManager,
        private readonly ArticleRepository $articleRepository,
    ) {
    }

    /**
     * @param array<mixed>     $content
     * @param \App\Entity\Category[] $categories
     */
    public function create(string $title, array $content, User $author, ?string $excerpt = null, ?string $coverImage = null, array $categories = []): ?Article
    {
        $article = new Article();
        $article->setTitle($title);
        $article->setSlug($this->generateUniqueSlug($title));
        $article->setContent($content);
        $article->setAuthor($author);
        $article->setExcerpt($excerpt);
        $article->setCoverImage($coverImage);
        $article->syncCategories($categories);

        return $this->insert($article) ? $article : null;
    }

    /**
     * @param array<mixed>     $content
     * @param \App\Entity\Category[] $categories
     */
    public function update(Article $article, string $title, array $content, ?string $excerpt, ?string $coverImage = null, array $categories = []): bool
    {
        if ($article->getTitle() !== $title) {
            $article->setTitle($title);
            $article->setSlug($this->generateUniqueSlug($title, $article->getId()));
        }

        $article->setContent($content);
        $article->setExcerpt($excerpt);
        $article->setCoverImage($coverImage);
        $article->syncCategories($categories);
        $article->setUpdatedAt(new \DateTimeImmutable());

        return $this->save($article);
    }

    public function publish(Article $article): bool
    {
        $article->setStatus(Article::STATUS_PUBLISHED);
        $article->setPublishedAt(new \DateTimeImmutable());
        $article->setUpdatedAt(new \DateTimeImmutable());

        return $this->save($article);
    }

    public function unpublish(Article $article): bool
    {
        $article->setStatus(Article::STATUS_DRAFT);
        $article->setPublishedAt(null);
        $article->setUpdatedAt(new \DateTimeImmutable());

        return $this->save($article);
    }

    public function insert(Article $article, bool $flush = true): bool
    {
        $this->em->persist($article);

        if ($flush) {
            try {
                $this->em->flush();
            } catch (\Throwable $e) {
                $this->logManager->reportException($e);

                return false;
            }
        }

        return true;
    }

    public function save(Article $article, bool $flush = true): bool
    {
        if ($flush) {
            try {
                $this->em->flush();
            } catch (\Throwable $e) {
                $this->logManager->reportException($e);

                return false;
            }
        }

        return true;
    }

    public function delete(Article $article, bool $flush = true): bool
    {
        $this->em->remove($article);

        if ($flush) {
            try {
                $this->em->flush();
            } catch (\Throwable $e) {
                $this->logManager->reportException($e);

                return false;
            }
        }

        return true;
    }

    private function generateUniqueSlug(string $title, ?int $excludeId = null): string
    {
        $base = $this->slugify($title);
        $slug = $base;
        $counter = 2;

        while (true) {
            $existing = $this->articleRepository->findBySlug($slug);

            if (null === $existing || $existing->getId() === $excludeId) {
                break;
            }

            $slug = $base.'-'.$counter;
            ++$counter;
        }

        return $slug;
    }

    private function slugify(string $text): string
    {
        $text = mb_strtolower($text, 'UTF-8');
        $text = (string) iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
        $text = (string) preg_replace('/[^a-z0-9\-]/', '-', $text);
        $text = (string) preg_replace('/-+/', '-', $text);

        return trim($text, '-') ?: 'article';
    }
}

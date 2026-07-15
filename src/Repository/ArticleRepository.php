<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Article;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Article>
 */
class ArticleRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Article::class);
    }

    /**
     * @return array{items: list<Article>, total: int}
     */
    public function searchPaginated(?string $query, int $page, int $pageSize, ?int $categoryId = null, ?string $status = null): array
    {
        $qb = $this->createQueryBuilder('a')
            ->orderBy(null !== $status ? 'a.publishedAt' : 'a.createdAt', 'DESC');

        if (null !== $query && '' !== $query) {
            $qb->andWhere('a.title LIKE :query')
                ->setParameter('query', '%'.$query.'%');
        }

        if (null !== $categoryId) {
            $qb->join('a.categories', 'c')
                ->andWhere('c.id = :catId')
                ->setParameter('catId', $categoryId);
        }

        if (null !== $status) {
            $qb->andWhere('a.status = :status')
                ->setParameter('status', $status);
        }

        $total = (int) (clone $qb)->select('COUNT(a.id)')->getQuery()->getSingleScalarResult();

        /** @var list<Article> $items */
        $items = $qb
            ->setFirstResult(($page - 1) * $pageSize)
            ->setMaxResults($pageSize)
            ->getQuery()
            ->getResult();

        return ['items' => $items, 'total' => $total];
    }

    public function findBySlug(string $slug): ?Article
    {
        return $this->findOneBy(['slug' => $slug]);
    }
}

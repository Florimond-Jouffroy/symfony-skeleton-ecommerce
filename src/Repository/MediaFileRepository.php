<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\MediaFile;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<MediaFile>
 */
class MediaFileRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, MediaFile::class);
    }

    public function findByHash(string $hash): ?MediaFile
    {
        return $this->findOneBy(['hash' => $hash]);
    }

    /**
     * @return array{items: list<MediaFile>, total: int}
     */
    public function searchPaginated(?string $query, int $page, int $pageSize): array
    {
        $qb = $this->createQueryBuilder('m')
            ->orderBy('m.createdAt', 'DESC');

        if (null !== $query && '' !== $query) {
            $qb->andWhere('m.originalName LIKE :query')
                ->setParameter('query', '%'.$query.'%');
        }

        $total = (int) (clone $qb)->select('COUNT(m.id)')->getQuery()->getSingleScalarResult();

        /** @var list<MediaFile> $items */
        $items = $qb
            ->setFirstResult(($page - 1) * $pageSize)
            ->setMaxResults($pageSize)
            ->getQuery()
            ->getResult();

        return ['items' => $items, 'total' => $total];
    }
}

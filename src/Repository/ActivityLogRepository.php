<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\ActivityLog;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class ActivityLogRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ActivityLog::class);
    }

    /**
     * @return array{items: ActivityLog[], total: int}
     */
    public function findPaginated(
        ?string $entityType,
        ?\DateTimeImmutable $from,
        ?\DateTimeImmutable $to,
        int $page,
        int $pageSize,
    ): array {
        $qb = $this->createQueryBuilder('a')->orderBy('a.createdAt', 'DESC');

        if ($entityType !== null) {
            $qb->andWhere('a.entityType = :entityType')->setParameter('entityType', $entityType);
        }
        if ($from !== null) {
            $qb->andWhere('a.createdAt >= :from')->setParameter('from', $from);
        }
        if ($to !== null) {
            $qb->andWhere('a.createdAt <= :to')->setParameter('to', $to);
        }

        $total = (int) (clone $qb)->select('COUNT(a.id)')->getQuery()->getSingleScalarResult();

        $items = $qb->setFirstResult(($page - 1) * $pageSize)
            ->setMaxResults($pageSize)
            ->getQuery()
            ->getResult();

        return ['items' => $items, 'total' => $total];
    }
}

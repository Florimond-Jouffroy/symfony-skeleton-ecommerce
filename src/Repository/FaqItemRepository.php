<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\FaqItem;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class FaqItemRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, FaqItem::class);
    }

    /** @return FaqItem[] */
    public function findAllOrdered(): array
    {
        return $this->createQueryBuilder('f')
            ->orderBy('f.position', 'ASC')
            ->addOrderBy('f.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /** @return FaqItem[] */
    public function findActiveOrdered(): array
    {
        return $this->createQueryBuilder('f')
            ->andWhere('f.isActive = true')
            ->orderBy('f.position', 'ASC')
            ->addOrderBy('f.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function getMaxPosition(): int
    {
        $result = $this->createQueryBuilder('f')
            ->select('MAX(f.position)')
            ->getQuery()
            ->getSingleScalarResult();

        return (int) $result;
    }
}

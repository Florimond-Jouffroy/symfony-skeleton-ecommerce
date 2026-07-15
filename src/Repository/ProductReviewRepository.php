<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Product;
use App\Entity\ProductReview;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class ProductReviewRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ProductReview::class);
    }

    /** @return ProductReview[] */
    public function findApprovedByProduct(int $productId): array
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.product = :pid')
            ->andWhere('r.isApproved = true')
            ->setParameter('pid', $productId)
            ->orderBy('r.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /** @return array{avgRating: float, count: int} */
    public function getProductStats(int $productId): array
    {
        $row = $this->createQueryBuilder('r')
            ->select('AVG(r.rating) as avg, COUNT(r.id) as cnt')
            ->andWhere('r.product = :pid')
            ->andWhere('r.isApproved = true')
            ->setParameter('pid', $productId)
            ->getQuery()
            ->getSingleResult();

        return [
            'avgRating' => $row['avg'] !== null ? round((float) $row['avg'], 1) : null,
            'count'     => (int) $row['cnt'],
        ];
    }

    /** @return ProductReview[] */
    public function findForAdmin(?bool $approved = null): array
    {
        $qb = $this->createQueryBuilder('r')
            ->join('r.product', 'p')
            ->addSelect('p')
            ->orderBy('r.createdAt', 'DESC');

        if (null !== $approved) {
            $qb->andWhere('r.isApproved = :approved')->setParameter('approved', $approved);
        }

        return $qb->getQuery()->getResult();
    }

    /** @return ProductReview[] */
    public function findByUser(User $user): array
    {
        return $this->createQueryBuilder('r')
            ->join('r.product', 'p')
            ->addSelect('p')
            ->andWhere('r.user = :user')
            ->setParameter('user', $user)
            ->orderBy('r.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function hasUserReviewedProduct(User $user, Product $product): bool
    {
        return (int) $this->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->andWhere('r.user = :user')
            ->andWhere('r.product = :product')
            ->setParameter('user', $user)
            ->setParameter('product', $product)
            ->getQuery()
            ->getSingleScalarResult() > 0;
    }

    public function countPendingApproval(): int
    {
        return (int) $this->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->andWhere('r.isApproved = false')
            ->getQuery()
            ->getSingleScalarResult();
    }
}

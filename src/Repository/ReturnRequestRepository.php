<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\ReturnRequest;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * Requêtes Doctrine pour l'entité ReturnRequest.
 *
 * @extends ServiceEntityRepository<ReturnRequest>
 */
class ReturnRequestRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ReturnRequest::class);
    }

    /**
     * @return list<ReturnRequest>
     */
    public function findByCustomer(int $customerId): array
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.customer = :customer')
            ->setParameter('customer', $customerId)
            ->orderBy('r.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return list<ReturnRequest>
     */
    public function findAllOrdered(): array
    {
        return $this->createQueryBuilder('r')
            ->orderBy('r.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}

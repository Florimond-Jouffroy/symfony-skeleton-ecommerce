<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Customer;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * Requêtes Doctrine pour l'entité Customer.
 *
 * @extends ServiceEntityRepository<Customer>
 */
class CustomerRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Customer::class);
    }

    public function findByEmail(string $email): ?Customer
    {
        return $this->findOneBy(['email' => $email]);
    }

    /**
     * @return array{items: list<Customer>, total: int}
     */
    public function searchPaginated(?string $query, int $page, int $pageSize): array
    {
        $qb = $this->createQueryBuilder('c')
            ->orderBy('c.createdAt', 'DESC');

        if (null !== $query && '' !== $query) {
            $qb->andWhere('c.email LIKE :q OR c.firstName LIKE :q OR c.lastName LIKE :q')
                ->setParameter('q', '%'.$query.'%');
        }

        $total = (int) (clone $qb)->select('COUNT(c.id)')->getQuery()->getSingleScalarResult();

        /** @var list<Customer> $items */
        $items = $qb
            ->setFirstResult(($page - 1) * $pageSize)
            ->setMaxResults($pageSize)
            ->getQuery()
            ->getResult();

        return ['items' => $items, 'total' => $total];
    }
}

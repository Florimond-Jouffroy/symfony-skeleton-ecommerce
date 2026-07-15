<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Order;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Connection;
use Doctrine\Persistence\ManagerRegistry;

/**
 * Requêtes Doctrine pour l'entité Order.
 *
 * @extends ServiceEntityRepository<Order>
 */
class OrderRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Order::class);
    }

    public function findByOrderNumber(string $number): ?Order
    {
        return $this->findOneBy(['orderNumber' => $number]);
    }

    /**
     * Retourne le prochain numéro de séquence pour générer un numéro de commande.
     * Utilise COUNT+1, ce qui suffit pour un usage admin à faible concurrence.
     * Note : des suppressions de commandes peuvent créer des "trous" dans la séquence,
     * c'est intentionnel et sans impact fonctionnel.
     */
    public function getNextSequence(): int
    {
        $result = $this->createQueryBuilder('o')
            ->select('COUNT(o.id)')
            ->getQuery()
            ->getSingleScalarResult();

        return (int) $result + 1;
    }

    /**
     * Recherche paginée avec filtres optionnels.
     * La jointure sur customer est systématique car la recherche textuelle
     * porte sur le nom et l'email du client.
     *
     * @return array{items: list<Order>, total: int}
     */
    public function searchPaginated(
        ?string $query,
        int $page,
        int $pageSize,
        ?string $status = null,
        ?int $customerId = null,
    ): array {
        $qb = $this->createQueryBuilder('o')
            ->join('o.customer', 'c')
            ->orderBy('o.createdAt', 'DESC');

        if (null !== $query && '' !== $query) {
            $qb->andWhere('o.orderNumber LIKE :q OR c.email LIKE :q OR c.firstName LIKE :q OR c.lastName LIKE :q')
                ->setParameter('q', '%'.$query.'%');
        }

        if (null !== $status) {
            $qb->andWhere('o.status = :status')->setParameter('status', $status);
        }

        if (null !== $customerId) {
            $qb->andWhere('c.id = :cid')->setParameter('cid', $customerId);
        }

        $total = (int) (clone $qb)->select('COUNT(o.id)')->getQuery()->getSingleScalarResult();

        /** @var list<Order> $items */
        $items = $qb
            ->setFirstResult(($page - 1) * $pageSize)
            ->setMaxResults($pageSize)
            ->getQuery()
            ->getResult();

        return ['items' => $items, 'total' => $total];
    }

    /**
     * Retourne le nombre de commandes par statut.
     *
     * @return array<string, int> statut => nombre de commandes
     */
    public function countByStatus(): array
    {
        $rows = $this->createQueryBuilder('o')
            ->select('o.status, COUNT(o.id) as cnt')
            ->groupBy('o.status')
            ->getQuery()
            ->getArrayResult();

        $result = [];
        foreach ($rows as $row) {
            $result[$row['status']] = (int) $row['cnt'];
        }

        return $result;
    }

    /** Nombre de commandes pour un intervalle donné, hors commandes annulées/remboursées. */
    public function countRevenue(\DateTimeImmutable $from, \DateTimeImmutable $to): int
    {
        $result = $this->createQueryBuilder('o')
            ->select('COUNT(o.id)')
            ->where('o.status NOT IN (:excluded)')
            ->andWhere('o.createdAt >= :from')
            ->andWhere('o.createdAt < :to')
            ->setParameter('excluded', [Order::STATUS_CANCELLED, Order::STATUS_REFUNDED])
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->getQuery()
            ->getSingleScalarResult();

        return (int) ($result ?? 0);
    }

    /** CA total (en centimes) pour un intervalle donné, hors commandes annulées/remboursées. */
    public function sumRevenue(\DateTimeImmutable $from, \DateTimeImmutable $to): int
    {
        $result = $this->createQueryBuilder('o')
            ->select('SUM(o.total)')
            ->where('o.status NOT IN (:excluded)')
            ->andWhere('o.createdAt >= :from')
            ->andWhere('o.createdAt < :to')
            ->setParameter('excluded', [Order::STATUS_CANCELLED, Order::STATUS_REFUNDED])
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->getQuery()
            ->getSingleScalarResult();

        return (int) ($result ?? 0);
    }

    /**
     * CA mensuel sur les N derniers mois complets + mois en cours.
     * Utilise SQL natif car DQL ne supporte pas YEAR()/MONTH() sans extension.
     *
     * @return list<array{year: int, month: int, revenue: int}>
     */
    public function revenueByMonth(int $months = 6): array
    {
        $since = (new \DateTimeImmutable('first day of this month'))->modify("-{$months} months");

        $conn = $this->getEntityManager()->getConnection();
        $sql  = '
            SELECT YEAR(created_at) AS y, MONTH(created_at) AS m, SUM(total) AS revenue
            FROM `order`
            WHERE status NOT IN (:cancelled, :refunded)
              AND created_at >= :since
            GROUP BY y, m
            ORDER BY y ASC, m ASC
        ';

        $rows = $conn->executeQuery($sql, [
            'cancelled' => Order::STATUS_CANCELLED,
            'refunded'  => Order::STATUS_REFUNDED,
            'since'     => $since->format('Y-m-d H:i:s'),
        ])->fetchAllAssociative();

        return array_map(fn(array $r) => [
            'year'    => (int) $r['y'],
            'month'   => (int) $r['m'],
            'revenue' => (int) ($r['revenue'] ?? 0),
        ], $rows);
    }

    /** Retourne les N commandes les plus récentes avec leur client. */
    public function findRecent(int $limit = 5): array
    {
        return $this->createQueryBuilder('o')
            ->join('o.customer', 'c')
            ->addSelect('c')
            ->orderBy('o.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /** @return \App\Entity\Order[] */
    public function findRecentByCustomer(int $customerId, int $limit = 5): array
    {
        return $this->createQueryBuilder('o')
            ->join('o.customer', 'c')
            ->andWhere('c.id = :cid')
            ->setParameter('cid', $customerId)
            ->orderBy('o.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function hasCustomerEmailOrderedProduct(string $email, int $productId): bool
    {
        $count = $this->getEntityManager()->createQueryBuilder()
            ->select('COUNT(oi.id)')
            ->from(\App\Entity\OrderItem::class, 'oi')
            ->join('oi.order', 'o')
            ->join('o.customer', 'c')
            ->where('c.email = :email')
            ->andWhere('oi.product = :pid')
            ->andWhere('o.status IN (:statuses)')
            ->setParameter('email', $email)
            ->setParameter('pid', $productId)
            ->setParameter('statuses', [
                \App\Entity\Order::STATUS_CONFIRMED,
                \App\Entity\Order::STATUS_SHIPPED,
                \App\Entity\Order::STATUS_DELIVERED,
            ])
            ->getQuery()
            ->getSingleScalarResult();

        return (int) $count > 0;
    }
}

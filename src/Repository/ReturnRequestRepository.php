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

    /**
     * Nombre de demandes de retour en attente de traitement (statut "requested").
     */
    public function countRequested(): int
    {
        return (int) $this->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->andWhere('r.status = :status')
            ->setParameter('status', ReturnRequest::STATUS_REQUESTED)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Quantités déjà retournées par ligne de commande pour une commande donnée.
     * Les retours refusés ne comptent pas (la quantité redevient disponible).
     *
     * @return array<int, int> orderItemId => quantité déjà retournée
     */
    public function getReturnedQuantitiesForOrder(int $orderId): array
    {
        /** @var list<array{itemId: int, qty: string|int}> $rows */
        $rows = $this->getEntityManager()->createQuery(
            'SELECT IDENTITY(ri.orderItem) AS itemId, SUM(ri.quantity) AS qty
             FROM App\Entity\ReturnItem ri
             JOIN ri.returnRequest rr
             WHERE rr.order = :orderId AND rr.status != :rejected
             GROUP BY ri.orderItem'
        )
            ->setParameter('orderId', $orderId)
            ->setParameter('rejected', ReturnRequest::STATUS_REJECTED)
            ->getResult();

        $map = [];
        foreach ($rows as $row) {
            $map[(int) $row['itemId']] = (int) $row['qty'];
        }

        return $map;
    }
}

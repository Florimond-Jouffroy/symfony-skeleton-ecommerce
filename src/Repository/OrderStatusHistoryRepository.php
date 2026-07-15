<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\OrderStatusHistory;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * Requêtes Doctrine pour l'entité OrderStatusHistory.
 * L'historique est principalement accédé via $order->getStatusHistory(),
 * ce repository reste donc minimal.
 *
 * @extends ServiceEntityRepository<OrderStatusHistory>
 */
class OrderStatusHistoryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, OrderStatusHistory::class);
    }
}

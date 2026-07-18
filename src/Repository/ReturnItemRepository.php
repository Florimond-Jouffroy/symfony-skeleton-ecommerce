<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\ReturnItem;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * Requêtes Doctrine pour l'entité ReturnItem.
 *
 * @extends ServiceEntityRepository<ReturnItem>
 */
class ReturnItemRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ReturnItem::class);
    }
}

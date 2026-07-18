<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\ProcessedWebhookEvent;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * Requêtes Doctrine pour l'entité ProcessedWebhookEvent.
 *
 * @extends ServiceEntityRepository<ProcessedWebhookEvent>
 */
class ProcessedWebhookEventRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ProcessedWebhookEvent::class);
    }

    public function isProcessed(string $provider, string $eventId): bool
    {
        return null !== $this->findOneBy(['provider' => $provider, 'eventId' => $eventId]);
    }
}

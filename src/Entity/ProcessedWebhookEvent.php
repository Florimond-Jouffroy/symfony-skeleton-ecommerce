<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\ProcessedWebhookEventRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Trace des événements de webhook de paiement déjà traités, pour garantir
 * l'idempotence : un même événement (renvoyé par le fournisseur en cas de
 * retry) ne déclenche pas deux fois les effets de bord (email, facture).
 */
#[ORM\Entity(repositoryClass: ProcessedWebhookEventRepository::class)]
#[ORM\UniqueConstraint(name: 'uniq_webhook_provider_event', columns: ['provider', 'event_id'])]
class ProcessedWebhookEvent
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 32)]
    private string $provider;

    #[ORM\Column(length: 255)]
    private string $eventId;

    #[ORM\Column]
    private \DateTimeImmutable $processedAt;

    public function __construct(string $provider, string $eventId)
    {
        $this->provider    = $provider;
        $this->eventId     = $eventId;
        $this->processedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getProvider(): string
    {
        return $this->provider;
    }

    public function getEventId(): string
    {
        return $this->eventId;
    }

    public function getProcessedAt(): \DateTimeImmutable
    {
        return $this->processedAt;
    }
}

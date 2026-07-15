<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\ActivityLogRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ActivityLogRepository::class)]
class ActivityLog
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100)]
    private string $action = '';

    #[ORM\Column(length: 50)]
    private string $entityType = '';

    #[ORM\Column(nullable: true)]
    private ?int $entityId = null;

    #[ORM\Column(length: 255)]
    private string $entityLabel = '';

    #[ORM\Column(type: 'json')]
    private array $context = [];

    #[ORM\Column(length: 255)]
    private string $performedByEmail = '';

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }

    public function getAction(): string { return $this->action; }
    public function setAction(string $action): static { $this->action = $action; return $this; }

    public function getEntityType(): string { return $this->entityType; }
    public function setEntityType(string $entityType): static { $this->entityType = $entityType; return $this; }

    public function getEntityId(): ?int { return $this->entityId; }
    public function setEntityId(?int $entityId): static { $this->entityId = $entityId; return $this; }

    public function getEntityLabel(): string { return $this->entityLabel; }
    public function setEntityLabel(string $entityLabel): static { $this->entityLabel = $entityLabel; return $this; }

    public function getContext(): array { return $this->context; }
    public function setContext(array $context): static { $this->context = $context; return $this; }

    public function getPerformedByEmail(): string { return $this->performedByEmail; }
    public function setPerformedByEmail(string $email): static { $this->performedByEmail = $email; return $this; }

    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
}

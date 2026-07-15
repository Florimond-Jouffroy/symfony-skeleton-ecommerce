<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\OrderStatusHistoryRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Trace chaque changement de statut d'une commande.
 *
 * Chaque transition (ex. pending → confirmed) crée une nouvelle entrée dans
 * cette table. C'est un log immuable : on n'édite jamais une entrée existante,
 * on en crée une nouvelle. Cela permet de retracer toute la vie de la commande.
 */
#[ORM\Entity(repositoryClass: OrderStatusHistoryRepository::class)]
class OrderStatusHistory
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Order::class, inversedBy: 'statusHistory')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Order $order;

    #[ORM\Column(length: 20)]
    private string $status = '';

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $comment = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }

    public function getOrder(): Order { return $this->order; }
    public function setOrder(Order $order): self { $this->order = $order; return $this; }

    public function getStatus(): string { return $this->status; }
    public function setStatus(string $status): self { $this->status = $status; return $this; }

    public function getComment(): ?string { return $this->comment; }
    public function setComment(?string $comment): self { $this->comment = $comment; return $this; }

    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
}

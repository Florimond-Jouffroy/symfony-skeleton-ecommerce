<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\SupportMessageRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SupportMessageRepository::class)]
#[ORM\HasLifecycleCallbacks]
class SupportMessage
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'messages')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?SupportTicket $ticket = null;

    #[ORM\Column(type: Types::TEXT)]
    private string $body = '';

    #[ORM\Column]
    private bool $isFromAdmin = false;

    #[ORM\Column(length: 100)]
    private string $authorName = '';

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\PrePersist]
    public function initCreatedAt(): void
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }

    public function getTicket(): ?SupportTicket { return $this->ticket; }
    public function setTicket(?SupportTicket $ticket): static { $this->ticket = $ticket; return $this; }

    public function getBody(): string { return $this->body; }
    public function setBody(string $body): static { $this->body = $body; return $this; }

    public function isFromAdmin(): bool { return $this->isFromAdmin; }
    public function setIsFromAdmin(bool $isFromAdmin): static { $this->isFromAdmin = $isFromAdmin; return $this; }

    public function getAuthorName(): string { return $this->authorName; }
    public function setAuthorName(string $authorName): static { $this->authorName = $authorName; return $this; }

    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
}

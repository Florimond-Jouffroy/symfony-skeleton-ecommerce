<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\SupportTicketRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SupportTicketRepository::class)]
#[ORM\HasLifecycleCallbacks]
class SupportTicket
{
    public const string STATUS_OPEN        = 'open';
    public const string STATUS_IN_PROGRESS = 'in_progress';
    public const string STATUS_CLOSED      = 'closed';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private string $subject = '';

    #[ORM\Column(length: 20)]
    private string $status = self::STATUS_OPEN;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $user = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $guestName = null;

    #[ORM\Column(length: 180, nullable: true)]
    private ?string $guestEmail = null;

    #[ORM\Column(length: 64, unique: true)]
    private string $token;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    /** @var Collection<int, SupportMessage> */
    #[ORM\OneToMany(targetEntity: SupportMessage::class, mappedBy: 'ticket', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['createdAt' => 'ASC'])]
    private Collection $messages;

    public function __construct()
    {
        $this->token    = bin2hex(random_bytes(32));
        $this->messages = new ArrayCollection();
    }

    #[ORM\PrePersist]
    public function initDates(): void
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    #[ORM\PreUpdate]
    public function refreshUpdatedAt(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function touch(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }

    public function getSubject(): string { return $this->subject; }
    public function setSubject(string $subject): static { $this->subject = $subject; return $this; }

    public function getStatus(): string { return $this->status; }
    public function setStatus(string $status): static { $this->status = $status; return $this; }

    public function getUser(): ?User { return $this->user; }
    public function setUser(?User $user): static { $this->user = $user; return $this; }

    public function getGuestName(): ?string { return $this->guestName; }
    public function setGuestName(?string $guestName): static { $this->guestName = $guestName; return $this; }

    public function getGuestEmail(): ?string { return $this->guestEmail; }
    public function setGuestEmail(?string $guestEmail): static { $this->guestEmail = $guestEmail; return $this; }

    public function getToken(): string { return $this->token; }

    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function getUpdatedAt(): \DateTimeImmutable { return $this->updatedAt; }

    public function isGuest(): bool { return $this->user === null; }

    public function getContactName(): string
    {
        if ($this->user) {
            return $this->user->getEmail();
        }
        return $this->guestName ?? 'Invité';
    }

    public function getContactEmail(): string
    {
        if ($this->user) {
            return $this->user->getEmail();
        }
        return $this->guestEmail ?? '';
    }

    /** @return Collection<int, SupportMessage> */
    public function getMessages(): Collection { return $this->messages; }

    public function addMessage(SupportMessage $message): static
    {
        if (!$this->messages->contains($message)) {
            $this->messages->add($message);
            $message->setTicket($this);
        }
        return $this;
    }
}

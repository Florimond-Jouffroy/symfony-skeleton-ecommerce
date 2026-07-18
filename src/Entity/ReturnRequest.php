<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\ReturnRequestRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Demande de retour (RMA) portant sur tout ou partie des articles d'une commande.
 * Cycle de vie : requested → approved → refunded, ou requested → rejected.
 */
#[ORM\Entity(repositoryClass: ReturnRequestRepository::class)]
#[ORM\HasLifecycleCallbacks]
class ReturnRequest
{
    public const string STATUS_REQUESTED = 'requested';
    public const string STATUS_APPROVED  = 'approved';
    public const string STATUS_REJECTED  = 'rejected';
    public const string STATUS_REFUNDED  = 'refunded';

    /** @var list<string> */
    public const array STATUSES = [
        self::STATUS_REQUESTED,
        self::STATUS_APPROVED,
        self::STATUS_REJECTED,
        self::STATUS_REFUNDED,
    ];

    /** @var array<string, list<string>> */
    private const array TRANSITIONS = [
        self::STATUS_REQUESTED => [self::STATUS_APPROVED, self::STATUS_REJECTED],
        self::STATUS_APPROVED  => [self::STATUS_REFUNDED],
        self::STATUS_REJECTED  => [],
        self::STATUS_REFUNDED  => [],
    ];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Order::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Order $order;

    #[ORM\ManyToOne(targetEntity: Customer::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Customer $customer;

    #[ORM\Column(length: 20, options: ['default' => self::STATUS_REQUESTED])]
    private string $status = self::STATUS_REQUESTED;

    #[ORM\Column(type: Types::TEXT)]
    private string $reason = '';

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $adminNote = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    /** @var Collection<int, ReturnItem> */
    #[ORM\OneToMany(targetEntity: ReturnItem::class, mappedBy: 'returnRequest', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $items;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
        $this->items     = new ArrayCollection();
    }

    #[ORM\PreUpdate]
    public function onPreUpdate(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function canTransitionTo(string $status): bool
    {
        return in_array($status, self::TRANSITIONS[$this->status] ?? [], true);
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getOrder(): Order
    {
        return $this->order;
    }

    public function setOrder(Order $order): self
    {
        $this->order = $order;

        return $this;
    }

    public function getCustomer(): Customer
    {
        return $this->customer;
    }

    public function setCustomer(Customer $customer): self
    {
        $this->customer = $customer;

        return $this;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): self
    {
        $this->status = $status;

        return $this;
    }

    public function getReason(): string
    {
        return $this->reason;
    }

    public function setReason(string $reason): self
    {
        $this->reason = $reason;

        return $this;
    }

    public function getAdminNote(): ?string
    {
        return $this->adminNote;
    }

    public function setAdminNote(?string $adminNote): self
    {
        $this->adminNote = $adminNote;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    /** @return Collection<int, ReturnItem> */
    public function getItems(): Collection
    {
        return $this->items;
    }

    public function addItem(ReturnItem $item): self
    {
        if (!$this->items->contains($item)) {
            $this->items->add($item);
            $item->setReturnRequest($this);
        }

        return $this;
    }
}

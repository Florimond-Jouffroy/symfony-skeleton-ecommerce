<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\InvoiceRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: InvoiceRepository::class)]
#[ORM\HasLifecycleCallbacks]
class Invoice
{
    public const string STATUS_PENDING   = 'pending';
    public const string STATUS_PAID      = 'paid';
    public const string STATUS_CANCELLED = 'cancelled';

    public const array STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_PAID,
        self::STATUS_CANCELLED,
    ];

    public const array STATUS_LABELS = [
        self::STATUS_PENDING   => 'En attente',
        self::STATUS_PAID      => 'Payée',
        self::STATUS_CANCELLED => 'Annulée',
    ];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 30, unique: true)]
    private string $invoiceNumber = '';

    #[ORM\OneToOne(targetEntity: Order::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Order $order;

    #[ORM\Column(length: 20, options: ['default' => 'pending'])]
    private string $status = self::STATUS_PENDING;

    #[ORM\Column]
    private \DateTimeImmutable $issuedAt;

    /** Sous-total HT des articles (avant remise) en centimes */
    #[ORM\Column]
    private int $subtotalHt = 0;

    /** Frais de livraison HT en centimes */
    #[ORM\Column]
    private int $shippingHt = 0;

    /** Remise en centimes (TTC, reportée de la commande) */
    #[ORM\Column(options: ['default' => 0])]
    private int $discountAmount = 0;

    /** Total HT (subtotalHt - discountHt + shippingHt) en centimes */
    #[ORM\Column]
    private int $totalHt = 0;

    /**
     * Détail TVA par taux.
     * Format : [{'rate': 20.0, 'baseHt': 5000, 'taxAmount': 1000}, ...]
     *
     * @var array<array{rate: float, baseHt: int, taxAmount: int}>
     */
    #[ORM\Column(type: Types::JSON)]
    private array $taxBreakdown = [];

    /** Montant total de TVA en centimes (somme de taxBreakdown[].taxAmount) */
    #[ORM\Column]
    private int $taxAmount = 0;

    /** Total TTC en centimes */
    #[ORM\Column]
    private int $totalTtc = 0;

    /**
     * Adresse de facturation snapshot.
     *
     * @var array<string, string>
     */
    #[ORM\Column(type: Types::JSON)]
    private array $billingAddress = [];

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    public function __construct()
    {
        $this->issuedAt  = new \DateTimeImmutable();
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    #[ORM\PreUpdate]
    public function onPreUpdate(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }

    public function getInvoiceNumber(): string { return $this->invoiceNumber; }
    public function setInvoiceNumber(string $n): self { $this->invoiceNumber = $n; return $this; }

    public function getOrder(): Order { return $this->order; }
    public function setOrder(Order $order): self { $this->order = $order; return $this; }

    public function getStatus(): string { return $this->status; }
    public function setStatus(string $status): self { $this->status = $status; return $this; }

    public function getIssuedAt(): \DateTimeImmutable { return $this->issuedAt; }
    public function setIssuedAt(\DateTimeImmutable $d): self { $this->issuedAt = $d; return $this; }

    public function getSubtotalHt(): int { return $this->subtotalHt; }
    public function setSubtotalHt(int $v): self { $this->subtotalHt = $v; return $this; }

    public function getShippingHt(): int { return $this->shippingHt; }
    public function setShippingHt(int $v): self { $this->shippingHt = $v; return $this; }

    public function getDiscountAmount(): int { return $this->discountAmount; }
    public function setDiscountAmount(int $v): self { $this->discountAmount = $v; return $this; }

    public function getTotalHt(): int { return $this->totalHt; }
    public function setTotalHt(int $v): self { $this->totalHt = $v; return $this; }

    /** @return array<array{rate: float, baseHt: int, taxAmount: int}> */
    public function getTaxBreakdown(): array { return $this->taxBreakdown; }
    /** @param array<array{rate: float, baseHt: int, taxAmount: int}> $v */
    public function setTaxBreakdown(array $v): self { $this->taxBreakdown = $v; return $this; }

    public function getTaxAmount(): int { return $this->taxAmount; }
    public function setTaxAmount(int $v): self { $this->taxAmount = $v; return $this; }

    public function getTotalTtc(): int { return $this->totalTtc; }
    public function setTotalTtc(int $v): self { $this->totalTtc = $v; return $this; }

    /** @return array<string, string> */
    public function getBillingAddress(): array { return $this->billingAddress; }
    /** @param array<string, string> $addr */
    public function setBillingAddress(array $addr): self { $this->billingAddress = $addr; return $this; }

    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function getUpdatedAt(): \DateTimeImmutable { return $this->updatedAt; }
}

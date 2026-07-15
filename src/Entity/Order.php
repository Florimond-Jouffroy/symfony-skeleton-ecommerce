<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\OrderRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Commande passée par un client sur la boutique.
 *
 * Cycle de vie géré par une machine à états simple (voir TRANSITIONS).
 * Les montants (subtotal, discountAmount, shippingAmount, total) sont tous
 * stockés en centimes d'euro (ex. 1999 = 19,99 €) pour éviter les erreurs
 * d'arrondi liées aux nombres flottants.
 *
 * Note : le nom de table est entouré de backticks car "order" est un mot
 * réservé SQL. Sans ça, toutes les requêtes Doctrine échoueraient.
 */
#[ORM\Entity(repositoryClass: OrderRepository::class)]
#[ORM\Table(name: '`order`')]
#[ORM\HasLifecycleCallbacks]
class Order
{
    public const string STATUS_PENDING   = 'pending';
    public const string STATUS_CONFIRMED = 'confirmed';
    public const string STATUS_SHIPPED   = 'shipped';
    public const string STATUS_DELIVERED = 'delivered';
    public const string STATUS_CANCELLED = 'cancelled';
    public const string STATUS_REFUNDED  = 'refunded';

    /** Liste complète des statuts valides, utilisée pour valider les entrées API. */
    public const array STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_CONFIRMED,
        self::STATUS_SHIPPED,
        self::STATUS_DELIVERED,
        self::STATUS_CANCELLED,
        self::STATUS_REFUNDED,
    ];

    /**
     * Définit les transitions autorisées depuis chaque statut.
     * Lire comme : "depuis pending, on peut aller vers confirmed ou cancelled".
     * cancelled et refunded sont des états terminaux : aucune transition possible.
     * Ce tableau est aussi envoyé au frontend pour afficher uniquement
     * les boutons d'action pertinents sur la fiche commande.
     */
    public const array TRANSITIONS = [
        self::STATUS_PENDING   => [self::STATUS_CONFIRMED, self::STATUS_CANCELLED],
        self::STATUS_CONFIRMED => [self::STATUS_SHIPPED,   self::STATUS_CANCELLED],
        self::STATUS_SHIPPED   => [self::STATUS_DELIVERED, self::STATUS_CANCELLED],
        self::STATUS_DELIVERED => [self::STATUS_REFUNDED],
        self::STATUS_CANCELLED => [],
        self::STATUS_REFUNDED  => [],
    ];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 30, unique: true)]
    private string $orderNumber = '';

    #[ORM\ManyToOne(targetEntity: Customer::class, inversedBy: 'orders')]
    #[ORM\JoinColumn(nullable: false)]
    private Customer $customer;

    #[ORM\Column(length: 20, options: ['default' => 'pending'])]
    private string $status = self::STATUS_PENDING;

    /** Subtotal before discount and shipping, in euro cents */
    #[ORM\Column]
    private int $subtotal = 0;

    /** Discount amount in euro cents */
    #[ORM\Column(options: ['default' => 0])]
    private int $discountAmount = 0;

    /** Shipping cost in euro cents */
    #[ORM\Column(options: ['default' => 0])]
    private int $shippingAmount = 0;

    /** Grand total in euro cents */
    #[ORM\Column]
    private int $total = 0;

    /**
     * Shipping address snapshot.
     * Keys: firstName, lastName, line1, line2?, city, postalCode, country
     *
     * @var array<string, string>
     */
    #[ORM\Column(type: Types::JSON)]
    private array $shippingAddress = [];

    /**
     * Billing address snapshot (null = same as shipping).
     *
     * @var array<string, string>|null
     */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $billingAddress = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $promoCode = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $customerNote = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $internalNote = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    /** @var Collection<int, OrderItem> */
    #[ORM\OneToMany(targetEntity: OrderItem::class, mappedBy: 'order', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $items;

    /** @var Collection<int, OrderStatusHistory> */
    #[ORM\OneToMany(targetEntity: OrderStatusHistory::class, mappedBy: 'order', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['createdAt' => 'DESC'])]
    private Collection $statusHistory;

    public function __construct()
    {
        $this->createdAt     = new \DateTimeImmutable();
        $this->updatedAt     = new \DateTimeImmutable();
        $this->items         = new ArrayCollection();
        $this->statusHistory = new ArrayCollection();
    }

    #[ORM\PreUpdate]
    public function onPreUpdate(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    /**
     * Vérifie si la transition vers le statut donné est autorisée depuis l'état actuel.
     * À appeler avant tout appel à setStatus() pour respecter la machine à états.
     */
    public function canTransitionTo(string $status): bool
    {
        return in_array($status, self::TRANSITIONS[$this->status] ?? [], true);
    }

    public function getId(): ?int { return $this->id; }

    public function getOrderNumber(): string { return $this->orderNumber; }
    public function setOrderNumber(string $orderNumber): self { $this->orderNumber = $orderNumber; return $this; }

    public function getCustomer(): Customer { return $this->customer; }
    public function setCustomer(Customer $customer): self { $this->customer = $customer; return $this; }

    public function getStatus(): string { return $this->status; }
    public function setStatus(string $status): self { $this->status = $status; return $this; }

    public function getSubtotal(): int { return $this->subtotal; }
    public function setSubtotal(int $subtotal): self { $this->subtotal = $subtotal; return $this; }

    public function getDiscountAmount(): int { return $this->discountAmount; }
    public function setDiscountAmount(int $discountAmount): self { $this->discountAmount = $discountAmount; return $this; }

    public function getShippingAmount(): int { return $this->shippingAmount; }
    public function setShippingAmount(int $shippingAmount): self { $this->shippingAmount = $shippingAmount; return $this; }

    public function getTotal(): int { return $this->total; }
    public function setTotal(int $total): self { $this->total = $total; return $this; }

    /** @return array<string, string> */
    public function getShippingAddress(): array { return $this->shippingAddress; }
    /** @param array<string, string> $shippingAddress */
    public function setShippingAddress(array $shippingAddress): self { $this->shippingAddress = $shippingAddress; return $this; }

    /** @return array<string, string>|null */
    public function getBillingAddress(): ?array { return $this->billingAddress; }
    /** @param array<string, string>|null $billingAddress */
    public function setBillingAddress(?array $billingAddress): self { $this->billingAddress = $billingAddress; return $this; }

    public function getPromoCode(): ?string { return $this->promoCode; }
    public function setPromoCode(?string $promoCode): self { $this->promoCode = $promoCode; return $this; }

    public function getCustomerNote(): ?string { return $this->customerNote; }
    public function setCustomerNote(?string $customerNote): self { $this->customerNote = $customerNote; return $this; }

    public function getInternalNote(): ?string { return $this->internalNote; }
    public function setInternalNote(?string $internalNote): self { $this->internalNote = $internalNote; return $this; }

    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function getUpdatedAt(): \DateTimeImmutable { return $this->updatedAt; }

    /** @return Collection<int, OrderItem> */
    public function getItems(): Collection { return $this->items; }

    /** @return Collection<int, OrderStatusHistory> */
    public function getStatusHistory(): Collection { return $this->statusHistory; }

    /**
     * Recalcule subtotal et total à partir des lignes de commande.
     * À appeler après avoir ajouté/modifié des OrderItem.
     * Le total est plafonné à 0 pour éviter un montant négatif si la remise
     * dépasse le sous-total (ex. bon de réduction trop généreux).
     */
    public function recalculateTotal(): void
    {
        $this->subtotal = array_reduce(
            $this->items->toArray(),
            static fn (int $carry, OrderItem $item) => $carry + $item->getTotal(),
            0,
        );
        $this->total = max(0, $this->subtotal - $this->discountAmount + $this->shippingAmount);
    }
}

<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\OrderItemRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Ligne d'une commande (un produit acheté).
 *
 * Applique le "snapshot pattern" : les champs productName, variantName et
 * unitPrice sont copiés depuis le catalogue au moment de la commande et ne
 * changent plus jamais, même si le produit est modifié ou supprimé ensuite.
 * Les FK product et variant sont volontairement nullable (ON DELETE SET NULL)
 * pour ne pas bloquer la suppression d'un produit du catalogue.
 */
#[ORM\Entity(repositoryClass: OrderItemRepository::class)]
class OrderItem
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Order::class, inversedBy: 'items')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Order $order;

    /** Nullable: product may be deleted after order */
    #[ORM\ManyToOne(targetEntity: Product::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Product $product = null;

    #[ORM\ManyToOne(targetEntity: ProductVariant::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?ProductVariant $variant = null;

    /** Snapshot of product name at order time */
    #[ORM\Column(length: 255)]
    private string $productName = '';

    /** Snapshot of variant name at order time */
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $variantName = null;

    /** Unit price in euro cents at order time */
    #[ORM\Column]
    private int $unitPrice = 0;

    #[ORM\Column]
    private int $quantity = 1;

    /** unitPrice × quantity */
    #[ORM\Column]
    private int $total = 0;

    public function getId(): ?int { return $this->id; }

    public function getOrder(): Order { return $this->order; }
    public function setOrder(Order $order): self { $this->order = $order; return $this; }

    public function getProduct(): ?Product { return $this->product; }
    public function setProduct(?Product $product): self { $this->product = $product; return $this; }

    public function getVariant(): ?ProductVariant { return $this->variant; }
    public function setVariant(?ProductVariant $variant): self { $this->variant = $variant; return $this; }

    public function getProductName(): string { return $this->productName; }
    public function setProductName(string $productName): self { $this->productName = $productName; return $this; }

    public function getVariantName(): ?string { return $this->variantName; }
    public function setVariantName(?string $variantName): self { $this->variantName = $variantName; return $this; }

    public function getUnitPrice(): int { return $this->unitPrice; }
    public function setUnitPrice(int $unitPrice): self { $this->unitPrice = $unitPrice; return $this; }

    public function getQuantity(): int { return $this->quantity; }
    public function setQuantity(int $quantity): self { $this->quantity = $quantity; return $this; }

    public function getTotal(): int { return $this->total; }

    public function recalculateTotal(): void
    {
        $this->total = $this->unitPrice * $this->quantity;
    }
}

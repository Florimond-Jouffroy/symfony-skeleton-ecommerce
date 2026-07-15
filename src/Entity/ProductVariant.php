<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\ProductVariantRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Déclinaison d'un produit (ex. "Rouge / L", "Bleu / XL").
 *
 * Utilisée uniquement quand Product::$hasVariants = true. Dans ce cas,
 * le stock global n'est plus géré au niveau du produit mais par la somme
 * des stocks de ses variantes actives. Chaque variante peut avoir un prix
 * propre (priceOverride) ou hériter du prix de base du produit.
 */
#[ORM\Entity(repositoryClass: ProductVariantRepository::class)]
class ProductVariant
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Product::class, inversedBy: 'variants')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Product $product;

    /** Human-readable label, e.g. "Rouge / L" */
    #[ORM\Column(length: 255)]
    private string $name = '';

    #[ORM\Column(length: 100, nullable: true, unique: true)]
    private ?string $sku = null;

    /** If null, inherits product price */
    #[ORM\Column(nullable: true)]
    private ?int $priceOverride = null;

    #[ORM\Column(options: ['default' => 0])]
    private int $stock = 0;

    #[ORM\Column(options: ['default' => 5])]
    private int $lowStockThreshold = 5;

    #[ORM\Column(options: ['default' => 0])]
    private int $position = 0;

    /** e.g. [["name":"Couleur","value":"Rouge"],["name":"Taille","value":"L"]] */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $attributes = null;

    #[ORM\Column(options: ['default' => true])]
    private bool $isActive = true;

    public function getId(): ?int { return $this->id; }

    public function getProduct(): Product { return $this->product; }
    public function setProduct(Product $product): self { $this->product = $product; return $this; }

    public function getName(): string { return $this->name; }
    public function setName(string $name): self { $this->name = $name; return $this; }

    public function getSku(): ?string { return $this->sku; }
    public function setSku(?string $sku): self { $this->sku = $sku ?: null; return $this; }

    public function getPriceOverride(): ?int { return $this->priceOverride; }
    public function setPriceOverride(?int $priceOverride): self { $this->priceOverride = $priceOverride; return $this; }

    /**
     * Retourne le prix réel de la variante.
     * Si aucun prix spécifique n'est défini, on utilise le prix de base du produit.
     */
    public function getEffectivePrice(): int
    {
        return $this->priceOverride ?? $this->product->getPrice();
    }

    public function getStock(): int { return $this->stock; }
    public function setStock(int $stock): self { $this->stock = $stock; return $this; }

    public function getLowStockThreshold(): int { return $this->lowStockThreshold; }
    public function setLowStockThreshold(int $lowStockThreshold): self { $this->lowStockThreshold = $lowStockThreshold; return $this; }

    public function getPosition(): int { return $this->position; }
    public function setPosition(int $position): self { $this->position = $position; return $this; }

    public function getAttributes(): ?array { return $this->attributes; }
    public function setAttributes(?array $attributes): self { $this->attributes = $attributes; return $this; }

    public function isActive(): bool { return $this->isActive; }
    public function setIsActive(bool $isActive): self { $this->isActive = $isActive; return $this; }
}

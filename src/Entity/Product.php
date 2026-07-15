<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\ProductRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Produit de la boutique.
 *
 * Deux modes de gestion du stock :
 * - hasVariants = false : stock simple géré directement sur le produit
 * - hasVariants = true  : stock calculé en sommant les stocks des variantes actives
 *
 * Tous les prix sont en centimes d'euro (ex. 1999 = 19,99 €).
 * compareAtPrice est le "prix barré" affiché avant remise.
 *
 * Le statut suit un cycle simple : draft → published (et retour).
 * Un produit en draft n'est pas visible sur la boutique.
 */
#[ORM\Entity(repositoryClass: ProductRepository::class)]
#[ORM\HasLifecycleCallbacks]
class Product
{
    public const string STATUS_DRAFT     = 'draft';
    public const string STATUS_PUBLISHED = 'published';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private string $name = '';

    #[ORM\Column(length: 255, unique: true)]
    private string $slug = '';

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $description = null;

    /** Price in euro cents (e.g. 1999 = 19.99 €) */
    #[ORM\Column]
    private int $price = 0;

    /** Crossed-out price in euro cents */
    #[ORM\Column(nullable: true)]
    private ?int $compareAtPrice = null;

    #[ORM\Column(length: 20, options: ['default' => 'draft'])]
    private string $status = self::STATUS_DRAFT;

    /** Stock for simple products (no variants) */
    #[ORM\Column(options: ['default' => 0])]
    private int $stock = 0;

    #[ORM\Column(options: ['default' => 5])]
    private int $lowStockThreshold = 5;

    #[ORM\Column(options: ['default' => false])]
    private bool $hasVariants = false;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    /** @var Collection<int, ProductCategory> */
    #[ORM\ManyToMany(targetEntity: ProductCategory::class, inversedBy: 'products')]
    #[ORM\JoinTable(name: 'product_product_category')]
    private Collection $categories;

    /** @var Collection<int, ProductImage> */
    #[ORM\OneToMany(targetEntity: ProductImage::class, mappedBy: 'product', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['position' => 'ASC'])]
    private Collection $images;

    /** @var Collection<int, ProductVariant> */
    #[ORM\OneToMany(targetEntity: ProductVariant::class, mappedBy: 'product', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['position' => 'ASC'])]
    private Collection $variants;

    public function __construct()
    {
        $this->createdAt  = new \DateTimeImmutable();
        $this->updatedAt  = new \DateTimeImmutable();
        $this->categories = new ArrayCollection();
        $this->images     = new ArrayCollection();
        $this->variants   = new ArrayCollection();
    }

    #[ORM\PreUpdate]
    public function onPreUpdate(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }

    public function getName(): string { return $this->name; }
    public function setName(string $name): self { $this->name = $name; return $this; }

    public function getSlug(): string { return $this->slug; }
    public function setSlug(string $slug): self { $this->slug = $slug; return $this; }

    public function getDescription(): ?array { return $this->description; }
    public function setDescription(?array $description): self { $this->description = $description; return $this; }

    public function getPrice(): int { return $this->price; }
    public function setPrice(int $price): self { $this->price = $price; return $this; }

    public function getCompareAtPrice(): ?int { return $this->compareAtPrice; }
    public function setCompareAtPrice(?int $compareAtPrice): self { $this->compareAtPrice = $compareAtPrice; return $this; }

    public function getStatus(): string { return $this->status; }
    public function setStatus(string $status): self { $this->status = $status; return $this; }
    public function isDraft(): bool { return self::STATUS_DRAFT === $this->status; }
    public function isPublished(): bool { return self::STATUS_PUBLISHED === $this->status; }

    public function getStock(): int { return $this->stock; }
    public function setStock(int $stock): self { $this->stock = $stock; return $this; }

    public function getLowStockThreshold(): int { return $this->lowStockThreshold; }
    public function setLowStockThreshold(int $lowStockThreshold): self { $this->lowStockThreshold = $lowStockThreshold; return $this; }

    public function hasVariants(): bool { return $this->hasVariants; }
    public function setHasVariants(bool $hasVariants): self { $this->hasVariants = $hasVariants; return $this; }

    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function getUpdatedAt(): \DateTimeImmutable { return $this->updatedAt; }

    /** @return Collection<int, ProductCategory> */
    public function getCategories(): Collection { return $this->categories; }

    /**
     * Remplace toutes les catégories du produit par le tableau fourni.
     * On vide d'abord la collection pour gérer proprement les ajouts ET les suppressions
     * en un seul appel, sans avoir à comparer l'ancienne et la nouvelle liste.
     */
    public function syncCategories(array $categories): self
    {
        $this->categories->clear();
        foreach ($categories as $cat) {
            $this->categories->add($cat);
        }
        return $this;
    }

    /** @return Collection<int, ProductImage> */
    public function getImages(): Collection { return $this->images; }

    /** @return Collection<int, ProductVariant> */
    public function getVariants(): Collection { return $this->variants; }
}

<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\ProductImageRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Image associée à un produit.
 *
 * Les images sont triées par `position` (ASC) : la première (position 0)
 * est la photo principale affichée dans les listes. L'URL pointe vers
 * une ressource de la médiathèque ou un CDN externe.
 */
#[ORM\Entity(repositoryClass: ProductImageRepository::class)]
class ProductImage
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Product::class, inversedBy: 'images')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Product $product;

    #[ORM\Column(length: 500)]
    private string $url = '';

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $alt = null;

    #[ORM\Column(options: ['default' => 0])]
    private int $position = 0;

    public function getId(): ?int { return $this->id; }

    public function getProduct(): Product { return $this->product; }
    public function setProduct(Product $product): self { $this->product = $product; return $this; }

    public function getUrl(): string { return $this->url; }
    public function setUrl(string $url): self { $this->url = $url; return $this; }

    public function getAlt(): ?string { return $this->alt; }
    public function setAlt(?string $alt): self { $this->alt = $alt; return $this; }

    public function getPosition(): int { return $this->position; }
    public function setPosition(int $position): self { $this->position = $position; return $this; }
}

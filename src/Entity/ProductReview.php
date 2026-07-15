<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\ProductReviewRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ProductReviewRepository::class)]
#[ORM\Table(name: 'product_review')]
#[ORM\HasLifecycleCallbacks]
class ProductReview
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Product::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Product $product;

    /** Nullable: user account may be deleted after review */
    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $user = null;

    /** Snapshot of user email at review time */
    #[ORM\Column(length: 180)]
    private string $authorName = '';

    #[ORM\Column(type: Types::SMALLINT)]
    private int $rating = 5;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $comment = null;

    #[ORM\Column]
    private bool $isApproved = false;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\PrePersist]
    public function initCreatedAt(): void
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }

    public function getProduct(): Product { return $this->product; }
    public function setProduct(Product $product): static { $this->product = $product; return $this; }

    public function getUser(): ?User { return $this->user; }
    public function setUser(?User $user): static { $this->user = $user; return $this; }

    public function getAuthorName(): string { return $this->authorName; }
    public function setAuthorName(string $authorName): static { $this->authorName = $authorName; return $this; }

    public function getRating(): int { return $this->rating; }
    public function setRating(int $rating): static { $this->rating = max(1, min(5, $rating)); return $this; }

    public function getComment(): ?string { return $this->comment; }
    public function setComment(?string $comment): static { $this->comment = $comment; return $this; }

    public function isApproved(): bool { return $this->isApproved; }
    public function setIsApproved(bool $isApproved): static { $this->isApproved = $isApproved; return $this; }

    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
}

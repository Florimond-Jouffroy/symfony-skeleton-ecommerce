<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\PromoCodeRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PromoCodeRepository::class)]
#[ORM\HasLifecycleCallbacks]
class PromoCode
{
    public const string TYPE_PERCENT = 'percent';
    public const string TYPE_FIXED   = 'fixed';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 50, unique: true)]
    private string $code = '';

    /** 'percent' ou 'fixed' (centimes) */
    #[ORM\Column(length: 10)]
    private string $type = self::TYPE_PERCENT;

    /** Valeur : pourcentage (ex. 15 = 15 %) ou montant fixe en centimes (ex. 500 = 5,00 €) */
    #[ORM\Column]
    private int $value = 0;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $expiresAt = null;

    /** Nombre maximum d'utilisations — null = illimité */
    #[ORM\Column(nullable: true)]
    private ?int $maxUses = null;

    #[ORM\Column(options: ['default' => 0])]
    private int $usedCount = 0;

    #[ORM\Column(options: ['default' => true])]
    private bool $isActive = true;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }

    public function getCode(): string { return $this->code; }
    public function setCode(string $code): self { $this->code = strtoupper(trim($code)); return $this; }

    public function getType(): string { return $this->type; }
    public function setType(string $type): self { $this->type = $type; return $this; }

    public function getValue(): int { return $this->value; }
    public function setValue(int $value): self { $this->value = $value; return $this; }

    public function getExpiresAt(): ?\DateTimeImmutable { return $this->expiresAt; }
    public function setExpiresAt(?\DateTimeImmutable $expiresAt): self { $this->expiresAt = $expiresAt; return $this; }

    public function getMaxUses(): ?int { return $this->maxUses; }
    public function setMaxUses(?int $maxUses): self { $this->maxUses = $maxUses; return $this; }

    public function getUsedCount(): int { return $this->usedCount; }
    public function incrementUsedCount(): self { ++$this->usedCount; return $this; }

    public function isActive(): bool { return $this->isActive; }
    public function setIsActive(bool $isActive): self { $this->isActive = $isActive; return $this; }

    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }

    /** Vérifie si le code est utilisable (actif, non expiré, quota non atteint). */
    public function isUsable(): bool
    {
        if (!$this->isActive) {
            return false;
        }
        if (null !== $this->expiresAt && $this->expiresAt < new \DateTimeImmutable()) {
            return false;
        }
        if (null !== $this->maxUses && $this->usedCount >= $this->maxUses) {
            return false;
        }
        return true;
    }

    /** Calcule la remise en centimes pour un sous-total donné. */
    public function computeDiscount(int $subtotalCents): int
    {
        if (self::TYPE_PERCENT === $this->type) {
            return (int) round($subtotalCents * $this->value / 100);
        }
        return min($this->value, $subtotalCents);
    }
}

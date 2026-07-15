<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\ShippingMethodRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Méthode de livraison proposée à la commande.
 *
 * price             : coût de base en centimes (ex. 490 = 4,90 €)
 * freeAboveAmount   : si le sous-total commande ≥ ce montant, la livraison est offerte
 *                     null = jamais gratuite automatiquement
 */
#[ORM\Entity(repositoryClass: ShippingMethodRepository::class)]
class ShippingMethod
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private string $name = '';

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    /** Price in euro cents */
    #[ORM\Column(options: ['default' => 0])]
    private int $price = 0;

    /** Cart subtotal threshold (cents) above which shipping is free */
    #[ORM\Column(nullable: true)]
    private ?int $freeAboveAmount = null;

    #[ORM\Column(options: ['default' => true])]
    private bool $isActive = true;

    #[ORM\Column(options: ['default' => 0])]
    private int $position = 0;

    public function getId(): ?int { return $this->id; }

    public function getName(): string { return $this->name; }
    public function setName(string $name): self { $this->name = $name; return $this; }

    public function getDescription(): ?string { return $this->description; }
    public function setDescription(?string $description): self { $this->description = $description; return $this; }

    public function getPrice(): int { return $this->price; }
    public function setPrice(int $price): self { $this->price = $price; return $this; }

    public function getFreeAboveAmount(): ?int { return $this->freeAboveAmount; }
    public function setFreeAboveAmount(?int $amount): self { $this->freeAboveAmount = $amount; return $this; }

    public function isActive(): bool { return $this->isActive; }
    public function setIsActive(bool $isActive): self { $this->isActive = $isActive; return $this; }

    public function getPosition(): int { return $this->position; }
    public function setPosition(int $position): self { $this->position = $position; return $this; }

    /**
     * Returns the effective price for a given cart subtotal.
     * If freeAboveAmount is set and subtotal >= freeAboveAmount, returns 0.
     */
    public function getEffectivePrice(int $subtotal = 0): int
    {
        if ($this->freeAboveAmount !== null && $subtotal >= $this->freeAboveAmount) {
            return 0;
        }

        return $this->price;
    }
}

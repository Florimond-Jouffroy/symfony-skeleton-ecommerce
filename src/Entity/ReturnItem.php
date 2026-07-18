<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\ReturnItemRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Ligne d'une demande de retour : renvoie à une ligne de commande d'origine
 * (OrderItem) et la quantité retournée (≤ quantité commandée).
 */
#[ORM\Entity(repositoryClass: ReturnItemRepository::class)]
class ReturnItem
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: ReturnRequest::class, inversedBy: 'items')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ReturnRequest $returnRequest;

    #[ORM\ManyToOne(targetEntity: OrderItem::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private OrderItem $orderItem;

    #[ORM\Column]
    private int $quantity = 1;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getReturnRequest(): ReturnRequest
    {
        return $this->returnRequest;
    }

    public function setReturnRequest(ReturnRequest $returnRequest): self
    {
        $this->returnRequest = $returnRequest;

        return $this;
    }

    public function getOrderItem(): OrderItem
    {
        return $this->orderItem;
    }

    public function setOrderItem(OrderItem $orderItem): self
    {
        $this->orderItem = $orderItem;

        return $this;
    }

    public function getQuantity(): int
    {
        return $this->quantity;
    }

    public function setQuantity(int $quantity): self
    {
        $this->quantity = $quantity;

        return $this;
    }
}

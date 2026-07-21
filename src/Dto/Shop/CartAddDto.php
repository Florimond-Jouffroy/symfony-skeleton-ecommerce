<?php

declare(strict_types=1);

namespace App\Dto\Shop;

use Symfony\Component\Validator\Constraints as Assert;

class CartAddDto
{
    #[Assert\Positive(message: 'Produit invalide.')]
    public int $productId = 0;

    public ?int $variantId = null;

    /** Quantité demandée — ramenée à 1 minimum côté controller. */
    public int $quantity = 1;
}

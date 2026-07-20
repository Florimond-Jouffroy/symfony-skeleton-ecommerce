<?php

declare(strict_types=1);

namespace App\Dto;

class CartUpdateDto
{
    /** Nouvelle quantité — 0 (ou moins) retire la ligne du panier. */
    public int $quantity = 0;
}

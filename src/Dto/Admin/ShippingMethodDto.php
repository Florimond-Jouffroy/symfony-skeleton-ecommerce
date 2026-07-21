<?php

declare(strict_types=1);

namespace App\Dto\Admin;

use Symfony\Component\Validator\Constraints as Assert;

class ShippingMethodDto
{
    #[Assert\NotBlank(message: 'Le nom est obligatoire.', normalizer: 'trim')]
    public string $name = '';

    public ?string $description = null;

    /** Prix en centimes. */
    #[Assert\PositiveOrZero(message: 'Le prix doit être un entier positif ou nul (en centimes).')]
    public int $price = 0;

    /** Montant (centimes) à partir duquel la livraison est offerte — null = jamais. */
    #[Assert\PositiveOrZero(message: 'Le montant de gratuité doit être un entier positif ou nul (en centimes).')]
    public ?int $freeAboveAmount = null;

    public bool $isActive = true;

    #[Assert\PositiveOrZero]
    public int $position = 0;
}

<?php

declare(strict_types=1);

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

class ProductCategoryDto
{
    #[Assert\NotBlank(message: 'Le nom est obligatoire.', normalizer: 'trim')]
    public string $name = '';

    /** Taux de TVA spécifique à la catégorie — null = taux par défaut de la boutique. */
    #[Assert\PositiveOrZero(message: 'Le taux de TVA doit être positif ou nul.')]
    public ?int $taxRate = null;
}

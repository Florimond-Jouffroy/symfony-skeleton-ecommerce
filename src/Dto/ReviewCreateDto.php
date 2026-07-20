<?php

declare(strict_types=1);

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

class ReviewCreateDto
{
    #[Assert\Positive(message: 'Données invalides.')]
    public int $productId = 0;

    #[Assert\Range(min: 1, max: 5, notInRangeMessage: 'Données invalides.')]
    public int $rating = 0;

    public ?string $comment = null;
}

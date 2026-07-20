<?php

declare(strict_types=1);

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

class CategoryDto
{
    #[Assert\NotBlank(message: 'Le nom est obligatoire.', normalizer: 'trim')]
    public string $name = '';
}

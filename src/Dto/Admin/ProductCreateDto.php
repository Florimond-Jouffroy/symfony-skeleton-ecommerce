<?php

declare(strict_types=1);

namespace App\Dto\Admin;

use Symfony\Component\Validator\Constraints as Assert;

class ProductCreateDto
{
    #[Assert\NotBlank(message: 'Le nom est obligatoire.', normalizer: 'trim')]
    public string $name = '';

    /** @var list<int> */
    #[Assert\All([new Assert\Type('integer')])]
    public array $categoryIds = [];
}

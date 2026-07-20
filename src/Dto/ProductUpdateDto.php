<?php

declare(strict_types=1);

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

class ProductUpdateDto
{
    #[Assert\NotBlank(message: 'Le nom est obligatoire.', normalizer: 'trim')]
    public string $name = '';

    /** @var list<int> */
    #[Assert\All([new Assert\Type('integer')])]
    public array $categoryIds = [];

    /** Prix en centimes. */
    #[Assert\PositiveOrZero(message: 'Le prix doit être positif ou nul.')]
    public int $price = 0;

    /** Prix barré en centimes — null = aucun. */
    #[Assert\PositiveOrZero(message: 'Le prix barré doit être positif ou nul.')]
    public ?int $compareAtPrice = null;

    /** @var array<mixed>|null */
    public ?array $description = null;

    #[Assert\PositiveOrZero(message: 'Le stock doit être positif ou nul.')]
    public int $stock = 0;

    #[Assert\PositiveOrZero]
    public int $lowStockThreshold = 5;

    public bool $hasVariants = false;

    /** @var array<mixed> */
    public array $images = [];

    /** @var array<mixed> */
    public array $variants = [];

    /** Version chargée par le client, pour le verrou optimiste (null = pas de contrôle). */
    public ?int $version = null;
}

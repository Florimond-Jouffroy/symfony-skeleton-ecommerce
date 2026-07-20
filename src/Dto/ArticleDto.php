<?php

declare(strict_types=1);

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

class ArticleDto
{
    #[Assert\NotBlank(message: 'Le titre est obligatoire.', normalizer: 'trim')]
    public string $title = '';

    /**
     * Contenu structuré (blocs).
     *
     * @var array<mixed>
     */
    public array $content = [];

    public ?string $excerpt = null;

    public ?string $coverImage = null;

    /** @var list<int> */
    #[Assert\All([new Assert\Type('integer')])]
    public array $categoryIds = [];
}

<?php

declare(strict_types=1);

namespace App\Dto\Admin;

use Symfony\Component\Validator\Constraints as Assert;

class StaticPageDto
{
    #[Assert\NotBlank(message: 'Le titre est requis.', normalizer: 'trim')]
    public string $title = '';

    /** Optionnel à la création (généré depuis le titre si vide) ; requis en modification. */
    public string $slug = '';

    /** @var array<mixed> */
    public array $content = [];

    public bool $isActive = true;
}

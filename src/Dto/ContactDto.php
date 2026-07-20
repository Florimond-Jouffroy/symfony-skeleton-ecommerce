<?php

declare(strict_types=1);

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

class ContactDto
{
    #[Assert\NotBlank(message: 'Le nom est requis.', normalizer: 'trim')]
    public string $name = '';

    #[Assert\NotBlank(message: 'Adresse e-mail invalide.', normalizer: 'trim')]
    #[Assert\Email(message: 'Adresse e-mail invalide.')]
    public string $email = '';

    #[Assert\NotBlank(message: 'Le sujet est requis.', normalizer: 'trim')]
    public string $subject = '';

    #[Assert\NotBlank(message: 'Le message est requis.', normalizer: 'trim')]
    public string $body = '';
}

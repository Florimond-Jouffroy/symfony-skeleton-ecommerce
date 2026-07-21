<?php

declare(strict_types=1);

namespace App\Dto\Account;

use Symfony\Component\Validator\Constraints as Assert;

class ProfileUpdateDto
{
    #[Assert\NotBlank(message: 'Le prénom et le nom sont obligatoires.', normalizer: 'trim')]
    public string $firstName = '';

    #[Assert\NotBlank(message: 'Le prénom et le nom sont obligatoires.', normalizer: 'trim')]
    public string $lastName = '';

    public ?string $phone = null;
}

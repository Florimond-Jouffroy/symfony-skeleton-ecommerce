<?php

declare(strict_types=1);

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

class ShippingAddressDto
{
    #[Assert\NotBlank(message: 'Le prénom est obligatoire.', normalizer: 'trim')]
    public string $firstName = '';

    #[Assert\NotBlank(message: 'Le nom est obligatoire.', normalizer: 'trim')]
    public string $lastName = '';

    #[Assert\NotBlank(message: 'L\'adresse est obligatoire.', normalizer: 'trim')]
    public string $line1 = '';

    public ?string $line2 = null;

    #[Assert\NotBlank(message: 'La ville est obligatoire.', normalizer: 'trim')]
    public string $city = '';

    #[Assert\NotBlank(message: 'Le code postal est obligatoire.', normalizer: 'trim')]
    public string $postalCode = '';

    public ?string $country = null;
}

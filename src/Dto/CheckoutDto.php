<?php

declare(strict_types=1);

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

class CheckoutDto
{
    #[Assert\Positive(message: 'Méthode de livraison obligatoire.')]
    public int $shippingMethodId = 0;

    #[Assert\NotNull(message: 'L\'adresse de livraison est obligatoire.')]
    #[Assert\Valid]
    public ?ShippingAddressDto $shippingAddress = null;

    public ?string $customerNote = null;
}

<?php

declare(strict_types=1);

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class TwoFactorVerifyDto
{
    public function __construct(
        #[Assert\NotBlank(message: 'Le code est requis.')]
        #[Assert\Length(exactly: 6, exactMessage: 'Le code doit contenir exactement 6 chiffres.')]
        #[Assert\Regex(pattern: '/^\d{6}$/', message: 'Le code doit contenir uniquement des chiffres.')]
        public string $code = '',
    ) {
    }
}

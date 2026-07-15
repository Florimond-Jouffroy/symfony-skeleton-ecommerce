<?php

declare(strict_types=1);

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

class ConfirmPasswordResetDto
{
    #[Assert\NotBlank(message: 'L\'email est obligatoire.')]
    #[Assert\Email(message: 'L\'email n\'est pas valide.')]
    public string $email = '';

    #[Assert\NotBlank(message: 'Le code est obligatoire.')]
    #[Assert\Length(exactly: 6, exactMessage: 'Le code doit contenir exactement {{ limit }} chiffres.')]
    #[Assert\Regex(pattern: '/^\d{6}$/', message: 'Le code doit être composé de 6 chiffres.')]
    public string $code = '';

    #[Assert\NotBlank(message: 'Le mot de passe est obligatoire.')]
    #[Assert\Length(min: 8, minMessage: 'Le mot de passe doit contenir au moins {{ limit }} caractères.')]
    public string $newPassword = '';

    #[Assert\NotBlank(message: 'La confirmation du mot de passe est obligatoire.')]
    #[Assert\EqualTo(propertyPath: 'newPassword', message: 'Les mots de passe ne correspondent pas.')]
    public string $newPasswordConfirm = '';
}

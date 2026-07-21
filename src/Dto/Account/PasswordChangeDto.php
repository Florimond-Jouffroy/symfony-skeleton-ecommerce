<?php

declare(strict_types=1);

namespace App\Dto\Account;

use Symfony\Component\Validator\Constraints as Assert;

class PasswordChangeDto
{
    public string $currentPassword = '';

    #[Assert\Length(min: 8, minMessage: 'Le nouveau mot de passe doit contenir au moins {{ limit }} caractères.')]
    public string $newPassword = '';

    #[Assert\EqualTo(propertyPath: 'newPassword', message: 'Les mots de passe ne correspondent pas.')]
    public string $newPasswordConfirm = '';
}

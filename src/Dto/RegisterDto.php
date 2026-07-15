<?php

declare(strict_types=1);

namespace App\Dto;

use App\Entity\User;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

#[UniqueEntity(entityClass: User::class, fields: ['email'], message: 'L\'inscription a échoué.')]
class RegisterDto
{
    #[Assert\NotBlank(message: 'L\'email est obligatoire.')]
    #[Assert\Email(message: 'L\'email n\'est pas valide.')]
    public string $email = '';

    #[Assert\NotBlank(message: 'Le mot de passe est obligatoire.')]
    #[Assert\Length(min: 8, minMessage: 'Le mot de passe doit contenir au moins {{ limit }} caractères.')]
    public string $password = '';

    #[Assert\NotBlank(message: 'La confirmation du mot de passe est obligatoire.')]
    #[Assert\EqualTo(propertyPath: 'password', message: 'Les mots de passe ne correspondent pas.')]
    public string $passwordConfirm = '';
}

<?php

declare(strict_types=1);

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

class ContactReplyDto
{
    public string $token = '';

    #[Assert\NotBlank(message: 'Le message ne peut pas être vide.', normalizer: 'trim')]
    public string $body = '';
}

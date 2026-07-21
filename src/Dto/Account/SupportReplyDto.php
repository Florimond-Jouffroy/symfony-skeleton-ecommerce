<?php

declare(strict_types=1);

namespace App\Dto\Account;

use Symfony\Component\Validator\Constraints as Assert;

class SupportReplyDto
{
    #[Assert\NotBlank(message: 'Le message ne peut pas être vide.', normalizer: 'trim')]
    public string $body = '';
}

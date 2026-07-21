<?php

declare(strict_types=1);

namespace App\Dto\Account;

use Symfony\Component\Validator\Constraints as Assert;

class SupportTicketDto
{
    #[Assert\NotBlank(message: 'Le sujet et le message sont requis.', normalizer: 'trim')]
    public string $subject = '';

    #[Assert\NotBlank(message: 'Le sujet et le message sont requis.', normalizer: 'trim')]
    public string $body = '';
}

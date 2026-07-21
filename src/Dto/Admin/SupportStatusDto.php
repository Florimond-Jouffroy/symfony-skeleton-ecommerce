<?php

declare(strict_types=1);

namespace App\Dto\Admin;

use App\Entity\SupportTicket;
use Symfony\Component\Validator\Constraints as Assert;

class SupportStatusDto
{
    #[Assert\Choice(
        choices: [SupportTicket::STATUS_OPEN, SupportTicket::STATUS_IN_PROGRESS, SupportTicket::STATUS_CLOSED],
        message: 'Statut invalide.',
    )]
    public string $status = '';
}

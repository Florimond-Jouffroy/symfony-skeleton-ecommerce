<?php

declare(strict_types=1);

namespace App\Dto\Admin;

use App\Entity\Invoice;
use Symfony\Component\Validator\Constraints as Assert;

class InvoiceStatusDto
{
    #[Assert\Choice(choices: Invoice::STATUSES, message: 'Statut invalide.')]
    public string $status = '';
}

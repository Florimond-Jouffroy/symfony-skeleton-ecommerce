<?php

declare(strict_types=1);

namespace App\Dto;

use App\Entity\ReturnRequest;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

class ReturnStatusDto
{
    /**
     * Statut cible. La possibilité de la transition depuis l'état courant reste
     * validée dans le manager (dépend de la demande).
     */
    #[Assert\Choice(
        choices: [ReturnRequest::STATUS_APPROVED, ReturnRequest::STATUS_REJECTED, ReturnRequest::STATUS_REFUNDED],
        message: 'Statut invalide.',
    )]
    public string $status = '';

    /** Note admin ; obligatoire en cas de refus (elle part au client dans le fil). */
    public ?string $adminNote = null;

    #[Assert\Callback]
    public function validateRejectionMotive(ExecutionContextInterface $context): void
    {
        if (ReturnRequest::STATUS_REJECTED === $this->status && '' === trim((string) $this->adminNote)) {
            $context->buildViolation('Un motif est obligatoire pour refuser un retour.')
                ->atPath('adminNote')
                ->addViolation();
        }
    }
}

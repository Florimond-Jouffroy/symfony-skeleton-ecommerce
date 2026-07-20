<?php

declare(strict_types=1);

namespace App\Dto;

use App\Entity\PromoCode;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

class PromoCodeDto
{
    #[Assert\NotBlank(message: 'Le code est obligatoire.')]
    #[Assert\Regex(
        pattern: '/^[A-Za-z0-9_-]{2,50}$/',
        message: 'Le code ne peut contenir que des lettres, chiffres, tirets et underscores (2–50 caractères).',
    )]
    public string $code = '';

    #[Assert\Choice(
        choices: [PromoCode::TYPE_PERCENT, PromoCode::TYPE_FIXED],
        message: 'Type invalide (percent ou fixed).',
    )]
    public string $type = PromoCode::TYPE_PERCENT;

    /** Pourcentage (ex. 15 = 15 %) ou montant fixe en centimes (ex. 500 = 5,00 €). */
    #[Assert\Positive(message: 'La valeur doit être supérieure à 0.')]
    public int $value = 0;

    public ?\DateTimeImmutable $expiresAt = null;

    /** null = illimité. */
    #[Assert\Positive(message: 'Le nombre maximum d\'utilisations doit être supérieur à 0.')]
    public ?int $maxUses = null;

    public bool $isActive = true;

    #[Assert\Callback]
    public function validatePercentRange(ExecutionContextInterface $context): void
    {
        if (PromoCode::TYPE_PERCENT === $this->type && $this->value > 100) {
            $context->buildViolation('Un pourcentage ne peut pas dépasser 100.')
                ->atPath('value')
                ->addViolation();
        }
    }
}

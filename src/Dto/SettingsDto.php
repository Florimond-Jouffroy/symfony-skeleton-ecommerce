<?php

declare(strict_types=1);

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * Mise à jour partielle des paramètres de la boutique.
 *
 * Chaque champ est optionnel : `null` signifie « non fourni » et laisse la valeur
 * inchangée. Ce partiel est volontaire — les secrets de paiement sont write-only
 * (jamais renvoyés par l'API), le client ne peut donc pas soumettre une ressource
 * complète.
 */
class SettingsDto
{
    #[Assert\Choice(choices: ['on_order', 'on_confirm'], message: 'Valeur invalide pour invoiceTrigger.')]
    public ?string $invoiceTrigger = null;

    #[Assert\Range(min: 0, max: 100, notInRangeMessage: 'Taux de TVA invalide.')]
    public ?float $defaultTaxRate = null;

    public ?bool $shopEnabled = null;

    public ?bool $maintenanceMode = null;

    public ?bool $returnsEnabled = null;

    public ?bool $stripeEnabled = null;

    public ?string $stripePublicKey = null;

    public ?string $stripeSecretKey = null;

    public ?string $stripeWebhookSecret = null;

    public ?bool $mollieEnabled = null;

    public ?string $mollieApiKey = null;

    public ?bool $paypalEnabled = null;

    public ?bool $paypalSandbox = null;

    public ?string $paypalClientId = null;

    public ?string $paypalClientSecret = null;

    public ?string $paypalWebhookId = null;

    #[Assert\Range(min: 0, max: 365, notInRangeMessage: 'Valeur invalide pour twoFaRememberDays (0–365).')]
    public ?int $twoFaRememberDays = null;

    #[Assert\Range(min: 0, max: 100, notInRangeMessage: 'Valeur invalide pour rateLimitMaxAttempts (0–100).')]
    public ?int $rateLimitMaxAttempts = null;

    #[Assert\Range(min: 1, max: 1440, notInRangeMessage: 'Valeur invalide pour rateLimitWindowMinutes (1–1440).')]
    public ?int $rateLimitWindowMinutes = null;
}

<?php

declare(strict_types=1);

namespace App\Controller\Api\Admin;

use App\Dto\SettingsDto;
use App\Repository\AppSettingRepository;
use App\Security\Voter\SettingVoter;
use App\Service\InvoiceService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/admin/parametres')]
class SettingController extends AbstractController
{
    public function __construct(
        private readonly InvoiceService $invoiceService,
        private readonly AppSettingRepository $settingRepo,
    ) {}

    #[Route('', name: 'api_admin_settings_get', methods: ['GET'])]
    public function get(): JsonResponse
    {
        $this->denyAccessUnlessGranted(SettingVoter::VIEW);

        return $this->json($this->buildPayload());
    }

    /**
     * Mise à jour partielle : seuls les champs non-null du DTO sont appliqués.
     * Les plages/enum sont validés en amont par SettingsDto ; ne restent ici que
     * la traduction champ → clé et la règle « ne pas écraser un secret par du vide ».
     */
    #[Route('', name: 'api_admin_settings_update', methods: ['PATCH'])]
    public function update(#[MapRequestPayload] SettingsDto $dto): JsonResponse
    {
        $this->denyAccessUnlessGranted(SettingVoter::EDIT);

        if (null !== $dto->invoiceTrigger) {
            $this->invoiceService->setInvoiceTrigger($dto->invoiceTrigger);
        }

        if (null !== $dto->defaultTaxRate) {
            $this->invoiceService->setDefaultTaxRate($dto->defaultTaxRate);
        }

        if (null !== $dto->shopEnabled) {
            $this->settingRepo->setValue('shop.enabled', $dto->shopEnabled ? 'true' : 'false');
        }

        if (null !== $dto->maintenanceMode) {
            $this->settingRepo->setValue('site.maintenance', $dto->maintenanceMode ? 'true' : 'false');
        }

        if (null !== $dto->returnsEnabled) {
            $this->settingRepo->setValue('returns.enabled', $dto->returnsEnabled ? 'true' : 'false');
        }

        if (null !== $dto->stripeEnabled) {
            $this->settingRepo->setValue('payment.stripe.enabled', $dto->stripeEnabled ? 'true' : 'false');
        }

        if (null !== $dto->stripePublicKey) {
            $this->settingRepo->setValue('payment.stripe.public_key', trim($dto->stripePublicKey));
        }

        $this->applySecret('payment.stripe.secret_key', $dto->stripeSecretKey);
        $this->applySecret('payment.stripe.webhook_secret', $dto->stripeWebhookSecret);

        if (null !== $dto->mollieEnabled) {
            $this->settingRepo->setValue('payment.mollie.enabled', $dto->mollieEnabled ? 'true' : 'false');
        }

        $this->applySecret('payment.mollie.api_key', $dto->mollieApiKey);

        if (null !== $dto->paypalEnabled) {
            $this->settingRepo->setValue('payment.paypal.enabled', $dto->paypalEnabled ? 'true' : 'false');
        }

        if (null !== $dto->paypalSandbox) {
            $this->settingRepo->setValue('payment.paypal.sandbox', $dto->paypalSandbox ? 'true' : 'false');
        }

        if (null !== $dto->paypalClientId) {
            $this->settingRepo->setValue('payment.paypal.client_id', trim($dto->paypalClientId));
        }

        $this->applySecret('payment.paypal.client_secret', $dto->paypalClientSecret);
        $this->applySecret('payment.paypal.webhook_id', $dto->paypalWebhookId);

        if (null !== $dto->twoFaRememberDays) {
            $this->settingRepo->setValue('security.2fa.trusted_device_days', (string) $dto->twoFaRememberDays);
        }

        if (null !== $dto->rateLimitMaxAttempts) {
            $this->settingRepo->setValue('security.rate_limit.max_attempts', (string) $dto->rateLimitMaxAttempts);
        }

        if (null !== $dto->rateLimitWindowMinutes) {
            $this->settingRepo->setValue('security.rate_limit.window_minutes', (string) $dto->rateLimitWindowMinutes);
        }

        return $this->json($this->buildPayload());
    }

    /**
     * Écrit un secret uniquement s'il est fourni et non vide — un champ vide laisse
     * la valeur existante intacte (les secrets ne sont jamais renvoyés par l'API).
     */
    private function applySecret(string $key, ?string $value): void
    {
        if (null !== $value && '' !== trim($value)) {
            $this->settingRepo->setValue($key, trim($value));
        }
    }

    /** @return array<string, mixed> */
    private function buildPayload(): array
    {
        $stripeSecretKey  = $this->settingRepo->getValue('payment.stripe.secret_key', '');
        $stripeWebhookKey = $this->settingRepo->getValue('payment.stripe.webhook_secret', '');

        $paypalClientSecret = $this->settingRepo->getValue('payment.paypal.client_secret', '');
        $paypalWebhookId    = $this->settingRepo->getValue('payment.paypal.webhook_id', '');

        $mollieApiKey = $this->settingRepo->getValue('payment.mollie.api_key', '');

        return [
            'maintenanceMode'        => 'true' === $this->settingRepo->getValue('site.maintenance', 'false'),
            'shopEnabled'            => 'true' === $this->settingRepo->getValue('shop.enabled', 'true'),
            'returnsEnabled'         => 'true' === $this->settingRepo->getValue('returns.enabled', 'false'),
            'invoiceTrigger'         => $this->invoiceService->getInvoiceTrigger(),
            'defaultTaxRate'         => $this->invoiceService->getDefaultTaxRate(),
            'stripeEnabled'          => 'true' === $this->settingRepo->getValue('payment.stripe.enabled', 'false'),
            'stripePublicKey'        => $this->settingRepo->getValue('payment.stripe.public_key', ''),
            'stripeSecretKeySet'     => '' !== $stripeSecretKey,
            'stripeWebhookSecretSet' => '' !== $stripeWebhookKey,
            'mollieEnabled'          => 'true' === $this->settingRepo->getValue('payment.mollie.enabled', 'false'),
            'mollieApiKeySet'        => '' !== $mollieApiKey,
            'mollieApiKeyPrefix'     => '' !== $mollieApiKey ? substr($mollieApiKey, 0, 5) : '',
            'paypalEnabled'          => 'true' === $this->settingRepo->getValue('payment.paypal.enabled', 'false'),
            'paypalSandbox'          => 'true' === $this->settingRepo->getValue('payment.paypal.sandbox', 'true'),
            'paypalClientId'         => $this->settingRepo->getValue('payment.paypal.client_id', ''),
            'paypalClientSecretSet'  => '' !== $paypalClientSecret,
            'paypalWebhookIdSet'     => '' !== $paypalWebhookId,
            'twoFaRememberDays'      => (int) $this->settingRepo->getValue('security.2fa.trusted_device_days', '30'),
            'rateLimitMaxAttempts'   => (int) $this->settingRepo->getValue('security.rate_limit.max_attempts', '5'),
            'rateLimitWindowMinutes' => (int) $this->settingRepo->getValue('security.rate_limit.window_minutes', '15'),
        ];
    }
}

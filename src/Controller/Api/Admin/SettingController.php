<?php

declare(strict_types=1);

namespace App\Controller\Api\Admin;

use App\Repository\AppSettingRepository;
use App\Security\Voter\SettingVoter;
use App\Service\InvoiceService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
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

    #[Route('', name: 'api_admin_settings_update', methods: ['PATCH'])]
    public function update(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(SettingVoter::EDIT);

        $payload = $request->toArray();

        if (isset($payload['invoiceTrigger'])) {
            $trigger = trim((string) $payload['invoiceTrigger']);
            if (!in_array($trigger, ['on_order', 'on_confirm'], true)) {
                return $this->json(['message' => 'Valeur invalide pour invoiceTrigger.'], Response::HTTP_UNPROCESSABLE_ENTITY);
            }
            $this->invoiceService->setInvoiceTrigger($trigger);
        }

        if (isset($payload['defaultTaxRate'])) {
            $rate = (float) $payload['defaultTaxRate'];
            if ($rate < 0 || $rate > 100) {
                return $this->json(['message' => 'Taux de TVA invalide.'], Response::HTTP_UNPROCESSABLE_ENTITY);
            }
            $this->invoiceService->setDefaultTaxRate($rate);
        }

        if (array_key_exists('shopEnabled', $payload)) {
            $this->settingRepo->setValue('shop.enabled', $payload['shopEnabled'] ? 'true' : 'false');
        }

        if (array_key_exists('maintenanceMode', $payload)) {
            $this->settingRepo->setValue('site.maintenance', $payload['maintenanceMode'] ? 'true' : 'false');
        }

        if (array_key_exists('returnsEnabled', $payload)) {
            $this->settingRepo->setValue('returns.enabled', $payload['returnsEnabled'] ? 'true' : 'false');
        }

        if (array_key_exists('stripeEnabled', $payload)) {
            $this->settingRepo->setValue('payment.stripe.enabled', $payload['stripeEnabled'] ? 'true' : 'false');
        }

        if (isset($payload['stripePublicKey'])) {
            $this->settingRepo->setValue('payment.stripe.public_key', trim((string) $payload['stripePublicKey']));
        }

        if (isset($payload['stripeSecretKey']) && '' !== trim((string) $payload['stripeSecretKey'])) {
            $this->settingRepo->setValue('payment.stripe.secret_key', trim((string) $payload['stripeSecretKey']));
        }

        if (isset($payload['stripeWebhookSecret']) && '' !== trim((string) $payload['stripeWebhookSecret'])) {
            $this->settingRepo->setValue('payment.stripe.webhook_secret', trim((string) $payload['stripeWebhookSecret']));
        }

        if (array_key_exists('mollieEnabled', $payload)) {
            $this->settingRepo->setValue('payment.mollie.enabled', $payload['mollieEnabled'] ? 'true' : 'false');
        }

        if (isset($payload['mollieApiKey']) && '' !== trim((string) $payload['mollieApiKey'])) {
            $this->settingRepo->setValue('payment.mollie.api_key', trim((string) $payload['mollieApiKey']));
        }

        if (array_key_exists('paypalEnabled', $payload)) {
            $this->settingRepo->setValue('payment.paypal.enabled', $payload['paypalEnabled'] ? 'true' : 'false');
        }

        if (array_key_exists('paypalSandbox', $payload)) {
            $this->settingRepo->setValue('payment.paypal.sandbox', $payload['paypalSandbox'] ? 'true' : 'false');
        }

        if (isset($payload['paypalClientId'])) {
            $this->settingRepo->setValue('payment.paypal.client_id', trim((string) $payload['paypalClientId']));
        }

        if (isset($payload['paypalClientSecret']) && '' !== trim((string) $payload['paypalClientSecret'])) {
            $this->settingRepo->setValue('payment.paypal.client_secret', trim((string) $payload['paypalClientSecret']));
        }

        if (isset($payload['paypalWebhookId']) && '' !== trim((string) $payload['paypalWebhookId'])) {
            $this->settingRepo->setValue('payment.paypal.webhook_id', trim((string) $payload['paypalWebhookId']));
        }

        if (isset($payload['twoFaRememberDays'])) {
            $days = (int) $payload['twoFaRememberDays'];
            if ($days < 0 || $days > 365) {
                return $this->json(['message' => 'Valeur invalide pour twoFaRememberDays (0–365).'], Response::HTTP_UNPROCESSABLE_ENTITY);
            }
            $this->settingRepo->setValue('security.2fa.trusted_device_days', (string) $days);
        }

        if (isset($payload['rateLimitMaxAttempts'])) {
            $max = (int) $payload['rateLimitMaxAttempts'];
            if ($max < 0 || $max > 100) {
                return $this->json(['message' => 'Valeur invalide pour rateLimitMaxAttempts (0–100).'], Response::HTTP_UNPROCESSABLE_ENTITY);
            }
            $this->settingRepo->setValue('security.rate_limit.max_attempts', (string) $max);
        }

        if (isset($payload['rateLimitWindowMinutes'])) {
            $window = (int) $payload['rateLimitWindowMinutes'];
            if ($window < 1 || $window > 1440) {
                return $this->json(['message' => 'Valeur invalide pour rateLimitWindowMinutes (1–1440).'], Response::HTTP_UNPROCESSABLE_ENTITY);
            }
            $this->settingRepo->setValue('security.rate_limit.window_minutes', (string) $window);
        }

        return $this->json($this->buildPayload());
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

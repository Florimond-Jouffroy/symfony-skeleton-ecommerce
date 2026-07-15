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
    ) {
    }

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

        return $this->json($this->buildPayload());
    }

    /** @return array<string, mixed> */
    private function buildPayload(): array
    {
        return [
            'maintenanceMode' => $this->settingRepo->getValue('site.maintenance', 'false') === 'true',
            'shopEnabled'     => $this->settingRepo->getValue('shop.enabled', 'true') === 'true',
            'invoiceTrigger'  => $this->invoiceService->getInvoiceTrigger(),
            'defaultTaxRate'  => $this->invoiceService->getDefaultTaxRate(),
        ];
    }
}

<?php

declare(strict_types=1);

namespace App\Payment\Provider;

use App\Entity\Order;
use App\Payment\PaymentProviderInterface;
use App\Payment\PaymentResult;
use App\Repository\AppSettingRepository;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class MolliePaymentProvider implements PaymentProviderInterface
{
    private const string API_URL = 'https://api.mollie.com/v2';

    public function __construct(
        private readonly AppSettingRepository $settingRepo,
        private readonly HttpClientInterface $httpClient,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public function getName(): string
    {
        return 'mollie';
    }

    public function isEnabled(): bool
    {
        return $this->settingRepo->getValue('payment.mollie.enabled', 'false') === 'true'
            && '' !== $this->settingRepo->getValue('payment.mollie.api_key', '');
    }

    public function getPublicKey(): string
    {
        return '';
    }

    public function createIntent(Order $order): PaymentResult
    {
        try {
            $apiKey      = $this->settingRepo->getValue('payment.mollie.api_key', '');
            $amountValue = number_format($order->getTotal() / 100, 2, '.', '');

            $redirectUrl = $this->urlGenerator->generate(
                'shop_catchall',
                ['path' => 'commander'],
                UrlGeneratorInterface::ABSOLUTE_URL,
            ).'?mollie_return=1';

            $webhookUrl = $this->urlGenerator->generate(
                'api_webhook_mollie',
                [],
                UrlGeneratorInterface::ABSOLUTE_URL,
            );

            $response = $this->httpClient->request('POST', self::API_URL.'/payments', [
                'headers' => [
                    'Authorization' => 'Bearer '.$apiKey,
                    'Content-Type'  => 'application/json',
                ],
                'json' => [
                    'amount'      => ['currency' => 'EUR', 'value' => $amountValue],
                    'description' => 'Commande '.$order->getOrderNumber(),
                    'redirectUrl' => $redirectUrl,
                    'webhookUrl'  => $webhookUrl,
                    'metadata'    => ['orderNumber' => $order->getOrderNumber()],
                ],
            ]);

            $data = $response->toArray();

            return new PaymentResult(
                success:      true,
                clientSecret: $data['_links']['checkout']['href'],
                intentId:     $data['id'],
            );
        } catch (\Throwable $e) {
            return new PaymentResult(success: false, error: $e->getMessage());
        }
    }

    public function constructWebhookEvent(string $payload, string $signature): object
    {
        parse_str($payload, $params);
        $paymentId = $params['id'] ?? null;

        if (!$paymentId) {
            throw new \Exception('Missing payment ID in Mollie webhook payload.');
        }

        $apiKey   = $this->settingRepo->getValue('payment.mollie.api_key', '');
        $response = $this->httpClient->request('GET', self::API_URL.'/payments/'.$paymentId, [
            'headers' => ['Authorization' => 'Bearer '.$apiKey],
        ]);

        return json_decode($response->getContent());
    }

    public function extractOrderNumber(object $event): ?string
    {
        return $event->metadata?->orderNumber ?? null;
    }

    public function isPaymentSucceeded(object $event): bool
    {
        return ($event->status ?? '') === 'paid';
    }

    public function isPaymentFailed(object $event): bool
    {
        return in_array($event->status ?? '', ['failed', 'expired', 'canceled'], true);
    }
}

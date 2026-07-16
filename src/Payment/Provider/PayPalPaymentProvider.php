<?php

declare(strict_types=1);

namespace App\Payment\Provider;

use App\Entity\Order;
use App\Payment\PaymentProviderInterface;
use App\Payment\PaymentResult;
use App\Repository\AppSettingRepository;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class PayPalPaymentProvider implements PaymentProviderInterface
{
    private const string SANDBOX_URL = 'https://api-m.sandbox.paypal.com';
    private const string LIVE_URL    = 'https://api-m.paypal.com';

    public function __construct(
        private readonly AppSettingRepository $settingRepo,
        private readonly HttpClientInterface $httpClient,
    ) {
    }

    public function getName(): string
    {
        return 'paypal';
    }

    public function isEnabled(): bool
    {
        return $this->settingRepo->getValue('payment.paypal.enabled', 'false') === 'true'
            && '' !== $this->settingRepo->getValue('payment.paypal.client_id', '')
            && '' !== $this->settingRepo->getValue('payment.paypal.client_secret', '');
    }

    public function getPublicKey(): string
    {
        return $this->settingRepo->getValue('payment.paypal.client_id', '');
    }

    public function createIntent(Order $order): PaymentResult
    {
        try {
            $accessToken = $this->getAccessToken();
            $amountValue = number_format($order->getTotal() / 100, 2, '.', '');

            $response = $this->httpClient->request('POST', $this->baseUrl().'/v2/checkout/orders', [
                'headers' => [
                    'Authorization' => 'Bearer '.$accessToken,
                    'Content-Type'  => 'application/json',
                ],
                'json' => [
                    'intent'         => 'CAPTURE',
                    'purchase_units' => [[
                        'reference_id' => $order->getOrderNumber(),
                        'custom_id'    => $order->getOrderNumber(),
                        'amount'       => [
                            'currency_code' => 'EUR',
                            'value'         => $amountValue,
                        ],
                    ]],
                ],
            ]);

            $data = $response->toArray();

            return new PaymentResult(
                success:      true,
                clientSecret: $data['id'], // PayPal Order ID used as token
            );
        } catch (\Throwable $e) {
            return new PaymentResult(success: false, error: $e->getMessage());
        }
    }

    public function constructWebhookEvent(string $payload, string $signature): object
    {
        // $signature = JSON-encoded array of PayPal webhook headers
        $headers = json_decode($signature, true);

        try {
            $accessToken = $this->getAccessToken();

            $response = $this->httpClient->request('POST', $this->baseUrl().'/v1/notifications/verify-webhook-signature', [
                'headers' => [
                    'Authorization' => 'Bearer '.$accessToken,
                    'Content-Type'  => 'application/json',
                ],
                'json' => [
                    'transmission_id'   => $headers['PAYPAL-TRANSMISSION-ID'] ?? '',
                    'transmission_time' => $headers['PAYPAL-TRANSMISSION-TIME'] ?? '',
                    'cert_url'          => $headers['PAYPAL-CERT-URL'] ?? '',
                    'auth_algo'         => $headers['PAYPAL-AUTH-ALGO'] ?? '',
                    'transmission_sig'  => $headers['PAYPAL-TRANSMISSION-SIG'] ?? '',
                    'webhook_id'        => $this->settingRepo->getValue('payment.paypal.webhook_id', ''),
                    'webhook_event'     => json_decode($payload, true),
                ],
            ]);

            $data = $response->toArray();

            if (($data['verification_status'] ?? '') !== 'SUCCESS') {
                throw new \Exception('PayPal webhook signature verification failed.');
            }
        } catch (\Exception $e) {
            throw new \Exception('PayPal webhook verification error: '.$e->getMessage());
        }

        return json_decode($payload);
    }

    public function extractOrderNumber(object $event): ?string
    {
        return $event->resource->custom_id
            ?? $event->resource->purchase_units[0]->reference_id
            ?? null;
    }

    public function isPaymentSucceeded(object $event): bool
    {
        return ($event->event_type ?? '') === 'PAYMENT.CAPTURE.COMPLETED';
    }

    public function isPaymentFailed(object $event): bool
    {
        return in_array($event->event_type ?? '', [
            'PAYMENT.CAPTURE.DENIED',
            'PAYMENT.CAPTURE.DECLINED',
            'CHECKOUT.ORDER.DECLINED',
        ], true);
    }

    private function getAccessToken(): string
    {
        $clientId     = $this->settingRepo->getValue('payment.paypal.client_id', '');
        $clientSecret = $this->settingRepo->getValue('payment.paypal.client_secret', '');

        $response = $this->httpClient->request('POST', $this->baseUrl().'/v1/oauth2/token', [
            'headers' => [
                'Authorization' => 'Basic '.base64_encode($clientId.':'.$clientSecret),
                'Content-Type'  => 'application/x-www-form-urlencoded',
            ],
            'body' => 'grant_type=client_credentials',
        ]);

        return $response->toArray()['access_token'];
    }

    private function baseUrl(): string
    {
        return $this->settingRepo->getValue('payment.paypal.sandbox', 'true') === 'true'
            ? self::SANDBOX_URL
            : self::LIVE_URL;
    }
}

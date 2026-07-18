<?php

declare(strict_types=1);

namespace App\Payment\Provider;

use App\Entity\Order;
use App\Payment\PaymentProviderInterface;
use App\Payment\PaymentResult;
use App\Repository\AppSettingRepository;
use Stripe\PaymentIntent;
use Stripe\Stripe;
use Stripe\Webhook;

class StripePaymentProvider implements PaymentProviderInterface
{
    public function __construct(
        private readonly AppSettingRepository $settingRepo,
    ) {}

    public function getName(): string
    {
        return 'stripe';
    }

    public function isEnabled(): bool
    {
        return 'true' === $this->settingRepo->getValue('payment.stripe.enabled', 'false')
            && '' !== $this->settingRepo->getValue('payment.stripe.secret_key', '');
    }

    public function getPublicKey(): string
    {
        return $this->settingRepo->getValue('payment.stripe.public_key', '');
    }

    public function createIntent(Order $order): PaymentResult
    {
        Stripe::setApiKey($this->settingRepo->getValue('payment.stripe.secret_key', ''));

        try {
            $intent = PaymentIntent::create([
                'amount'                    => $order->getTotal(),
                'currency'                  => 'eur',
                'automatic_payment_methods' => ['enabled' => true],
                'metadata'                  => ['orderNumber' => $order->getOrderNumber()],
            ]);

            return new PaymentResult(
                success: true,
                clientSecret: $intent->client_secret,
                intentId: $intent->id,
            );
        } catch (\Exception $e) {
            return new PaymentResult(success: false, error: $e->getMessage());
        }
    }

    public function constructWebhookEvent(string $payload, string $signature): object
    {
        Stripe::setApiKey($this->settingRepo->getValue('payment.stripe.secret_key', ''));

        return Webhook::constructEvent(
            $payload,
            $signature,
            $this->settingRepo->getValue('payment.stripe.webhook_secret', ''),
        );
    }

    public function extractOrderNumber(object $event): ?string
    {
        return $event->data->object->metadata->orderNumber ?? null;
    }

    public function extractEventId(object $event): ?string
    {
        return $event->id ?? null;
    }

    public function isPaymentSucceeded(object $event): bool
    {
        return 'payment_intent.succeeded' === $event->type;
    }

    public function isPaymentFailed(object $event): bool
    {
        return 'payment_intent.payment_failed' === $event->type;
    }
}

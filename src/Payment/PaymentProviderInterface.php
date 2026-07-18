<?php

declare(strict_types=1);

namespace App\Payment;

use App\Entity\Order;

interface PaymentProviderInterface
{
    public function getName(): string;

    public function isEnabled(): bool;

    public function getPublicKey(): string;

    public function createIntent(Order $order): PaymentResult;

    /**
     * Verifies the webhook signature and returns the provider-specific event object.
     *
     * @throws \Exception on invalid signature
     */
    public function constructWebhookEvent(string $payload, string $signature): object;

    /**
     * Returns the order number embedded in a succeeded payment event, or null if irrelevant.
     */
    public function extractOrderNumber(object $event): ?string;

    /**
     * Returns a stable, unique identifier for the event (used for idempotency),
     * or null if the provider does not expose one.
     */
    public function extractEventId(object $event): ?string;

    /**
     * Returns true if the event signals a successful payment.
     */
    public function isPaymentSucceeded(object $event): bool;

    /**
     * Returns true if the event signals a failed payment.
     */
    public function isPaymentFailed(object $event): bool;
}

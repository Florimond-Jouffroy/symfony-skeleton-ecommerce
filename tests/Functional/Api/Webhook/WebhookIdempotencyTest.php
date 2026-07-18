<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api\Webhook;

use App\Entity\Order;
use App\Entity\ProcessedWebhookEvent;
use App\Payment\PaymentProviderInterface;
use App\Payment\PaymentResult;
use App\Service\WebhookHandler;
use App\Tests\Functional\AbstractApiTestCase;

class WebhookIdempotencyTest extends AbstractApiTestCase
{
    public function testDuplicateEventIsProcessedOnce(): void
    {
        $order    = $this->createOrder($this->createCustomer(), 'ORD-TEST-0001', Order::STATUS_PENDING);
        $provider = $this->stubProvider('ORD-TEST-0001', 'evt_123');
        $handler  = static::getContainer()->get(WebhookHandler::class);

        $handler->handle($provider, new \stdClass());
        $handler->handle($provider, new \stdClass()); // même événement, renvoyé par le fournisseur

        $this->em->refresh($order);
        self::assertSame(Order::STATUS_CONFIRMED, $order->getStatus());
        // Une seule trace de dédup et une seule transition "confirmed".
        self::assertCount(1, $this->em->getRepository(ProcessedWebhookEvent::class)->findAll());
        self::assertSame(1, $this->confirmedHistoryCount($order));
    }

    public function testWithoutEventIdStateMachinePreventsDoubleProcessing(): void
    {
        $order    = $this->createOrder($this->createCustomer(), 'ORD-TEST-0002', Order::STATUS_PENDING);
        $provider = $this->stubProvider('ORD-TEST-0002', null); // pas d'ID → pas de déduplication
        $handler  = static::getContainer()->get(WebhookHandler::class);

        $handler->handle($provider, new \stdClass());
        $handler->handle($provider, new \stdClass());

        $this->em->refresh($order);
        self::assertSame(Order::STATUS_CONFIRMED, $order->getStatus());
        // Aucun ID → rien en table, mais la machine à états bloque quand même le doublon.
        self::assertCount(0, $this->em->getRepository(ProcessedWebhookEvent::class)->findAll());
        self::assertSame(1, $this->confirmedHistoryCount($order));
    }

    private function confirmedHistoryCount(Order $order): int
    {
        $count = 0;
        foreach ($order->getStatusHistory() as $history) {
            if (Order::STATUS_CONFIRMED === $history->getStatus()) {
                ++$count;
            }
        }

        return $count;
    }

    private function stubProvider(string $orderNumber, ?string $eventId): PaymentProviderInterface
    {
        return new class($orderNumber, $eventId) implements PaymentProviderInterface {
            public function __construct(
                private readonly string $orderNumber,
                private readonly ?string $eventId,
            ) {}

            public function getName(): string
            {
                return 'test';
            }

            public function isEnabled(): bool
            {
                return true;
            }

            public function getPublicKey(): string
            {
                return '';
            }

            public function createIntent(Order $order): PaymentResult
            {
                return new PaymentResult(false);
            }

            public function constructWebhookEvent(string $payload, string $signature): object
            {
                return new \stdClass();
            }

            public function extractOrderNumber(object $event): ?string
            {
                return $this->orderNumber;
            }

            public function extractEventId(object $event): ?string
            {
                return $this->eventId;
            }

            public function isPaymentSucceeded(object $event): bool
            {
                return true;
            }

            public function isPaymentFailed(object $event): bool
            {
                return false;
            }
        };
    }
}

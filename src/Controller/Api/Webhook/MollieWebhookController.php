<?php

declare(strict_types=1);

namespace App\Controller\Api\Webhook;

use App\Payment\Provider\MolliePaymentProvider;
use App\Service\WebhookHandler;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/webhook/mollie', name: 'api_webhook_mollie', methods: ['POST'])]
class MollieWebhookController extends AbstractController
{
    public function __construct(
        private readonly MolliePaymentProvider $mollie,
        private readonly WebhookHandler $handler,
    ) {}

    public function __invoke(Request $request): Response
    {
        $payload = $request->getContent();

        try {
            $event = $this->mollie->constructWebhookEvent($payload, '');
        } catch (\Exception) {
            return new Response('Invalid payload', Response::HTTP_BAD_REQUEST);
        }

        return $this->handler->handle($this->mollie, $event);
    }
}

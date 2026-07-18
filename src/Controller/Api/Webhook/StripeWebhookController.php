<?php

declare(strict_types=1);

namespace App\Controller\Api\Webhook;

use App\Payment\Provider\StripePaymentProvider;
use App\Service\WebhookHandler;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/webhook/stripe', name: 'api_webhook_stripe', methods: ['POST'])]
class StripeWebhookController extends AbstractController
{
    public function __construct(
        private readonly StripePaymentProvider $stripe,
        private readonly WebhookHandler $handler,
    ) {}

    public function __invoke(Request $request): Response
    {
        $payload   = $request->getContent();
        $signature = $request->headers->get('Stripe-Signature', '');

        try {
            $event = $this->stripe->constructWebhookEvent($payload, $signature);
        } catch (\Exception) {
            return new Response('Invalid signature', Response::HTTP_BAD_REQUEST);
        }

        return $this->handler->handle($this->stripe, $event);
    }
}

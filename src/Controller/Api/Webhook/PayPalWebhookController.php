<?php

declare(strict_types=1);

namespace App\Controller\Api\Webhook;

use App\Payment\Provider\PayPalPaymentProvider;
use App\Service\WebhookHandler;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/webhook/paypal', name: 'api_webhook_paypal', methods: ['POST'])]
class PayPalWebhookController extends AbstractController
{
    public function __construct(
        private readonly PayPalPaymentProvider $paypal,
        private readonly WebhookHandler $handler,
    ) {}

    public function __invoke(Request $request): Response
    {
        $payload   = $request->getContent();
        $signature = json_encode([
            'PAYPAL-TRANSMISSION-ID'   => $request->headers->get('PAYPAL-TRANSMISSION-ID', ''),
            'PAYPAL-TRANSMISSION-TIME' => $request->headers->get('PAYPAL-TRANSMISSION-TIME', ''),
            'PAYPAL-CERT-URL'          => $request->headers->get('PAYPAL-CERT-URL', ''),
            'PAYPAL-AUTH-ALGO'         => $request->headers->get('PAYPAL-AUTH-ALGO', ''),
            'PAYPAL-TRANSMISSION-SIG'  => $request->headers->get('PAYPAL-TRANSMISSION-SIG', ''),
        ]);

        try {
            $event = $this->paypal->constructWebhookEvent($payload, $signature);
        } catch (\Exception) {
            return new Response('Invalid signature', Response::HTTP_BAD_REQUEST);
        }

        return $this->handler->handle($this->paypal, $event);
    }
}

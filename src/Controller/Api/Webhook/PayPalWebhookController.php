<?php

declare(strict_types=1);

namespace App\Controller\Api\Webhook;

use App\Payment\Provider\PayPalPaymentProvider;
use App\Repository\OrderRepository;
use App\Service\Manager\OrderManager;
use App\Service\OrderMailer;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/webhook/paypal', name: 'api_webhook_paypal', methods: ['POST'])]
class PayPalWebhookController extends AbstractController
{
    public function __construct(
        private readonly PayPalPaymentProvider $paypal,
        private readonly OrderRepository $orderRepository,
        private readonly OrderManager $orderManager,
        private readonly OrderMailer $orderMailer,
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

        $orderNumber = $this->paypal->extractOrderNumber($event);
        if (null === $orderNumber) {
            return new Response('ok');
        }

        $order = $this->orderRepository->findOneBy(['orderNumber' => $orderNumber]);
        if (null === $order) {
            return new Response('Order not found', Response::HTTP_NOT_FOUND);
        }

        if ($this->paypal->isPaymentSucceeded($event)) {
            $this->orderManager->transition($order, 'confirmed', 'Paiement PayPal confirmé.');
            $this->orderMailer->sendOrderConfirmation($order);
        } elseif ($this->paypal->isPaymentFailed($event)) {
            $this->orderManager->transition($order, 'cancelled', 'Paiement PayPal échoué.');
        }

        return new Response('ok');
    }
}

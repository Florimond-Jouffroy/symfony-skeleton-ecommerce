<?php

declare(strict_types=1);

namespace App\Controller\Api\Webhook;

use App\Payment\Provider\StripePaymentProvider;
use App\Repository\OrderRepository;
use App\Service\Manager\OrderManager;
use App\Service\OrderMailer;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/webhook/stripe', name: 'api_webhook_stripe', methods: ['POST'])]
class StripeWebhookController extends AbstractController
{
    public function __construct(
        private readonly StripePaymentProvider $stripe,
        private readonly OrderRepository $orderRepository,
        private readonly OrderManager $orderManager,
        private readonly OrderMailer $orderMailer,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        $payload   = $request->getContent();
        $signature = $request->headers->get('Stripe-Signature', '');

        try {
            $event = $this->stripe->constructWebhookEvent($payload, $signature);
        } catch (\Exception) {
            return new Response('Invalid signature', Response::HTTP_BAD_REQUEST);
        }

        $orderNumber = $this->stripe->extractOrderNumber($event);
        if (null === $orderNumber) {
            return new Response('ok');
        }

        $order = $this->orderRepository->findOneBy(['orderNumber' => $orderNumber]);
        if (null === $order) {
            return new Response('Order not found', Response::HTTP_NOT_FOUND);
        }

        if ($this->stripe->isPaymentSucceeded($event)) {
            $this->orderManager->transition($order, 'confirmed', 'Paiement Stripe confirmé.');
            $this->orderMailer->sendOrderConfirmation($order);
        } elseif ($this->stripe->isPaymentFailed($event)) {
            $this->orderManager->transition($order, 'cancelled', 'Paiement Stripe échoué.');
        }

        return new Response('ok');
    }
}

<?php

declare(strict_types=1);

namespace App\Controller\Api\Webhook;

use App\Payment\Provider\MolliePaymentProvider;
use App\Repository\OrderRepository;
use App\Service\Manager\OrderManager;
use App\Service\OrderMailer;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/webhook/mollie', name: 'api_webhook_mollie', methods: ['POST'])]
class MollieWebhookController extends AbstractController
{
    public function __construct(
        private readonly MolliePaymentProvider $mollie,
        private readonly OrderRepository $orderRepository,
        private readonly OrderManager $orderManager,
        private readonly OrderMailer $orderMailer,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        $payload = $request->getContent();

        try {
            $event = $this->mollie->constructWebhookEvent($payload, '');
        } catch (\Exception) {
            return new Response('Invalid payload', Response::HTTP_BAD_REQUEST);
        }

        $orderNumber = $this->mollie->extractOrderNumber($event);
        if (null === $orderNumber) {
            return new Response('ok');
        }

        $order = $this->orderRepository->findOneBy(['orderNumber' => $orderNumber]);
        if (null === $order) {
            return new Response('Order not found', Response::HTTP_NOT_FOUND);
        }

        if ($this->mollie->isPaymentSucceeded($event)) {
            $this->orderManager->transition($order, 'confirmed', 'Paiement Mollie confirmé.');
            $this->orderMailer->sendOrderConfirmation($order);
        } elseif ($this->mollie->isPaymentFailed($event)) {
            $this->orderManager->transition($order, 'cancelled', 'Paiement Mollie échoué.');
        }

        return new Response('ok');
    }
}

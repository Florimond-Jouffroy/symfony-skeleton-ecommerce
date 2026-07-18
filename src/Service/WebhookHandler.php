<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Order;
use App\Entity\ProcessedWebhookEvent;
use App\Payment\PaymentProviderInterface;
use App\Repository\OrderRepository;
use App\Repository\ProcessedWebhookEventRepository;
use App\Service\Manager\OrderManager;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Response;

/**
 * Traite le résultat d'un webhook de paiement une fois sa signature vérifiée,
 * de façon idempotente : un événement déjà traité (retry du fournisseur) ne
 * relance ni la transition ni l'email de confirmation.
 */
class WebhookHandler
{
    public function __construct(
        private readonly OrderRepository $orderRepository,
        private readonly OrderManager $orderManager,
        private readonly OrderMailer $orderMailer,
        private readonly ProcessedWebhookEventRepository $processedEvents,
        private readonly EntityManagerInterface $em,
    ) {}

    public function handle(PaymentProviderInterface $provider, object $event): Response
    {
        $orderNumber = $provider->extractOrderNumber($event);
        if (null === $orderNumber) {
            return new Response('ok');
        }

        // Déduplication : si cet événement a déjà été traité, on ne fait rien.
        $eventId = $provider->extractEventId($event);
        if (null !== $eventId && $this->processedEvents->isProcessed($provider->getName(), $eventId)) {
            return new Response('ok');
        }

        $order = $this->orderRepository->findOneBy(['orderNumber' => $orderNumber]);
        if (null === $order) {
            return new Response('Order not found', Response::HTTP_NOT_FOUND);
        }

        $label = ucfirst($provider->getName());
        $acted = false;

        if ($provider->isPaymentSucceeded($event)) {
            // L'email n'est envoyé que si la transition a réellement eu lieu :
            // la machine à états refuse pending→confirmed si la commande est déjà
            // confirmée, ce qui bloque tout doublon même sans l'ID d'événement.
            if ($this->orderManager->transition($order, Order::STATUS_CONFIRMED, "Paiement {$label} confirmé.")) {
                $this->orderMailer->sendOrderConfirmation($order);
            }
            $acted = true;
        } elseif ($provider->isPaymentFailed($event)) {
            $this->orderManager->transition($order, Order::STATUS_CANCELLED, "Paiement {$label} échoué.");
            $acted = true;
        }

        // On ne marque l'événement traité que si une action terminale a eu lieu :
        // un webhook intermédiaire (ex. Mollie "open") ne doit pas bloquer le
        // "paid" ultérieur qui porte le même identifiant de paiement.
        if ($acted && null !== $eventId) {
            $this->markProcessed($provider->getName(), $eventId);
        }

        return new Response('ok');
    }

    private function markProcessed(string $provider, string $eventId): void
    {
        try {
            $this->em->persist(new ProcessedWebhookEvent($provider, $eventId));
            $this->em->flush();
        } catch (UniqueConstraintViolationException) {
            // Deux livraisons concurrentes du même événement : l'autre a déjà
            // enregistré la trace, rien à faire.
        }
    }
}
